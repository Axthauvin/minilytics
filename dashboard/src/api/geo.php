<?php
declare(strict_types=1);

/**
 * Local, no-quota country/region/city lookup backed by DB-IP City Lite.
 * The database is CC BY 4.0; the dashboard links to DB-IP as required.
 */
require_once __DIR__ . '/../lib/maxmind-db/autoload.php';

final class GeoLocation {
    private const DATABASE_URL = 'https://cdn.jsdelivr.net/npm/dbip-city-lite/dbip-city-lite.mmdb.gz';
    private const MAX_AGE_SECONDS = 35 * 86400;
    private const RETRY_DELAY_SECONDS = 3600;
    private static ?\MaxMind\Db\Reader $reader = null;

    public static function lookup(string $ip): ?array {
        if (!self::ensureDatabase()) return null;
        try {
            self::$reader ??= new \MaxMind\Db\Reader(self::databasePath());
            $record = self::$reader->get($ip);
            $code = strtoupper((string)($record['country']['iso_code'] ?? ''));
            $country = (string)($record['country']['names']['en'] ?? '');
            if (!preg_match('/^[A-Z]{2}$/', $code) || $country === '') return null;
            return [
                'country' => $country,
                'country_code' => $code,
                'region' => (string)($record['subdivisions'][0]['names']['en'] ?? ''),
                'city' => (string)($record['city']['names']['en'] ?? ''),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private static function directory(): string {
        return dirname(__DIR__, 3) . '/data/geo';
    }

    private static function databasePath(): string {
        return self::directory() . '/dbip-city-lite.mmdb';
    }

    private static function ensureDatabase(): bool {
        $database = self::databasePath();
        if (is_file($database) && filesize($database) > 1024 && filemtime($database) >= time() - self::MAX_AGE_SECONDS) return true;
        $directory = self::directory();
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) return is_file($database);
        $failure = $directory . '/last-update-failure';
        if (is_file($failure) && filemtime($failure) >= time() - self::RETRY_DELAY_SECONDS) return is_file($database);
        $lock = @fopen($directory . '/update.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return is_file($database);
        try {
            if (is_file($database) && filesize($database) > 1024 && filemtime($database) >= time() - self::MAX_AGE_SECONDS) return true;
            $compressed = self::download();
            $contents = is_string($compressed) ? @gzdecode($compressed) : false;
            if (!is_string($contents) || strlen($contents) < 1024) throw new RuntimeException('Invalid DB-IP City Lite database download.');
            $temporary = $database . '.new';
            if (@file_put_contents($temporary, $contents, LOCK_EX) === false || !@rename($temporary, $database)) throw new RuntimeException('Unable to install DB-IP City Lite database.');
            @unlink($failure);
            return true;
        } catch (Throwable) {
            @touch($failure);
            return is_file($database);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function download(): string|false {
        if (function_exists('curl_init')) {
            $curl = curl_init(self::DATABASE_URL);
            curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Accept: application/gzip']]);
            $body = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            return $status === 200 && is_string($body) ? $body : false;
        }
        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) return false;
        return @file_get_contents(self::DATABASE_URL, false, stream_context_create(['http' => ['timeout' => 30]]));
    }
}
