<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
require_once __DIR__ . '/auth.php';
Auth::requireLogin();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/filters.php';

$formatDurationLabel = static function (int $seconds): string {
    $seconds = max(0, $seconds);
    if ($seconds < 60) {
        return "{$seconds}s";
    }

    $days = intdiv($seconds, 86400);
    $seconds %= 86400;
    $hours = intdiv($seconds, 3600);
    $seconds %= 3600;
    $minutes = intdiv($seconds, 60);
    $seconds %= 60;

    $parts = [];
    if ($days > 0) $parts[] = "{$days}d";
    if ($hours > 0 || $days > 0) $parts[] = "{$hours}h";
    if ($minutes > 0 || $hours > 0 || $days > 0) $parts[] = "{$minutes}m";
    if ($seconds > 0 || empty($parts)) $parts[] = "{$seconds}s";

    return implode(' ', $parts);
};

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    $siteId = $_GET['site_id'] ?? $_GET['site'] ?? null;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'DELETE') {
        Auth::requireAdmin();
        $input = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $siteId = $input['site_id'] ?? $siteId;
        $sessionId = trim((string)($input['session_id'] ?? $_GET['session_id'] ?? ''));
        if ($sessionId === '' || strlen($sessionId) > 255) throw new InvalidArgumentException('A valid session ID is required.');
        $cleanSite = Database::sanitizeSiteId($siteId);
        $db = Database::getConnection($cleanSite);
        $delete = $db->prepare('DELETE FROM user_activity WHERE session_id = :session_id');
        $delete->bindValue(':session_id', $sessionId, SQLITE3_TEXT);
        $delete->execute();
        $deleted = $db->changes();
        if ($deleted === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Session not found']);
            exit;
        }
        echo json_encode(['success' => true, 'deleted_events' => $deleted]);
        exit;
    }
    $cleanSite = Database::sanitizeSiteId($siteId);
    $db = Database::getConnection($cleanSite);

    $specificSessionId = trim($_GET['session_id'] ?? '');

    // 1. Single session details drill-down
    if ($specificSessionId !== '') {
        $detailSql = "SELECT id, session_id, visitor_id, action, timestamp 
                      FROM user_activity 
                      WHERE session_id = :session_id 
                      ORDER BY timestamp ASC, id ASC";
        $dStmt = $db->prepare($detailSql);
        $dStmt->bindValue(':session_id', $specificSessionId, SQLITE3_TEXT);
        $res = $dStmt->execute();

        $events = [];
        $foundEvents = false;
        $firstTime = null;
        $lastTime = null;
        $entryPage = null;
        $referrer = null;
        $browser = 'Other';
        $os = 'Other';
        $device = 'Desktop';
        $country = 'Unknown';
        $countryCode = 'UN';
        $city = '';
        $trackingMode = 'unknown';

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $foundEvents = true;
            $act = json_decode($row['action'], true) ?: [];
            $ts = strtotime($row['timestamp'] . ' UTC');

            if ($firstTime === null) {
                $firstTime = $ts;
                $data = $act['data'] ?? [];
                if (!empty($data['path']))
                    $entryPage = $data['path'];
                if (!empty($data['referrer']))
                    $referrer = $data['referrer'];
                if (!empty($data['browser']))
                    $browser = $data['browser'];
                if (!empty($data['os']))
                    $os = $data['os'];
                if (!empty($data['device']))
                    $device = $data['device'];
                if (!empty($data['country']))
                    $country = $data['country'];
                if (!empty($data['country_code']))
                    $countryCode = $data['country_code'];
                if (!empty($data['city']))
                    $city = $data['city'];
                if (!empty($data['_ml_tracking_mode']))
                    $trackingMode = $data['_ml_tracking_mode'];
            }
            $lastTime = $ts;

            $offsetSec = $ts - $firstTime;
            $offsetLabel = '+' . $formatDurationLabel($offsetSec);

            // Engagement is an internal timing signal, not a visitor-facing event.
            if (($act['name'] ?? '') === '_ml_engaged') continue;
            $events[] = [
                'id' => (int) $row['id'],
                'name' => $act['name'] ?? 'unknown',
                'site_id' => $act['site_id'] ?? $cleanSite,
                'data' => $act['data'] ?? [],
                'timestamp' => $row['timestamp'],
                'offset_label' => $offsetLabel,
                'offset_seconds' => $offsetSec
            ];
        }

        if (!$foundEvents) {
            http_response_code(404);
            echo json_encode(['error' => 'Session not found']);
            exit;
        }

        $durationSec = max(0, $lastTime - $firstTime);
        $durationLabel = $formatDurationLabel($durationSec);

        echo json_encode([
            'success' => true,
            'session' => [
                'session_id' => $specificSessionId,
                'started_at' => gmdate('Y-m-d H:i:s', $firstTime),
                'ended_at' => gmdate('Y-m-d H:i:s', $lastTime),
                'duration_seconds' => $durationSec,
                'duration_label' => $durationLabel,
                'event_count' => count($events),
                'entry_page' => $entryPage ?: '/',
                'referrer' => $referrer ?: 'Direct',
                'browser' => $browser,
                'os' => $os,
                'device' => $device,
                'country' => $country,
                'country_code' => $countryCode,
                'city' => $city,
                'tracking_mode' => $trackingMode,
                'avatar_url' => "https://api.dicebear.com/10.x/glyphs/svg?seed=" . rawurlencode($specificSessionId),
                'events' => $events
            ]
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // 2. Listing sessions with aggregations
    $range = $_GET['range'] ?? '7d';
    $search = trim($_GET['search'] ?? '');
    $day = trim($_GET['day'] ?? $_GET['date'] ?? '');
    $eventName = trim($_GET['event_name'] ?? $_GET['event'] ?? '');
    $limit = max(1, min(100, (int) ($_GET['limit'] ?? 25)));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    $now = time();
    $from = $_GET['from'] ?? $_GET['start'] ?? $_GET['start_date'] ?? null;
    $to = $_GET['to'] ?? $_GET['end'] ?? $_GET['end_date'] ?? null;

    if ($day !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        $startDateStr = $day . ' 00:00:00';
        $endDateStr = $day . ' 23:59:59';
    } elseif ($range === 'custom' || (!empty($from) && !empty($to))) {
        $range = 'custom';
        $startUnix = !empty($from) ? (strtotime($from . ' 00:00:00 UTC') ?: ($now - 30 * 86400)) : ($now - 30 * 86400);
        $endUnix = !empty($to) ? (strtotime($to . ' 23:59:59 UTC') ?: $now) : $now;
        if ($startUnix > $endUnix) {
            [$startUnix, $endUnix] = [$endUnix, $startUnix];
        }
        $startDateStr = gmdate('Y-m-d H:i:s', $startUnix);
        $endDateStr = gmdate('Y-m-d H:i:s', $endUnix);
    } else {
        $endUnix = $now;
        $startUnix = match ($range) {
            'today' => strtotime('today midnight'),
            '24h' => $now - 86400,
            '7d' => $now - (7 * 86400),
            '30d' => $now - (30 * 86400),
            '90d' => $now - (90 * 86400),
            '6m', '180d' => $now - (180 * 86400),
            'all' => 0,
            default => $now - (7 * 86400)
        };
        $startDateStr = gmdate('Y-m-d H:i:s', $startUnix);
        $endDateStr = gmdate('Y-m-d H:i:s', $endUnix);
    }

    $where = ["timestamp >= :start_date AND timestamp <= :end_date"];
    $params = [
        ':start_date' => $startDateStr,
        ':end_date' => $endDateStr,
    ];

    if ($eventName !== '' && $eventName !== 'all') {
        $where[] = "session_id IN (
            SELECT DISTINCT sub_ua.session_id 
            FROM user_activity sub_ua 
            WHERE json_extract(sub_ua.action, '$.name') = :event_name
              AND sub_ua.timestamp >= :start_date AND sub_ua.timestamp <= :end_date
        )";
        $params[':event_name'] = $eventName;
    }

    if ($search !== '') {
        $where[] = "(session_id LIKE :search OR action LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    // Overview filters (pages, referrers, environment, countries)
    $filterCondition = AnalyticsFilters::apply($db, AnalyticsFilters::fromRequest($_GET), $startDateStr, $endDateStr);
    if ($filterCondition !== '') {
        $where[] = preg_replace('/^\s*AND\s+/', '', $filterCondition);
    }

    $whereClause = implode(' AND ', $where);

    // Total distinct sessions count
    $cntSql = "SELECT COUNT(DISTINCT session_id) FROM user_activity WHERE {$whereClause}";
    $cntStmt = $db->prepare($cntSql);
    foreach ($params as $k => $v)
        $cntStmt->bindValue($k, $v, SQLITE3_TEXT);
    $totalSessions = (int) $cntStmt->execute()->fetchArray(SQLITE3_NUM)[0];

    // Aggregated sessions
    $sessSql = "
        SELECT 
            session_id,
            COALESCE(MAX(visitor_id), session_id) as visitor_id,
            MIN(timestamp) as started_at,
            MAX(timestamp) as last_active_at,
            SUM(CASE WHEN json_extract(action, '$.name') <> '_ml_engaged' THEN 1 ELSE 0 END) as event_count,
            (strftime('%s', MAX(timestamp)) - strftime('%s', MIN(timestamp))) as duration_seconds
        FROM user_activity
        WHERE {$whereClause}
        GROUP BY session_id
        ORDER BY started_at DESC, last_active_at DESC
        LIMIT :limit OFFSET :offset
    ";
    $sStmt = $db->prepare($sessSql);
    foreach ($params as $k => $v)
        $sStmt->bindValue($k, $v, SQLITE3_TEXT);
    $sStmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $sStmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
    $sRes = $sStmt->execute();

    $sessions = [];
    while ($sr = $sRes->fetchArray(SQLITE3_ASSOC)) {
        $sId = $sr['session_id'];
        $durSec = max(0, (int) $sr['duration_seconds']);
        $durLabel = $formatDurationLabel($durSec);

        // Fetch real metadata and journey flow for this session (up to 8 items)
        $flowStmt = $db->prepare("SELECT action, timestamp FROM user_activity WHERE session_id = :sid ORDER BY timestamp ASC, id ASC LIMIT 8");
        $flowStmt->bindValue(':sid', $sId, SQLITE3_TEXT);
        $flowRes = $flowStmt->execute();

        $flow = [];
        $entryPage = null;
        $referrer = null;
        $browser = 'Other';
        $os = 'Other';
        $device = 'Desktop';
        $country = 'Unknown';
        $countryCode = 'UN';
        $city = '';

        while ($fr = $flowRes->fetchArray(SQLITE3_ASSOC)) {
            $act = json_decode($fr['action'], true) ?: [];
            $name = $act['name'] ?? 'action';
            $data = $act['data'] ?? [];

            if ($entryPage === null) {
                if (!empty($data['path']))
                    $entryPage = $data['path'];
                if (!empty($data['referrer']))
                    $referrer = $data['referrer'];
                if (!empty($data['browser']))
                    $browser = $data['browser'];
                if (!empty($data['os']))
                    $os = $data['os'];
                if (!empty($data['device']))
                    $device = $data['device'];
                if (!empty($data['country']))
                    $country = $data['country'];
                if (!empty($data['country_code']))
                    $countryCode = $data['country_code'];
                if (!empty($data['city']))
                    $city = $data['city'];
            }

            if ($name === '_ml_engaged') continue;
            if ($name === 'pageview') {
                $p = $data['path'] ?? '/';
                $flow[] = ['type' => 'pageview', 'label' => $p];
            } else {
                $flow[] = ['type' => 'event', 'label' => $name];
            }
        }

        // Relative time ago calculation
        $startTs = strtotime($sr['started_at'] . ' UTC');
        $diff = time() - $startTs;
        if ($diff < 60)
            $timeAgo = 'just now';
        elseif ($diff < 3600)
            $timeAgo = floor($diff / 60) . 'm ago';
        elseif ($diff < 86400)
            $timeAgo = floor($diff / 3600) . 'h ago';
        else
            $timeAgo = floor($diff / 86400) . 'd ago';

        $sessions[] = [
            'session_id' => $sId,
            'visitor_id' => $sr['visitor_id'] ?? $sId,
            'started_at' => $sr['started_at'],
            'last_active_at' => $sr['last_active_at'],
            'time_ago' => $timeAgo,
            'duration_seconds' => $durSec,
            'duration_label' => $durLabel,
            'event_count' => (int) $sr['event_count'],
            'entry_page' => $entryPage ?: '/',
            'referrer' => $referrer ?: 'Direct',
            'browser' => $browser,
            'os' => $os,
            'device' => $device,
            'country' => $country,
            'country_code' => strtoupper($countryCode),
            'city' => $city,
            'avatar_url' => "https://api.dicebear.com/10.x/glyphs/svg?seed=" . rawurlencode($sId),
            'flow' => $flow
        ];
    }

    // Fetch distinct event names for the filter dropdown
    $evtTypesStmt = $db->prepare("
        SELECT json_extract(action, '$.name') as name, COUNT(*) as count 
        FROM user_activity 
        WHERE timestamp >= :start_date AND timestamp <= :end_date
        GROUP BY name 
        ORDER BY count DESC
    ");
    $evtTypesStmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
    $evtTypesStmt->bindValue(':end_date', $endDateStr, SQLITE3_TEXT);
    $evtRes = $evtTypesStmt->execute();
    $availableEvents = [];
    while ($er = $evtRes->fetchArray(SQLITE3_ASSOC)) {
        if (!empty($er['name']) && $er['name'] !== '_ml_engaged') {
            $availableEvents[] = [
                'name' => $er['name'],
                'count' => (int)$er['count']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'sessions' => $sessions,
        'total' => $totalSessions,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => max(1, (int) ceil($totalSessions / $limit)),
        'available_events' => $availableEvents
    ], JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
