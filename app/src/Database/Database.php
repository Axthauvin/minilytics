<?php

declare(strict_types=1);

namespace Minilytics\Database;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use SQLite3;
use Throwable;

/**
 * Minilytics Database Connection Helper
 * Supports isolated SQLite databases or a shared MySQL/MariaDB database.
 */
class Database
{
    private static array $instances = [];

    /**
     * Resolve the persistent data directory, kept outside the web root.
     *
     * Uses MINILYTICS_DATA_DIR when set, otherwise a "minilytics-data" folder
     * next to the web root.
     */
    public static function getDataDir(): string
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $configured = getenv('MINILYTICS_DATA_DIR');
        $dir = is_string($configured) && trim($configured) !== ''
            ? rtrim(trim($configured), '/\\')
            : dirname(__DIR__, 3) . '/minilytics-data';

        if ((is_dir($dir) || @mkdir($dir, 0770, true) || is_dir($dir)) && is_writable($dir)) {
            return $resolved = $dir;
        }
        throw new RuntimeException('No writable data directory. Set MINILYTICS_DATA_DIR to a writable path outside the web root.');
    }

    public static function getSitesFilePath(): string
    {
        return self::getDataDir() . '/sites.json';
    }

    public static function getDatabaseConfigPath(): string
    {
        return self::getDataDir() . '/database.json';
    }
    public static function getDatabaseConfig(): array
    {
        $config = is_file(self::getDatabaseConfigPath()) ? json_decode((string) file_get_contents(self::getDatabaseConfigPath()), true) : [];
        $config = is_array($config) ? $config : [];
        return array_merge(['driver' => 'sqlite', 'host' => '', 'port' => 3306, 'database' => '', 'username' => '', 'password' => ''], $config);
    }
    public static function publicDatabaseConfig(): array
    {
        $config = self::getDatabaseConfig();
        unset($config['password']);
        $config['configured'] = $config['driver'] === 'sqlite' || ($config['host'] !== '' && $config['database'] !== '' && $config['username'] !== '');
        return $config;
    }
    public static function saveDatabaseConfig(array $input): array
    {
        $driver = strtolower(trim((string) ($input['driver'] ?? 'sqlite')));
        if (!in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new InvalidArgumentException('Unsupported database driver.');
        }
        $current = self::getDatabaseConfig();
        $config = ['driver' => $driver, 'host' => trim((string) ($input['host'] ?? '')), 'port' => max(1, min(65535, (int) ($input['port'] ?? 3306))), 'database' => trim((string) ($input['database'] ?? '')), 'username' => trim((string) ($input['username'] ?? '')), 'password' => array_key_exists('password', $input) && $input['password'] !== '' ? (string) $input['password'] : $current['password']];
        if ($driver === 'sqlite') {
            $config = ['driver' => 'sqlite', 'host' => '', 'port' => 3306, 'database' => '', 'username' => '', 'password' => ''];
        }
        if ($driver !== 'sqlite' && ($config['host'] === '' || $config['database'] === '' || $config['username'] === '')) {
            throw new InvalidArgumentException('Host, database name and username are required.');
        }
        self::testDatabaseConfig(array_merge($config, ['create_database' => !empty($input['create_database'])]));
        file_put_contents(self::getDatabaseConfigPath(), json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        @chmod(self::getDatabaseConfigPath(), 0600);
        self::$instances = [];
        return self::publicDatabaseConfig();
    }
    public static function testDatabaseConfig(array $input): array
    {
        $driver = strtolower((string) ($input['driver'] ?? 'sqlite'));
        if ($driver === 'sqlite') {
            if (!class_exists('SQLite3')) {
                throw new RuntimeException('The SQLite3 PHP extension is not enabled.');
            }
            return ['driver' => 'sqlite', 'version' => SQLite3::version()['versionString']];
        }
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('The PDO MySQL extension (pdo_mysql) is not enabled on this server.');
        }
        $host = trim((string) ($input['host'] ?? ''));
        $database = trim((string) ($input['database'] ?? ''));
        $username = trim((string) ($input['username'] ?? ''));
        if ($host === '' || $database === '' || $username === '') {
            throw new InvalidArgumentException('Host, database name and username are required.');
        }
        if (!preg_match('/^[A-Za-z0-9_$]{1,64}$/', $database)) {
            throw new InvalidArgumentException('Database names may contain only letters, numbers, underscores and dollar signs.');
        }
        $port = max(1, min(65535, (int) ($input['port'] ?? 3306)));
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5];
        $password = (string) ($input['password'] ?? '');
        $created = false;
        if (!empty($input['create_database'])) {
            // Connect without a schema first: this permits creating a missing
            // schema but still requires the caller's explicit CREATE privilege.
            $server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, $options);
            $check = $server->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :database');
            $check->execute([':database' => $database]);
            $created = $check->fetchColumn() === false;
            $server->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, $options);
        return ['driver' => $driver, 'version' => (string) $pdo->query('SELECT VERSION()')->fetchColumn(), 'database_created' => $created];
    }

    public static function sanitizeSiteId(?string $siteId): string
    {
        if (empty($siteId) || $siteId === 'all') {
            throw new InvalidArgumentException('Select a website before accessing analytics.');
        }

        $cleanSiteId = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower($siteId));
        if ($cleanSiteId === '') {
            throw new InvalidArgumentException('A valid website must be selected before accessing analytics.');
        }

        foreach (self::getAvailableSites() as $site) {
            if (($site['id'] ?? '') === $cleanSiteId) {
                return $cleanSiteId;
            }
        }

        throw new InvalidArgumentException('The selected website does not exist.');
    }

    public static function getAvailableSites(): array
    {
        $dataDir = self::getDataDir();
        $sitesFile = self::getSitesFilePath();
        $sites = [];

        if (file_exists($sitesFile)) {
            $sites = json_decode(file_get_contents($sitesFile), true) ?: [];
        }

        // Backfill configuration for installations created before protected
        // collection was introduced. The generated key is only exposed to admins.
        $sitesChanged = false;
        foreach ($sites as &$site) {
            if (empty($site['write_key'])) {
                $site['write_key'] = bin2hex(random_bytes(24));
                $sitesChanged = true;
            }
            if (!isset($site['allowed_domains'])) {
                $site['allowed_domains'] = array_values(array_filter([self::normalizeHost((string) ($site['domain'] ?? ''))]));
                $sitesChanged = true;
            }
            if (!isset($site['internal_ips'])) {
                $site['internal_ips'] = [];
                $sitesChanged = true;
            }
            if (!isset($site['retention_days'])) {
                $site['retention_days'] = 395;
                $sitesChanged = true;
            }
        }
        unset($site);
        if ($sitesChanged) {
            self::saveSites($sites);
        }

        // Auto-discover any .db files inside data/
        $dbFiles = glob("{$dataDir}/*.db") ?: [];
        $knownIds = array_column($sites, 'id');

        foreach ($dbFiles as $file) {
            $base = basename($file, '.db');
            if ($base === 'auth') {
                continue;
            }
            if (!in_array($base, $knownIds)) {
                $sites[] = [
                    'id' => $base,
                    'name' => ucwords(str_replace(['_', '-'], ' ', $base)),
                    'domain' => '',
                    'allowed_domains' => [],
                    'internal_ips' => [],
                    'retention_days' => 395,
                    'write_key' => bin2hex(random_bytes(24)),
                    'created_at' => gmdate('Y-m-d H:i:s', filemtime($file)),
                ];
                $sitesChanged = true;
            }
        }
        if ($sitesChanged) {
            self::saveSites($sites);
        }

        return $sites;
    }

    public static function getSitesWithStats(): array
    {
        $sites = self::getAvailableSites();
        $dataDir = self::getDataDir();
        $sevenDaysAgo = gmdate('Y-m-d 00:00:00', strtotime('-6 days'));

        foreach ($sites as &$site) {
            $dbPath = "{$dataDir}/{$site['id']}.db";
            $views = 0;
            $visitors = 0;
            $visitors7d = 0;
            $live = 0;
            $lastActive = null;

            // 7 daily buckets for the sparkline (day -6 to today)
            $sparklineMap = [];
            for ($i = 6; $i >= 0; $i--) {
                $dayStr = gmdate('Y-m-d', strtotime("-{$i} days"));
                $sparklineMap[$dayStr] = 0;
            }

            if (self::getDatabaseConfig()['driver'] !== 'sqlite' || file_exists($dbPath)) {
                try {
                    $db = self::getConnection($site['id']);
                    $views = (int) $db->querySingle("SELECT COUNT(*) FROM user_activity WHERE json_extract(action, '$.name') = 'pageview'");
                    $visitors = (int) $db->querySingle("SELECT COUNT(DISTINCT COALESCE(visitor_id, session_id)) FROM user_activity");
                    $visitors7d = (int) $db->querySingle("SELECT COUNT(DISTINCT COALESCE(visitor_id, session_id)) FROM user_activity WHERE timestamp >= '{$sevenDaysAgo}'");
                    $liveThresh = gmdate('Y-m-d H:i:s', time() - 300);
                    $live = (int) $db->querySingle("SELECT COUNT(DISTINCT COALESCE(visitor_id, session_id)) FROM user_activity WHERE timestamp >= '{$liveThresh}'");
                    $lastActive = $db->querySingle("SELECT MAX(timestamp) FROM user_activity");

                    // Real daily visitors for the 7 days
                    $spStmt = $db->prepare("SELECT strftime('%Y-%m-%d', timestamp) as day, COUNT(DISTINCT COALESCE(visitor_id, session_id)) as v
                                           FROM user_activity 
                                           WHERE timestamp >= :start_date 
                                           GROUP BY day");
                    $spStmt->bindValue(':start_date', $sevenDaysAgo, SQLITE3_TEXT);
                    $spRes = $spStmt->execute();
                    while ($spRow = $spRes->fetchArray(SQLITE3_ASSOC)) {
                        if (isset($sparklineMap[$spRow['day']])) {
                            $sparklineMap[$spRow['day']] = (int) $spRow['v'];
                        }
                    }
                } catch (Throwable $e) {
                    // Ignore DB read errors
                }
            }

            $site['pageviews'] = $views;
            $site['visitors'] = $visitors;
            $site['visitors_7d'] = $visitors7d;
            $site['sparkline'] = array_values($sparklineMap);
            $site['live_visitors'] = $live;
            $site['last_active'] = $lastActive;
        }

        return $sites;
    }

    public static function saveSites(array $sites): void
    {
        $sitesFile = self::getSitesFilePath();
        file_put_contents($sitesFile, json_encode($sites, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function normalizeHost(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }
        $parsed = parse_url(str_contains($value, '://') ? $value : '//' . $value, PHP_URL_HOST);
        $value = $parsed ?: preg_replace('#^https?://#', '', $value);
        $value = preg_replace('#/.*$#', '', (string) $value);
        return trim((string) $value, '.');
    }

    public static function trackingSite(string $siteId): ?array
    {
        $cleanId = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower($siteId));
        foreach (self::getAvailableSites() as $site) {
            if (($site['id'] ?? '') === $cleanId) {
                return $site;
            }
        }
        return null;
    }

    /** Public sites can be browsed read-only by anonymous visitors (live demo). */
    public static function isPublicSite(?string $siteId): bool
    {
        return self::getPublicSite($siteId) !== null;
    }

    /** Returns the requested public site, or the first public site when no ID is given. */
    public static function getPublicSite(?string $siteId = null): ?array
    {
        $cleanId = $siteId === null ? null : preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower($siteId));
        foreach (self::getAvailableSites() as $site) {
            if (empty($site['is_public'])) {
                continue;
            }
            if ($cleanId === null || ($site['id'] ?? '') === $cleanId) {
                return $site;
            }
        }
        return null;
    }

    /** Strips tracking secrets and private configuration before exposing a site to guests. */
    public static function guestSiteView(array $site): array
    {
        return array_diff_key($site, array_flip(['write_key', 'allowed_domains', 'internal_ips', 'retention_days']));
    }

    public static function trackingSnippet(array $site, string $scriptUrl): string
    {
        return '<script defer src="' . htmlspecialchars($scriptUrl, ENT_QUOTES) . '" data-site-id="' . htmlspecialchars($site['id'], ENT_QUOTES) . '" data-site-key="' . htmlspecialchars($site['write_key'], ENT_QUOTES) . '" data-privacy-mode="strict"></script>';
    }

    public static function updateSiteConfig(string $siteId, array $input): array
    {
        $cleanId = self::sanitizeSiteId($siteId);
        $sites = self::getAvailableSites();
        foreach ($sites as &$site) {
            if ($site['id'] !== $cleanId) {
                continue;
            }
            if (array_key_exists('domain', $input)) {
                $site['domain'] = trim((string) $input['domain']);
            }
            if (array_key_exists('allowed_domains', $input)) {
                $raw = is_array($input['allowed_domains']) ? $input['allowed_domains'] : explode(',', (string) $input['allowed_domains']);
                $site['allowed_domains'] = array_values(array_unique(array_filter(array_map([self::class, 'normalizeHost'], $raw))));
            }
            if (array_key_exists('internal_ips', $input)) {
                $raw = is_array($input['internal_ips']) ? $input['internal_ips'] : preg_split('/[\s,]+/', (string) $input['internal_ips']);
                $site['internal_ips'] = array_values(array_unique(array_filter(array_map('trim', $raw))));
            }
            if (array_key_exists('retention_days', $input)) {
                $site['retention_days'] = max(1, min(760, (int) $input['retention_days']));
            }
            if (array_key_exists('is_public', $input)) {
                $site['is_public'] = filter_var($input['is_public'], FILTER_VALIDATE_BOOLEAN);
            }
            if (!empty($input['rotate_key'])) {
                $site['write_key'] = bin2hex(random_bytes(24));
            }
            self::saveSites($sites);
            return $site;
        }
        throw new InvalidArgumentException('Website not found.');
    }

    public static function createSite(string $siteId, string $name, string $domain = ''): array
    {
        $cleanId = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower(trim($siteId)));
        if (empty($cleanId)) {
            throw new InvalidArgumentException("Site ID must contain only letters, numbers, hyphens or underscores.");
        }

        $sites = self::getAvailableSites();
        foreach ($sites as $s) {
            if ($s['id'] === $cleanId) {
                throw new RuntimeException("A website with ID '{$cleanId}' already exists.");
            }
        }

        // Create the site's storage schema in the selected connector.
        self::openConnection($cleanId);

        $newSite = [
            'id' => $cleanId,
            'name' => trim($name) ?: $cleanId,
            'domain' => trim($domain),
            'allowed_domains' => array_values(array_filter([self::normalizeHost($domain)])),
            'internal_ips' => [],
            'retention_days' => 395,
            'write_key' => bin2hex(random_bytes(24)),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];

        $sites[] = $newSite;
        self::saveSites($sites);

        return $newSite;
    }

    public static function deleteSite(string $siteId): bool
    {
        $cleanId = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower(trim($siteId)));
        if (empty($cleanId)) {
            throw new InvalidArgumentException("Invalid site ID.");
        }

        // Close connection if cached
        if (isset(self::$instances[$cleanId])) {
            self::$instances[$cleanId]->close();
            unset(self::$instances[$cleanId]);
        }

        // A remote connector is shared by all sites: deletion removes only the
        // registry entry, never another site's analytics tables.
        if (self::getDatabaseConfig()['driver'] === 'sqlite') {
            $dataDir = self::getDataDir();
            $dbFile = "{$dataDir}/{$cleanId}.db";
            if (file_exists($dbFile)) {
                @unlink($dbFile);
            }
            if (file_exists("{$dbFile}-wal")) {
                @unlink("{$dbFile}-wal");
            }
            if (file_exists("{$dbFile}-shm")) {
                @unlink("{$dbFile}-shm");
            }
        }

        // Update sites.json
        $sites = self::getAvailableSites();
        $filtered = array_values(array_filter($sites, fn($s) => $s['id'] !== $cleanId));
        self::saveSites($filtered);

        return true;
    }

    public static function getConnection(?string $siteId = null): DatabaseConnection
    {
        $cleanSite = self::sanitizeSiteId($siteId);

        if (!isset(self::$instances[$cleanSite])) {
            $db = self::openConnection($cleanSite);

            // Auto-migrate if visitor_id column doesn't exist
            if (!$db->isMysql()) {
                $cols = $db->query("PRAGMA table_info(user_activity)");
                $hasVisitorId = false;
                while (($col = $cols?->fetchArray(SQLITE3_ASSOC)) !== false) {
                    if ($col['name'] === 'visitor_id') {
                        $hasVisitorId = true;
                    }
                }
                if (!$hasVisitorId) {
                    @$db->exec("ALTER TABLE user_activity ADD COLUMN visitor_id TEXT");
                    @$db->exec("UPDATE user_activity SET visitor_id = session_id WHERE visitor_id IS NULL");
                }
            }

            self::$instances[$cleanSite] = $db;
            self::runRetention($db, self::trackingSite($cleanSite) ?: []);
        }

        return self::$instances[$cleanSite];
    }

    private static function openConnection(string $siteId): DatabaseConnection
    {
        $config = self::getDatabaseConfig();
        if ($config['driver'] === 'sqlite') {
            $db = new SQLite3(self::getDataDir() . "/{$siteId}.db");
            $db->busyTimeout(5000);
            $db->exec('PRAGMA journal_mode = WAL;');
            $connection = new DatabaseConnection($db, 'sqlite', $siteId);
            self::ensureSchema($connection);
            return $connection;
        }
        self::testDatabaseConfig($config);
        $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4", $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5, PDO::ATTR_EMULATE_PREPARES => false]);
        $connection = new DatabaseConnection($pdo, $config['driver'], $siteId);
        self::ensureSchema($connection);
        return $connection;
    }

    private static function ensureSchema(DatabaseConnection $db): void
    {
        if ($db->isMysql()) {
            $db->exec("CREATE TABLE IF NOT EXISTS user_activity (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, session_id VARCHAR(64) NOT NULL, visitor_id VARCHAR(64) NULL, action JSON NOT NULL, timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_ua_timestamp (timestamp), INDEX idx_ua_session (session_id), INDEX idx_ua_visitor (visitor_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS bot_activity (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, reason VARCHAR(100) NOT NULL, user_agent VARCHAR(500) NULL, origin VARCHAR(255) NULL, ip_hash VARCHAR(64) NULL, timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_bot_timestamp (timestamp)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (bucket VARCHAR(20) NOT NULL, ip_hash VARCHAR(64) NOT NULL, count INT NOT NULL DEFAULT 0, PRIMARY KEY(bucket, ip_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            return;
        }
        $db->exec("CREATE TABLE IF NOT EXISTS user_activity (id INTEGER PRIMARY KEY AUTOINCREMENT, session_id TEXT NOT NULL, visitor_id TEXT, action TEXT NOT NULL, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $db->exec("CREATE TABLE IF NOT EXISTS bot_activity (id INTEGER PRIMARY KEY AUTOINCREMENT, reason TEXT NOT NULL, user_agent TEXT, origin TEXT, ip_hash TEXT, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (bucket TEXT NOT NULL, ip_hash TEXT NOT NULL, count INTEGER NOT NULL DEFAULT 0, PRIMARY KEY(bucket, ip_hash))");
        $db->exec('CREATE INDEX IF NOT EXISTS idx_ua_timestamp ON user_activity(timestamp)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_ua_session ON user_activity(session_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_ua_visitor ON user_activity(visitor_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_bot_timestamp ON bot_activity(timestamp)');
    }

    private static function runRetention(DatabaseConnection $db, array $site): void
    {
        $days = max(1, min(760, (int) ($site['retention_days'] ?? 395)));
        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * 86400);
        $stmt = $db->prepare('DELETE FROM user_activity WHERE timestamp < :cutoff');
        $stmt->bindValue(':cutoff', $cutoff, SQLITE3_TEXT);
        $stmt->execute();
        $stmt = $db->prepare('DELETE FROM bot_activity WHERE timestamp < :cutoff');
        $stmt->bindValue(':cutoff', $cutoff, SQLITE3_TEXT);
        $stmt->execute();
        $db->exec("DELETE FROM rate_limits WHERE bucket < '" . gmdate('YmdHi', time() - 7200) . "'");
    }
}
