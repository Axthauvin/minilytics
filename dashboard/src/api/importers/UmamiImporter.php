<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseImporter.php';
require_once __DIR__ . '/../db.php';

/**
 * Umami Analytics Importer
 * Parses Umami export archives (.zip) or CSV directories (website_event.csv, event_data.csv).
 */
class UmamiImporter extends BaseImporter {
    /** Uploaded files use PHP temporary names without a .zip extension on Windows. */
    private function isZipArchiveFile(string $path): bool {
        if (!is_file($path)) return false;
        $handle = @fopen($path, 'rb');
        if (!$handle) return false;
        $signature = fread($handle, 4);
        fclose($handle);
        return in_array($signature, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true);
    }
    public function getId(): string {
        return 'umami';
    }

    public function getName(): string {
        return 'Umami Analytics';
    }

    public function getDescription(): string {
        return 'Import website events, pageviews and custom event data from an Umami export (.zip or CSV files).';
    }

    public function getStatus(): string {
        return 'ready';
    }

    public function getBadge(): string {
        return 'Available';
    }

    public function getIcon(): string {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM16.5 16.5C15.26 17.74 13.63 18.5 12 18.5C10.37 18.5 8.74 17.74 7.5 16.5L12 12L16.5 16.5Z" fill="currentColor"/></svg>';
    }

    public function isAvailable(): bool {
        return true;
    }

    public function getSupportedFormats(): array {
        return ['.zip', '.csv'];
    }

