<?php

declare(strict_types=1);

namespace Minilytics\Geo;

use RuntimeException;
use Throwable;

/**
 * Local, no-quota country/region/city lookup backed by DB-IP City Lite.
 * The database is CC BY 4.0; the dashboard links to DB-IP as required.
 */

final class GeoLocation
{
    private const DATABASE_URL = 'https://cdn.jsdelivr.net/npm/dbip-city-lite/dbip-city-lite.mmdb.gz';
    private const MAX_AGE_SECONDS = 35 * 86400;
    private const RETRY_DELAY_SECONDS = 3600;
    private static ?\MaxMind\Db\Reader $reader = null;

    public static function lookup(string $ip): ?array
    {
        if (!self::ensureDatabase()) {
            return null;
        }
        try {
            self::$reader ??= new \MaxMind\Db\Reader(self::databasePath());
            $record = self::$reader->get($ip);
            $code = strtoupper((string) ($record['country']['iso_code'] ?? ''));
            $country = (string) ($record['country']['names']['en'] ?? '');
            if (!preg_match('/^[A-Z]{2}$/', $code) || $country === '') {
                return null;
            }
            return [
                'country' => $country,
                'country_code' => $code,
                'region' => (string) ($record['subdivisions'][0]['names']['en'] ?? ''),
                'city' => (string) ($record['city']['names']['en'] ?? ''),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private static function directory(): string
    {
        return dirname(__DIR__, 2) . '/data/geo';
    }

    private static function databasePath(): string
    {
        return self::directory() . '/dbip-city-lite.mmdb';
    }

    private static function ensureDatabase(): bool
    {
        $database = self::databasePath();
        if (is_file($database) && filesize($database) > 1024 && filemtime($database) >= time() - self::MAX_AGE_SECONDS) {
            return true;
        }
        $directory = self::directory();
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return is_file($database);
        }
        $failure = $directory . '/last-update-failure';
        if (is_file($failure) && filemtime($failure) >= time() - self::RETRY_DELAY_SECONDS) {
            return is_file($database);
        }
        $lock = @fopen($directory . '/update.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return is_file($database);
        }
        try {
            if (is_file($database) && filesize($database) > 1024 && filemtime($database) >= time() - self::MAX_AGE_SECONDS) {
                return true;
            }
            // The compressed City Lite database is about 60 MB. Stream both
            // download and decompression so shared hosts never need hundreds
            // of megabytes of PHP memory during the initial installation.
            @set_time_limit(180);
            $compressed = $database . '.download';
            $temporary = $database . '.new';
            if (!self::downloadTo($compressed) || !self::decompressTo($compressed, $temporary) || filesize($temporary) < 1024 || !@rename($temporary, $database)) {
                throw new RuntimeException('Unable to install DB-IP City Lite database.');
            }
            @unlink($failure);
            return true;
        } catch (Throwable $error) {
            @file_put_contents($failure, gmdate('c') . ' ' . $error->getMessage() . PHP_EOL, LOCK_EX);
            return is_file($database);
        } finally {
            @unlink($database . '.download');
            @unlink($database . '.new');
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function downloadTo(string $destination): bool
    {
        $file = @fopen($destination, 'wb');
        if ($file === false) {
            return false;
        }
        if (function_exists('curl_init')) {
            $curl = curl_init(self::DATABASE_URL);
            curl_setopt_array($curl, [CURLOPT_FILE => $file, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 120, CURLOPT_HTTPHEADER => ['Accept: application/gzip']]);
            $ok = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            fclose($file);
            return $ok === true && $status === 200;
        }
        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            fclose($file);
            return false;
        }
        $source = @fopen(self::DATABASE_URL, 'rb', false, stream_context_create(['http' => ['timeout' => 120]]));
        if ($source === false) {
            fclose($file);
            return false;
        }
        $copied = stream_copy_to_stream($source, $file);
        fclose($source);
        fclose($file);
        return $copied !== false;
    }

    private static function decompressTo(string $compressed, string $destination): bool
    {
        $input = @gzopen($compressed, 'rb');
        $output = @fopen($destination, 'wb');
        if ($input === false || $output === false) {
            if (is_resource($input)) {
                gzclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            return false;
        }
        try {
            while (!gzeof($input)) {
                $chunk = gzread($input, 1024 * 1024);
                if ($chunk === false || ($chunk !== '' && fwrite($output, $chunk) !== strlen($chunk))) {
                    return false;
                }
            }
            return true;
        } finally {
            gzclose($input);
            fclose($output);
        }
    }
}
