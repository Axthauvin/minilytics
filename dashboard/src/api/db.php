<?php
declare(strict_types=1);

/**
 * Minilytics Database Connection Helper
 * Supports isolated SQLite databases per website inside the data/ directory.
 */

class Database {
    private static array $instances = [];

    public static function getDataDir(): string {
        $dir = dirname(__DIR__, 3) . '/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    public static function getSitesFilePath(): string {
        return self::getDataDir() . '/sites.json';
    }

    public static function sanitizeSiteId(?string $siteId): string {
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

    public static function getAvailableSites(): array {
        $dataDir = self::getDataDir();
        $sitesFile = self::getSitesFilePath();
        $sites = [];

        if (file_exists($sitesFile)) {
            $sites = json_decode(file_get_contents($sitesFile), true) ?: [];
        }

        // Auto-discover any .db files inside data/
        $dbFiles = glob("{$dataDir}/*.db") ?: [];
        $knownIds = array_column($sites, 'id');

        foreach ($dbFiles as $file) {
            $base = basename($file, '.db');
            if ($base === 'auth') continue;
            if (!in_array($base, $knownIds)) {
                $sites[] = [
                    'id' => $base,
                    'name' => ucwords(str_replace(['_', '-'], ' ', $base)),
                    'domain' => '',
                    'created_at' => gmdate('Y-m-d H:i:s', filemtime($file))
                ];
            }
        }

        return $sites;
    }

    public static function getSitesWithStats(): array {
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

            if (file_exists($dbPath)) {
                try {
                    $db = self::getConnection($site['id']);
                    $views = (int)$db->querySingle("SELECT COUNT(*) FROM user_activity WHERE json_extract(action, '$.name') = 'pageview'");
                    $visitors = (int)$db->querySingle("SELECT COUNT(DISTINCT session_id) FROM user_activity");
                    $visitors7d = (int)$db->querySingle("SELECT COUNT(DISTINCT session_id) FROM user_activity WHERE timestamp >= '{$sevenDaysAgo}'");
                    $liveThresh = gmdate('Y-m-d H:i:s', time() - 300);
                    $live = (int)$db->querySingle("SELECT COUNT(DISTINCT session_id) FROM user_activity WHERE timestamp >= '{$liveThresh}'");
                    $lastActive = $db->querySingle("SELECT MAX(timestamp) FROM user_activity");

                    // Real daily visitors for the 7 days
                    $spStmt = $db->prepare("SELECT strftime('%Y-%m-%d', timestamp) as day, COUNT(DISTINCT session_id) as v 
                                           FROM user_activity 
                                           WHERE timestamp >= :start_date 
                                           GROUP BY day");
                    $spStmt->bindValue(':start_date', $sevenDaysAgo, SQLITE3_TEXT);
                    $spRes = $spStmt->execute();
                    while ($spRow = $spRes->fetchArray(SQLITE3_ASSOC)) {
                        if (isset($sparklineMap[$spRow['day']])) {
                            $sparklineMap[$spRow['day']] = (int)$spRow['v'];
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

    public static function saveSites(array $sites): void {
        $sitesFile = self::getSitesFilePath();
        file_put_contents($sitesFile, json_encode($sites, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function createSite(string $siteId, string $name, string $domain = ''): array {
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

        // Initialize dedicated SQLite database for this site
        $dataDir = self::getDataDir();
        $dbPath = "{$dataDir}/{$cleanId}.db";
        $db = new SQLite3($dbPath);
        $db->busyTimeout(5000);
        $db->exec('PRAGMA journal_mode = WAL;');
        $db->exec("CREATE TABLE IF NOT EXISTS user_activity (
            id INTEGER PRIMARY KEY AUTOINCREMENT, 
            session_id TEXT NOT NULL, 
            visitor_id TEXT,
            action TEXT NOT NULL, 
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $newSite = [
            'id' => $cleanId,
            'name' => trim($name) ?: $cleanId,
            'domain' => trim($domain),
            'created_at' => gmdate('Y-m-d H:i:s')
        ];

        $sites[] = $newSite;
        self::saveSites($sites);

        return $newSite;
    }

    public static function deleteSite(string $siteId): bool {
        $cleanId = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower(trim($siteId)));
        if (empty($cleanId)) {
            throw new InvalidArgumentException("Invalid site ID.");
        }

        // Close connection if cached
        if (isset(self::$instances[$cleanId])) {
            self::$instances[$cleanId]->close();
            unset(self::$instances[$cleanId]);
        }

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

        // Update sites.json
        $sites = self::getAvailableSites();
        $filtered = array_values(array_filter($sites, fn($s) => $s['id'] !== $cleanId));
        self::saveSites($filtered);

        return true;
    }

    public static function getConnection(?string $siteId = null): SQLite3 {
        $cleanSite = self::sanitizeSiteId($siteId);

        if (!isset(self::$instances[$cleanSite])) {
            $dataDir = self::getDataDir();
            $dbPath = "{$dataDir}/{$cleanSite}.db";

            $db = new SQLite3($dbPath);
            $db->busyTimeout(5000);
            $db->exec('PRAGMA journal_mode = WAL;');

            $db->exec("CREATE TABLE IF NOT EXISTS user_activity (
                id INTEGER PRIMARY KEY AUTOINCREMENT, 
                session_id TEXT NOT NULL, 
                visitor_id TEXT,
                action TEXT NOT NULL, 
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            // Auto-migrate if visitor_id column doesn't exist
            $cols = $db->query("PRAGMA table_info(user_activity)");
            $hasVisitorId = false;
            while ($col = $cols->fetchArray(SQLITE3_ASSOC)) {
                if ($col['name'] === 'visitor_id') $hasVisitorId = true;
            }
            if (!$hasVisitorId) {
                @$db->exec("ALTER TABLE user_activity ADD COLUMN visitor_id TEXT");
                @$db->exec("UPDATE user_activity SET visitor_id = session_id WHERE visitor_id IS NULL");
            }

            self::$instances[$cleanSite] = $db;
        }

        return self::$instances[$cleanSite];
    }
}