    /**
     * Inspect a source to discover CSV files
     */
    public function findFiles(string $dir): array {
        $files = [
            'website_event' => null,
            'event_data' => null,
            'session_data' => null
        ];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $filename = strtolower($item->getFilename());
                if (str_contains($filename, 'website_event') && str_ends_with($filename, '.csv')) {
                    $files['website_event'] = $item->getPathname();
                } elseif (str_contains($filename, 'event_data') && str_ends_with($filename, '.csv')) {
                    $files['event_data'] = $item->getPathname();
                } elseif (str_contains($filename, 'session_data') && str_ends_with($filename, '.csv')) {
                    $files['session_data'] = $item->getPathname();
                }
            }
        }

        return $files;
    }

    /**
     * Quick metadata inspection before importing
     */
    public function inspect(string $sourcePath): array {
        $tempDir = null;
        $workDir = $sourcePath;

        if ($this->isZipArchiveFile($sourcePath)) {
            $tempDir = sys_get_temp_dir() . '/minilytics_inspect_' . uniqid();
            $this->extractZip($sourcePath, $tempDir);
            $workDir = $tempDir;
        }

        try {
            $found = $this->findFiles($workDir);
            if (empty($found['website_event'])) {
                throw new RuntimeException("Could not find 'website_event.csv' inside the export.");
            }

            $detectedHostnames = [];
            $sampleRows = 0;
            $handle = fopen($found['website_event'], 'r');
            if ($handle) {
                $header = fgetcsv($handle);
                $hostIdx = array_search('hostname', $header ?: []);
                while (($row = fgetcsv($handle)) !== false && $sampleRows < 500) {
                    if ($hostIdx !== false && !empty($row[$hostIdx]) && $row[$hostIdx] !== '\N') {
                        $h = trim($row[$hostIdx]);
                        $detectedHostnames[$h] = ($detectedHostnames[$h] ?? 0) + 1;
                    }
                    $sampleRows++;
                }
                fclose($handle);
            }

            arsort($detectedHostnames);
            $primaryHost = !empty($detectedHostnames) ? array_key_first($detectedHostnames) : '';

            return [
                'has_website_event' => !empty($found['website_event']),
                'has_event_data' => !empty($found['event_data']),
                'has_session_data' => !empty($found['session_data']),
                'detected_host' => $primaryHost,
                'suggested_site_id' => preg_replace('/[^a-z0-9_\-]/', '', strtolower(explode('.', $primaryHost)[0] ?: 'imported_site')),
                'suggested_name' => ucwords(str_replace(['.', '-', '_'], ' ', $primaryHost ?: 'Imported Site'))
            ];
        } finally {
            if ($tempDir) {
                $this->removeDirectory($tempDir);
            }
        }
    }

    /**
     * Import data into Minilytics SQLite database
     */
    public function import(string $sourcePath, string $siteId, array $options = []): array {
        $cleanSiteId = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower(trim($siteId)));
        if ($cleanSiteId === '') {
            throw new InvalidArgumentException('A valid website identifier is required for import.');
        }
        $tempDir = null;
        $workDir = $sourcePath;

        if (is_file($sourcePath)) {
            $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
            if ($ext === 'zip' || $this->isZipArchiveFile($sourcePath)) {
                $tempDir = sys_get_temp_dir() . '/minilytics_import_' . uniqid();
                $this->extractZip($sourcePath, $tempDir);
                $workDir = $tempDir;
            } elseif ($ext === 'csv') {
                // Single CSV file passed directly
                $workDir = dirname($sourcePath);
            }
        }

        try {
            $csvFiles = $this->findFiles($workDir);
            if (empty($csvFiles['website_event'])) {
                throw new RuntimeException("Could not find 'website_event.csv' inside the export.");
            }

            // 1. Index event_data.csv if available
            $eventDataMap = [];
            if (!empty($csvFiles['event_data']) && file_exists($csvFiles['event_data'])) {
                $edHandle = fopen($csvFiles['event_data'], 'r');
                if ($edHandle) {
                    $edHeader = fgetcsv($edHandle);
                    $eidIdx = array_search('event_id', $edHeader ?: []);
                    $keyIdx = array_search('data_key', $edHeader ?: []);
                    $strIdx = array_search('string_value', $edHeader ?: []);
                    $numIdx = array_search('number_value', $edHeader ?: []);
                    $typeIdx = array_search('data_type', $edHeader ?: []);

                    if ($eidIdx !== false && $keyIdx !== false) {
                        while (($row = fgetcsv($edHandle)) !== false) {
                            $eid = $row[$eidIdx] ?? '';
                            $key = $row[$keyIdx] ?? '';
                            if ($eid === '' || $key === '') continue;

                            $numVal = ($numIdx !== false && isset($row[$numIdx]) && $row[$numIdx] !== '\N') ? $row[$numIdx] : null;
                            $strVal = ($strIdx !== false && isset($row[$strIdx]) && $row[$strIdx] !== '\N') ? $row[$strIdx] : null;
                            $typeVal = ($typeIdx !== false && isset($row[$typeIdx])) ? $row[$typeIdx] : '1';

                            $val = $strVal;
                            if ($typeVal === '2' && $numVal !== null) {
                                $val = (str_contains($numVal, '.')) ? (float)$numVal : (int)$numVal;
                            } elseif ($typeVal === '3') {
                                $val = ($strVal === 'true' || $strVal === '1');
                            }

                            if (!isset($eventDataMap[$eid])) {
                                $eventDataMap[$eid] = [];
                            }
                            $eventDataMap[$eid][$key] = $val;
                        }
                    }
                    fclose($edHandle);
                }
            }

            // 2. Ensure target site and DB exist
            $sites = Database::getAvailableSites();
            $siteExists = false;
            foreach ($sites as $s) {
                if ($s['id'] === $cleanSiteId) {
                    $siteExists = true;
                    break;
                }
            }

            $siteName = $options['name'] ?? ucwords(str_replace(['_', '-'], ' ', $cleanSiteId));
            $siteDomain = $options['domain'] ?? '';

            if (!$siteExists) {
                $sites[] = [
                    'id' => $cleanSiteId,
                    'name' => $siteName,
                    'domain' => $siteDomain,
                    'created_at' => gmdate('Y-m-d H:i:s')
                ];
                Database::saveSites($sites);
            }

            $db = Database::getConnection($cleanSiteId);
            if (!$db->isMysql()) { $db->exec('PRAGMA synchronous = OFF;'); $db->exec('PRAGMA journal_mode = MEMORY;'); }

            // Ensure table and performance indexes
            if (!$db->isMysql()) $db->exec("CREATE TABLE IF NOT EXISTS user_activity (
                id INTEGER PRIMARY KEY AUTOINCREMENT, 
                session_id TEXT NOT NULL, 
                visitor_id TEXT,
                action TEXT NOT NULL, 
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            if (!$db->isMysql()) {
                $db->exec("CREATE INDEX IF NOT EXISTS idx_ua_timestamp ON user_activity(timestamp)");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_ua_session ON user_activity(session_id)");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_ua_visitor ON user_activity(visitor_id)");
            }

            // 3. Parse and Insert website_event.csv in a single atomic transaction
            $weHandle = fopen($csvFiles['website_event'], 'r');
            if (!$weHandle) {
                throw new RuntimeException("Unable to open website_event.csv for reading.");
            }

            $weHeader = fgetcsv($weHandle);
            if (!$weHeader) {
                fclose($weHandle);
                throw new RuntimeException("website_event.csv is empty.");
            }

            // Map header indexes
            $colMap = [];
            foreach ($weHeader as $idx => $name) {
                $colMap[trim($name)] = $idx;
            }

            $totalImported = 0;
            $pageviewCount = 0;
            $customEventCount = 0;
            $distinctSessions = [];
            $hostnamesMap = [];
            $minTimestamp = '9999-99-99 99:99:99';
            $maxTimestamp = '0000-00-00 00:00:00';

            $db->exec($db->isMysql() ? 'START TRANSACTION' : 'BEGIN TRANSACTION');

            $insertStmt = $db->prepare("INSERT INTO user_activity (session_id, visitor_id, action, timestamp) VALUES (:session_id, :visitor_id, :action, :timestamp)");

            while (($row = fgetcsv($weHandle)) !== false) {
                $get = fn(string $col) => isset($colMap[$col], $row[$colMap[$col]]) ? $this->cleanVal($row[$colMap[$col]]) : null;

                $sessionId = $get('session_id') ?: ('sess_' . uniqid());
                $visitorId = $get('distinct_id') ?: $get('session_id') ?: $sessionId;
                $eventId = $get('event_id');
                $eventType = (string)($get('event_type') ?? '1');
                $rawEventName = $get('event_name');
                $hostname = $get('hostname') ?: '';
                $urlPath = $get('url_path') ?: '/';
                $urlQuery = $get('url_query') ?: '';
                $createdAt = $get('created_at') ?: gmdate('Y-m-d H:i:s');

                if ($hostname !== '') {
                    $hostnamesMap[$hostname] = ($hostnamesMap[$hostname] ?? 0) + 1;
                }

                if ($createdAt < $minTimestamp) $minTimestamp = $createdAt;
                if ($createdAt > $maxTimestamp) $maxTimestamp = $createdAt;

                $distinctSessions[$sessionId] = true;

                // Determine action name
                $isPageview = ($eventType === '1' || empty($rawEventName));
                $actionName = $isPageview ? 'pageview' : $rawEventName;

                if ($isPageview) {
                    $pageviewCount++;
                } else {
                    $customEventCount++;
                }

                // Construct Referrer
                $refDomain = $get('referrer_domain');
                $refPath = $get('referrer_path') ?: '';
                $referrer = null;
                if (!empty($refDomain)) {
                    $referrer = str_starts_with($refDomain, 'http') ? $refDomain : "https://{$refDomain}" . ($refPath ? '/' . ltrim($refPath, '/') : '');
                }

                // Full URL
                $fullUrl = null;
                if (!empty($hostname)) {
                    $fullUrl = "https://{$hostname}{$urlPath}" . (!empty($urlQuery) ? "?{$urlQuery}" : '');
                }

                // Country & Geo
                $countryCode = strtoupper((string)($get('country') ?: ''));
                $countryName = $countryCode ? $this->getCountryName($countryCode) : null;

                // Base structured data object
                $data = [
                    'path' => $urlPath,
                    'title' => $get('page_title'),
                    'url' => $fullUrl,
                    'referrer' => $referrer,
                    'hostname' => $hostname,
                    'search' => $urlQuery ?: null,
                    'hash' => null,
                    'screen' => $get('screen'),
                    'viewport' => $get('screen'),
                    'device' => $this->normalizeDevice($get('device')),
                    'language' => $get('language') ? explode('-', (string)$get('language'))[0] : null,
                    'browser' => $this->normalizeBrowser($get('browser')),
                    'os' => $this->normalizeOs($get('os')),
                    'country_code' => $countryCode ?: null,
                    'country' => $countryName,
                    'region' => $get('region'),
                    'city' => $get('city')
                ];

                // UTM tracking parameters
                $utms = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
                foreach ($utms as $utm) {
                    $val = $get($utm);
                    if (!empty($val)) {
                        $data[$utm] = $val;
                    }
                }

                // Merge custom parameters from event_data.csv if any
                if ($eventId && isset($eventDataMap[$eventId])) {
                    foreach ($eventDataMap[$eventId] as $k => $v) {
                        $data[$k] = $v;
                    }
                }

                $action = [
                    'site_id' => $cleanSiteId,
                    'session_id' => $sessionId,
                    'visitor_id' => $visitorId,
                    'name' => $actionName,
                    'data' => $data
                ];

                $insertStmt->bindValue(':session_id', $sessionId, SQLITE3_TEXT);
                $insertStmt->bindValue(':visitor_id', $visitorId, SQLITE3_TEXT);
                $insertStmt->bindValue(':action', json_encode($action, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
                $insertStmt->bindValue(':timestamp', $createdAt, SQLITE3_TEXT);
                $insertStmt->execute();

                $totalImported++;
            }

            $db->exec('COMMIT;');
            fclose($weHandle);

            // Revert SQLite settings to standard WAL
            if (!$db->isMysql()) { $db->exec('PRAGMA synchronous = NORMAL;'); $db->exec('PRAGMA journal_mode = WAL;'); }

            // If domain was not set, update sites.json with primary hostname
            if (empty($siteDomain) && !empty($hostnamesMap)) {
                arsort($hostnamesMap);
                $detectedHost = array_key_first($hostnamesMap);
                $sites = Database::getAvailableSites();
                foreach ($sites as &$s) {
                    if ($s['id'] === $cleanSiteId && empty($s['domain'])) {
                        $s['domain'] = $detectedHost;
                        break;
                    }
                }
                Database::saveSites($sites);
                $siteDomain = $detectedHost;
            }

            return [
                'success' => true,
                'provider' => 'umami',
                'site_id' => $cleanSiteId,
                'site_name' => $siteName,
                'site_domain' => $siteDomain,
                'total_imported' => $totalImported,
                'pageviews' => $pageviewCount,
                'custom_events' => $customEventCount,
                'sessions' => count($distinctSessions),
                'date_start' => ($totalImported > 0) ? $minTimestamp : null,
                'date_end' => ($totalImported > 0) ? $maxTimestamp : null,
                'detected_hostnames' => array_keys($hostnamesMap)
            ];

        } finally {
            if ($tempDir) {
                $this->removeDirectory($tempDir);
            }
        }
    }
}
