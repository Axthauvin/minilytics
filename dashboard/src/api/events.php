<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/auth.php';
Auth::requireLogin();

require_once __DIR__ . '/db.php';

try {
    $siteId = $_GET['site_id'] ?? $_GET['site'] ?? null;
    $cleanSite = Database::sanitizeSiteId($siteId);
    $db = Database::getConnection($cleanSite);

    $search = trim($_GET['search'] ?? '');
    $eventName = trim($_GET['event_name'] ?? '');
    $sessionId = trim($_GET['session_id'] ?? '');
    $range = $_GET['range'] ?? '7d';
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    $now = time();
    $from = $_GET['from'] ?? $_GET['start'] ?? $_GET['start_date'] ?? null;
    $to = $_GET['to'] ?? $_GET['end'] ?? $_GET['end_date'] ?? null;

    if ($range === 'custom' || (!empty($from) && !empty($to))) {
        $range = 'custom';
        $startUnix = !empty($from) ? (strtotime($from . ' 00:00:00 UTC') ?: ($now - 30 * 86400)) : ($now - 30 * 86400);
        $endUnix = !empty($to) ? (strtotime($to . ' 23:59:59 UTC') ?: $now) : $now;
        if ($startUnix > $endUnix) {
            [$startUnix, $endUnix] = [$endUnix, $startUnix];
        }
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
    }
    $startDateStr = gmdate('Y-m-d H:i:s', $startUnix);
    $endDateStr = gmdate('Y-m-d H:i:s', $endUnix);
    // The Events Explorer is reserved for product/custom events. Pageviews
    // belong to the traffic overview and would otherwise flatten every other
    // series on this multi-event chart.
    $eventOnlyClause = "json_extract(action, '$.name') NOT LIKE '_ml_%' AND json_extract(action, '$.name') <> 'pageview'";

    // Build WHERE clauses
    $where = ["timestamp >= :start_date AND timestamp <= :end_date", $eventOnlyClause];
    $params = [
        ':start_date' => $startDateStr,
        ':end_date' => $endDateStr,
    ];

    if ($eventName !== '' && $eventName !== 'all') {
        $where[] = "json_extract(action, '$.name') = :event_name";
        $params[':event_name'] = $eventName;
    }

    if ($sessionId !== '') {
        $where[] = "session_id = :session_id";
        $params[':session_id'] = $sessionId;
    }

    if ($search !== '') {
        $where[] = "(session_id LIKE :search OR json_extract(action, '$.name') LIKE :search OR action LIKE :search_wild)";
        $params[':search'] = "%{$search}%";
        $params[':search_wild'] = "%{$search}%";
    }

    $whereClause = implode(' AND ', $where);

    // 1. Total matching count
    $countSql = "SELECT COUNT(*) FROM user_activity WHERE {$whereClause}";
    $cStmt = $db->prepare($countSql);
    foreach ($params as $k => $v) {
        $cStmt->bindValue($k, $v, SQLITE3_TEXT);
    }
    $totalCount = (int)$cStmt->execute()->fetchArray(SQLITE3_NUM)[0];

    // 2. Fetch events paginated
    $eventsSql = "SELECT id, session_id, visitor_id, action, timestamp 
                  FROM user_activity 
                  WHERE {$whereClause} 
                  ORDER BY timestamp DESC, id DESC 
                  LIMIT :limit OFFSET :offset";
    $eStmt = $db->prepare($eventsSql);
    foreach ($params as $k => $v) {
        $eStmt->bindValue($k, $v, SQLITE3_TEXT);
    }
    $eStmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $eStmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
    $eRes = $eStmt->execute();

    $events = [];
    while ($row = $eRes->fetchArray(SQLITE3_ASSOC)) {
        $actionData = json_decode($row['action'], true) ?: [];
        $tsUnix = strtotime($row['timestamp'] . ' UTC');
        $diff = max(0, $now - $tsUnix);

        if ($diff < 60) {
            $timeAgo = "just now";
        } elseif ($diff < 3600) {
            $timeAgo = floor($diff / 60) . "m ago";
        } elseif ($diff < 86400) {
            $timeAgo = floor($diff / 3600) . "h ago";
        } else {
            $timeAgo = floor($diff / 86400) . "d ago";
        }

        $events[] = [
            'id' => (int)$row['id'],
            'session_id' => $row['session_id'],
            'visitor_id' => $row['visitor_id'] ?? $row['session_id'],
            'name' => $actionData['name'] ?? 'unknown',
            'site_id' => $actionData['site_id'] ?? 'default_site',
            'data' => $actionData['data'] ?? [],
            'timestamp' => $row['timestamp'],
            'time_ago' => $timeAgo
        ];
    }

    // 3. Get distinct event types for filter dropdown
    $typesSql = "SELECT json_extract(action, '$.name') as name, COUNT(*) as count 
                 FROM user_activity 
                 WHERE timestamp >= :start_date AND timestamp <= :end_date
                   AND {$eventOnlyClause}
                 GROUP BY name 
                 ORDER BY count DESC";
    $tStmt = $db->prepare($typesSql);
    $tStmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
    $tStmt->bindValue(':end_date', $endDateStr, SQLITE3_TEXT);
    $tRes = $tStmt->execute();

    $types = [];
    while ($tr = $tRes->fetchArray(SQLITE3_ASSOC)) {
        if (!empty($tr['name'])) {
            $types[] = [
                'name' => $tr['name'],
                'count' => (int)$tr['count']
            ];
        }
    }
    $series = [];
    $seriesByName = [];
    foreach ($types as $index => $type) {
        $key = 'event_' . $index;
        $series[] = ['key' => $key, 'name' => $type['name'], 'count' => $type['count']];
        $seriesByName[$type['name']] = $key;
    }

    // 4. Chart data: continuous time-series matching overview.js style
    $useHourly = in_array($range, ['today', '24h']);
    $intervalHours = $useHourly ? 1 : 24;
    $chartGroupFmt = $useHourly ? "%Y-%m-%d %H:00" : "%Y-%m-%d";

    $chartWhere = ["timestamp >= :chart_start AND timestamp <= :chart_end", $eventOnlyClause];
    $chartParams = [
        ':chart_start' => $startDateStr,
        ':chart_end' => $endDateStr,
    ];
    if ($sessionId !== '') {
        $chartWhere[] = "session_id = :chart_session_id";
        $chartParams[':chart_session_id'] = $sessionId;
    }
    $chartSql = "SELECT strftime('{$chartGroupFmt}', timestamp) as bucket,
                        json_extract(action, '$.name') as name,
                        COUNT(*) as count
                 FROM user_activity
                 WHERE " . implode(' AND ', $chartWhere) . "
                 GROUP BY bucket, name
                 ORDER BY bucket ASC";
    $chStmt = $db->prepare($chartSql);
    foreach ($chartParams as $k => $v) {
        $chStmt->bindValue($k, $v, SQLITE3_TEXT);
    }
    $chRes = $chStmt->execute();

    $slotMap = [];
    while ($cr = $chRes->fetchArray(SQLITE3_ASSOC)) {
        $key = $seriesByName[$cr['name'] ?? ''] ?? null;
        if ($key !== null) $slotMap[$cr['bucket']][$key] = (int)$cr['count'];
    }

    if ($range === 'today' || $range === '24h') {
        $effectiveStart = ($range === 'today') ? strtotime('today midnight') : floor(($now - 86400) / 3600) * 3600;
        $endStep = ceil($now / 3600) * 3600;
    } elseif ($range === '7d') {
        $effectiveStart = strtotime('6 days ago midnight');
        $endStep = strtotime('today midnight');
    } elseif ($range === '30d') {
        $effectiveStart = strtotime('29 days ago midnight');
        $endStep = strtotime('today midnight');
    } elseif ($range === '90d') {
        $effectiveStart = strtotime('89 days ago midnight');
        $endStep = strtotime('today midnight');
    } elseif ($range === '6m' || $range === '180d') {
        $effectiveStart = strtotime('179 days ago midnight');
        $endStep = strtotime('today midnight');
    } elseif ($range === 'custom') {
        $effectiveStart = strtotime(gmdate('Y-m-d', $startUnix) . ' 00:00:00 UTC');
        $endStep = strtotime(gmdate('Y-m-d', $endUnix) . ' 00:00:00 UTC');
    } elseif ($range === 'all') {
        $minDbTime = $db->querySingle("SELECT MIN(timestamp) FROM user_activity WHERE timestamp IS NOT NULL");
        $effectiveStart = $minDbTime ? strtotime(substr((string)$minDbTime, 0, 10) . ' 00:00:00 UTC') : strtotime('6 days ago midnight');
        $endStep = strtotime('today midnight');
    } else {
        $effectiveStart = strtotime('6 days ago midnight');
        $endStep = strtotime('today midnight');
    }
    $stepSeconds = $intervalHours * 3600;

    $chartData = [];
    $currStep = $effectiveStart;
    while ($currStep <= $endStep) {
        $point = ['timestamp' => $currStep];
        foreach ($series as $item) $point[$item['key']] = 0;

        // The SQL query already returns a bucket per hour or per day.  A daily
        // bucket must be added once — repeating it for all 24 hours inflated
        // every daily count by 24 (for example 80 incorrectly became 1920).
        $bucketKey = gmdate($useHourly ? 'Y-m-d H:00' : 'Y-m-d', (int)$currStep);
        if (isset($slotMap[$bucketKey])) {
            foreach ($slotMap[$bucketKey] as $key => $count) $point[$key] += $count;
        }

        if ($range === 'today' || $range === '24h') {
            $timeLabel = gmdate('h A', (int)$currStep);
        } elseif ($range === '7d') {
            $timeLabel = gmdate('D, j M', (int)$currStep);
        } else {
            $timeLabel = gmdate('M d', (int)$currStep);
        }

        $fullLabel = ($intervalHours === 1) 
            ? gmdate('l, F j, Y \a\t h:i A', (int)$currStep) 
            : gmdate('l, F j, Y', (int)$currStep);

        $point['label'] = $timeLabel;
        $point['full_label'] = $fullLabel;
        $chartData[] = $point;

        $currStep += $stepSeconds;
    }

    echo json_encode([
        'success'     => true,
        'events'      => $events,
        'types'       => $types,
        'series'      => $series,
        'chart_data'  => $chartData,
        'total'       => $totalCount,
        'page'        => $page,
        'limit'       => $limit,
        'total_pages' => max(1, (int)ceil($totalCount / $limit))
    ], JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
