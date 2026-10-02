<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

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
    $startUnix = match ($range) {
        'today' => strtotime('today midnight'),
        '24h' => $now - 86400,
        '7d' => $now - (7 * 86400),
        '30d' => $now - (30 * 86400),
        'all' => 0,
        default => $now - (7 * 86400)
    };
    $startDateStr = gmdate('Y-m-d H:i:s', $startUnix);

    // Build WHERE clauses
    $where = ["timestamp >= :start_date"];
    $params = [':start_date' => $startDateStr];

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
                 WHERE timestamp >= :start_date 
                 GROUP BY name 
                 ORDER BY count DESC";
    $tStmt = $db->prepare($typesSql);
    $tStmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
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

    // 4. Chart data: events grouped by hour (today/24h) or by day (7d/30d/all)
    $useHourly = in_array($range, ['today', '24h']);
    if ($useHourly) {
        $chartGroupFmt = "%Y-%m-%d %H:00";
        $chartLabelFmt = "%H:%M";
    } else {
        $chartGroupFmt = "%Y-%m-%d";
        $chartLabelFmt = "%d/%m";
    }

    $chartWhere = ["timestamp >= :chart_start"];
    $chartParams = [':chart_start' => $startDateStr];
    if ($eventName !== '' && $eventName !== 'all') {
        $chartWhere[] = "json_extract(action, '$.name') = :chart_event_name";
        $chartParams[':chart_event_name'] = $eventName;
    }
    if ($sessionId !== '') {
        $chartWhere[] = "session_id = :chart_session_id";
        $chartParams[':chart_session_id'] = $sessionId;
    }

    $chartSql = "SELECT strftime('{$chartGroupFmt}', timestamp) as bucket,
                        strftime('{$chartLabelFmt}', timestamp) as label,
                        COUNT(*) as count,
                        COUNT(DISTINCT session_id) as sessions
                 FROM user_activity
                 WHERE " . implode(' AND ', $chartWhere) . "
                 GROUP BY bucket
                 ORDER BY bucket ASC";
    $chStmt = $db->prepare($chartSql);
    foreach ($chartParams as $k => $v) {
        $chStmt->bindValue($k, $v, SQLITE3_TEXT);
    }
    $chRes = $chStmt->execute();

    $chartData = [];
    while ($cr = $chRes->fetchArray(SQLITE3_ASSOC)) {
        $chartData[] = [
            'label'    => $cr['label'],
            'full_label' => $cr['bucket'],
            'events'   => (int)$cr['count'],
            'sessions' => (int)$cr['sessions'],
        ];
    }

    echo json_encode([
        'success'     => true,
        'events'      => $events,
        'types'       => $types,
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
