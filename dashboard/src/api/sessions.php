<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

try {
    $siteId = $_GET['site_id'] ?? $_GET['site'] ?? null;
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
        $firstTime = null;
        $lastTime = null;
        $entryPage = null;
        $referrer = null;
        $browser = 'Other';
        $os = 'Other';
        $device = 'Desktop';
        $country = 'Unknown';
        $countryCode = 'UN';

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
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
            }
            $lastTime = $ts;

            $offsetSec = $ts - $firstTime;
            $offsetLabel = ($offsetSec < 60) ? "+{$offsetSec}s" : ("+" . floor($offsetSec / 60) . "m " . ($offsetSec % 60) . "s");

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

        if (empty($events)) {
            http_response_code(404);
            echo json_encode(['error' => 'Session not found']);
            exit;
        }

        $durationSec = max(0, $lastTime - $firstTime);
        $durationLabel = ($durationSec < 60)
            ? "{$durationSec}s"
            : (floor($durationSec / 60) . "m " . ($durationSec % 60) . "s");

        echo json_encode([
            'success' => true,
            'session' => [
                'session_id' => $specificSessionId,
                'started_at' => $events[0]['timestamp'],
                'ended_at' => end($events)['timestamp'],
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
                'avatar_url' => "https://api.dicebear.com/10.x/glyphs/svg?seed=" . rawurlencode($specificSessionId),
                'events' => $events
            ]
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // 2. Listing sessions with aggregations
    $range = $_GET['range'] ?? '7d';
    $search = trim($_GET['search'] ?? '');
    $limit = max(1, min(100, (int) ($_GET['limit'] ?? 25)));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    $now = time();
    $startUnix = match ($range) {
        'today' => strtotime('today midnight'),
        '24h' => $now - 86400,
        '7d' => $now - (7 * 86400),
        '30d' => $now - (30 * 86400),
        'all' => 0,
        default => $now - (7 * 86400)
    };
    $startDateStr = gmdate('Y-m-d H:i:s', $startUnix);

    $where = ["timestamp >= :start_date"];
    $params = [':start_date' => $startDateStr];

    if ($search !== '') {
        $where[] = "session_id LIKE :search";
        $params[':search'] = "%{$search}%";
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
            COUNT(*) as event_count,
            (strftime('%s', MAX(timestamp)) - strftime('%s', MIN(timestamp))) as duration_seconds
        FROM user_activity
        WHERE {$whereClause}
        GROUP BY session_id
        ORDER BY last_active_at DESC
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
        $durLabel = ($durSec < 60)
            ? "{$durSec}s"
            : (floor($durSec / 60) . "m " . ($durSec % 60) . "s");

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
            }

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
            'avatar_url' => "https://api.dicebear.com/10.x/glyphs/svg?seed=" . rawurlencode($sId),
            'flow' => $flow
        ];
    }

    echo json_encode([
        'success' => true,
        'sessions' => $sessions,
        'total' => $totalSessions,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => max(1, (int) ceil($totalSessions / $limit))
    ], JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
