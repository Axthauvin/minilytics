<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

try {
    $range = $_GET['range'] ?? '7d';
    $siteId = $_GET['site_id'] ?? $_GET['site'] ?? null;
    $cleanSite = Database::sanitizeSiteId($siteId);
    $db = Database::getConnection($cleanSite);
    $availableSites = Database::getAvailableSites();

    // Date calculations
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

    // Site filter condition (in isolated per-site DB, all records belong to the site)
    $siteCondition = '';
    $siteParams = [];

    // 1. Live visitors in the last 5 minutes
    $liveThreshold = gmdate('Y-m-d H:i:s', $now - 300);
    $liveStmt = $db->prepare("SELECT COUNT(DISTINCT COALESCE(visitor_id, session_id)) FROM user_activity WHERE timestamp >= :live_time" . $siteCondition);
    $liveStmt->bindValue(':live_time', $liveThreshold, SQLITE3_TEXT);
    foreach ($siteParams as $k => $v) $liveStmt->bindValue($k, $v, SQLITE3_TEXT);
    $liveResult = $liveStmt->execute();
    $liveVisitors = (int)$liveResult->fetchArray(SQLITE3_NUM)[0];

    // 2. Summary stats for the selected period
    $summarySql = "
        SELECT 
            COUNT(*) as total_events,
            SUM(CASE WHEN json_extract(action, '$.name') = 'pageview' THEN 1 ELSE 0 END) as pageviews,
            COUNT(DISTINCT session_id) as sessions,
            COUNT(DISTINCT COALESCE(visitor_id, session_id)) as visitors
        FROM user_activity
        WHERE timestamp >= :start_date {$siteCondition}
    ";
    $sumStmt = $db->prepare($summarySql);
    $sumStmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
    foreach ($siteParams as $k => $v) $sumStmt->bindValue($k, $v, SQLITE3_TEXT);
    $sumRow = $sumStmt->execute()->fetchArray(SQLITE3_ASSOC) ?: [];

    $totalPageviews = (int)($sumRow['pageviews'] ?? 0);
    $totalVisitors = (int)($sumRow['visitors'] ?? 0);
    $totalSessions = (int)($sumRow['sessions'] ?? 0);
    $totalEvents = (int)($sumRow['total_events'] ?? 0);

    // Session duration & bounce rate
    $sessionMetricsSql = "
        SELECT 
            COUNT(*) as total_sessions,
            AVG(duration) as avg_duration,
            100.0 * SUM(CASE WHEN action_count = 1 THEN 1 ELSE 0 END) / MAX(1, COUNT(*)) as bounce_rate
        FROM (
            SELECT session_id,
                   COUNT(*) as action_count,
                   (strftime('%s', MAX(timestamp)) - strftime('%s', MIN(timestamp))) as duration
            FROM user_activity
            WHERE timestamp >= :start_date {$siteCondition}
            GROUP BY session_id
        )
    ";
    $sessStmt = $db->prepare($sessionMetricsSql);
    $sessStmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
    foreach ($siteParams as $k => $v) $sessStmt->bindValue($k, $v, SQLITE3_TEXT);
    $sessMetrics = $sessStmt->execute()->fetchArray(SQLITE3_ASSOC) ?: [];

    $totalSessions = (int)($sessMetrics['total_sessions'] ?? $totalSessions);
    $avgDuration = round((float)($sessMetrics['avg_duration'] ?? 0), 1);
    $bounceRate = $totalSessions > 0 ? round((float)($sessMetrics['bounce_rate'] ?? 0), 1) : 0.0;

    // Real comparison metrics against the previous period of identical length
    $periodLength = max(3600, $now - $startUnix);
    $prevStartUnix = max(0, $startUnix - $periodLength);
    $prevStartDateStr = gmdate('Y-m-d H:i:s', $prevStartUnix);
    $prevEndDateStr = $startDateStr;

    $prevSumSql = "
        SELECT 
            SUM(CASE WHEN json_extract(action, '$.name') = 'pageview' THEN 1 ELSE 0 END) as pageviews,
            COUNT(DISTINCT session_id) as sessions,
            COUNT(DISTINCT COALESCE(visitor_id, session_id)) as visitors
        FROM user_activity
        WHERE timestamp >= :prev_start AND timestamp < :prev_end {$siteCondition}
    ";
    $pSumStmt = $db->prepare($prevSumSql);
    $pSumStmt->bindValue(':prev_start', $prevStartDateStr, SQLITE3_TEXT);
    $pSumStmt->bindValue(':prev_end', $prevEndDateStr, SQLITE3_TEXT);
    foreach ($siteParams as $k => $v) $pSumStmt->bindValue($k, $v, SQLITE3_TEXT);
    $prevSumRow = $pSumStmt->execute()->fetchArray(SQLITE3_ASSOC) ?: [];

    $prevPageviews = (int)($prevSumRow['pageviews'] ?? 0);
    $prevVisitors = (int)($prevSumRow['visitors'] ?? 0);
    $prevSessions = (int)($prevSumRow['sessions'] ?? 0);

    $prevSessSql = "
        SELECT 
            COUNT(*) as total_sessions,
            AVG(duration) as avg_duration,
            100.0 * SUM(CASE WHEN action_count = 1 THEN 1 ELSE 0 END) / MAX(1, COUNT(*)) as bounce_rate
        FROM (
            SELECT session_id,
                   COUNT(*) as action_count,
                   (strftime('%s', MAX(timestamp)) - strftime('%s', MIN(timestamp))) as duration
            FROM user_activity
            WHERE timestamp >= :prev_start AND timestamp < :prev_end {$siteCondition}
            GROUP BY session_id
        )
    ";
    $pSessStmt = $db->prepare($prevSessSql);
    $pSessStmt->bindValue(':prev_start', $prevStartDateStr, SQLITE3_TEXT);
    $pSessStmt->bindValue(':prev_end', $prevEndDateStr, SQLITE3_TEXT);
    foreach ($siteParams as $k => $v) $pSessStmt->bindValue($k, $v, SQLITE3_TEXT);
    $pSessMetrics = $pSessStmt->execute()->fetchArray(SQLITE3_ASSOC) ?: [];

    $prevSessions = (int)($pSessMetrics['total_sessions'] ?? 0);
    $prevBounceRate = $prevSessions > 0 ? round((float)($pSessMetrics['bounce_rate'] ?? 0), 1) : 0.0;
    $prevDuration = round((float)($pSessMetrics['avg_duration'] ?? 0), 1);

    // Compute actual real deltas
    $diffVisitors = $totalVisitors - $prevVisitors;
    $diffSessions = $totalSessions - $prevSessions;
    $diffPageviews = $totalPageviews - $prevPageviews;
    $diffBounce = round($bounceRate - $prevBounceRate, 1);
    $diffDuration = round($avgDuration - $prevDuration);

    $formatDiff = function($diff, $suffix = '') {
        if ($diff > 0) return "+{$diff}{$suffix}";
        if ($diff < 0) return "{$diff}{$suffix}";
        return "0{$suffix}";
    };

    $deltas = [
        'visitors' => $formatDiff($diffVisitors),
        'sessions' => $formatDiff($diffSessions),
        'pageviews' => $formatDiff($diffPageviews),
        'bounce_rate' => $formatDiff($diffBounce, '%'),
        'duration' => $formatDiff($diffDuration, 's')
    ];

    // 3. Time Series Data for Chart (From REAL events in DB)
    $intervalHours = ($range === 'today' || $range === '24h') ? 1 : 24;

    $tsSql = "
        SELECT 
            strftime('%Y-%m-%d %H:00:00', timestamp) as slot,
            SUM(CASE WHEN json_extract(action, '$.name') = 'pageview' THEN 1 ELSE 0 END) as views,
            COUNT(DISTINCT session_id) as sessions,
            COUNT(DISTINCT COALESCE(visitor_id, session_id)) as visitors,
            SUM(CASE WHEN json_extract(action, '$.name') != 'pageview' THEN 1 ELSE 0 END) as events,
            COUNT(*) as total_actions
        FROM user_activity
        WHERE timestamp >= :start_date {$siteCondition}
        GROUP BY slot
        ORDER BY slot ASC
    ";
    $tsStmt = $db->prepare($tsSql);
    $tsStmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
    foreach ($siteParams as $k => $v) $tsStmt->bindValue($k, $v, SQLITE3_TEXT);
    $tsRes = $tsStmt->execute();

    $slotMap = [];
    while ($r = $tsRes->fetchArray(SQLITE3_ASSOC)) {
        $slotMap[$r['slot']] = [
            'views' => (int)$r['views'],
            'sessions' => (int)$r['sessions'],
            'visitors' => (int)$r['visitors'],
            'events' => (int)$r['events']
        ];
    }

    // Build timeline sequence
    $timeseries = [];
    $stepSeconds = $intervalHours * 3600;

    $effectiveStart = match ($range) {
        'today' => strtotime('today midnight'),
        '24h' => floor(($now - 86400) / 3600) * 3600,
        '7d' => strtotime('6 days ago midnight'),
        '30d' => strtotime('29 days ago midnight'),
        default => strtotime('6 days ago midnight')
    };
    $currStep = $effectiveStart;
    $endStep = ($range === 'today' || $range === '24h') ? (ceil($now / 3600) * 3600) : (strtotime('today midnight'));

    while ($currStep <= $endStep) {
        $views = 0;
        $sessions = 0;
        $visitors = 0;
        $events = 0;

        for ($sub = 0; $sub < $intervalHours; $sub++) {
            $subKey = gmdate('Y-m-d H:00:00', (int)($currStep + $sub * 3600));
            if (isset($slotMap[$subKey])) {
                $views += $slotMap[$subKey]['views'];
                $sessions += $slotMap[$subKey]['sessions'];
                $visitors += $slotMap[$subKey]['visitors'];
                $events += $slotMap[$subKey]['events'];
            }
        }

        if ($range === 'today' || $range === '24h') {
            $timeLabel = gmdate('h A', (int)$currStep);
        } elseif ($range === '7d') {
            $timeLabel = gmdate('D, j M', (int)$currStep); // e.g. "Sat, 26 Sep"
        } else {
            $timeLabel = gmdate('M d', (int)$currStep);
        }
        
        $dateLabel = gmdate('M d, Y', (int)$currStep);
        $fullLabel = ($intervalHours === 1) 
            ? gmdate('l, F j, Y \a\t h:i A', (int)$currStep) 
            : gmdate('l, F j, Y', (int)$currStep);

        $timeseries[] = [
            'timestamp' => $currStep,
            'label' => $timeLabel,
            'date_label' => $dateLabel,
            'full_label' => $fullLabel,
            'pageviews' => $views,
            'sessions' => $sessions,
            'visitors' => $visitors,
            'events' => $events
        ];

        $currStep += $stepSeconds;
    }

    // 4. Real Top Pages from SQLite
    $pagesSql = "
        SELECT 
            COALESCE(json_extract(action, '$.data.path'), '/') as path,
            MAX(json_extract(action, '$.data.title')) as title,
            COUNT(*) as views,
            COUNT(DISTINCT COALESCE(visitor_id, session_id)) as visitors
        FROM user_activity
        WHERE timestamp >= :start_date 
          AND json_extract(action, '$.name') = 'pageview'
          {$siteCondition}
        GROUP BY path
        ORDER BY views DESC
        LIMIT 10
    ";
    $pStmt = $db->prepare($pagesSql);
    $pStmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
    foreach ($siteParams as $k => $v) $pStmt->bindValue($k, $v, SQLITE3_TEXT);
    $pRes = $pStmt->execute();

    $topPages = [];
    while ($pr = $pRes->fetchArray(SQLITE3_ASSOC)) {
        $vCount = (int)$pr['views'];
        $pct = $totalPageviews > 0 ? round(($vCount / $totalPageviews) * 100, 1) : 0;
        $topPages[] = [
            'path' => $pr['path'] ?: '/',
            'title' => $pr['title'] ?: $pr['path'],
            'views' => $vCount,
            'visitors' => (int)$pr['visitors'],
            'percentage' => $pct
        ];
    }

    // 5. Real Top Referrers from SQLite
    $refSql = "
        SELECT 
            json_extract(action, '$.data.referrer') as referrer,
            COUNT(*) as count
        FROM user_activity
        WHERE timestamp >= :start_date 
          AND json_extract(action, '$.name') = 'pageview'
          {$siteCondition}
        GROUP BY referrer
        ORDER BY count DESC
        LIMIT 10
    ";
    $rStmt = $db->prepare($refSql);
    $rStmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
    foreach ($siteParams as $k => $v) $rStmt->bindValue($k, $v, SQLITE3_TEXT);
    $rRes = $rStmt->execute();

    $topReferrers = [];
    while ($rr = $rRes->fetchArray(SQLITE3_ASSOC)) {
        $rawRef = $rr['referrer'];
        $cleanRef = 'Direct / None';
        $domain = 'direct';

        if (!empty($rawRef)) {
            $parsed = parse_url($rawRef, PHP_URL_HOST);
            $cleanRef = $parsed ?: $rawRef;
            $domain = str_replace('www.', '', $cleanRef);
        }

        $cnt = (int)$rr['count'];
        $pct = $totalPageviews > 0 ? round(($cnt / $totalPageviews) * 100, 1) : 0;

        $topReferrers[] = [
            'name' => $cleanRef,
            'domain' => $domain,
            'raw' => $rawRef,
            'views' => $cnt,
            'percentage' => $pct
        ];
    }

    // 6. Real Top Events from SQLite (Matching Image 2)
    $evtSql = "
        SELECT 
            json_extract(action, '$.name') as event_name,
            COUNT(*) as count
        FROM user_activity
        WHERE timestamp >= :start_date 
          AND json_extract(action, '$.name') != 'pageview'
          {$siteCondition}
        GROUP BY event_name
        ORDER BY count DESC
        LIMIT 10
    ";
    $eStmt = $db->prepare($evtSql);
    $eStmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
    foreach ($siteParams as $k => $v) $eStmt->bindValue($k, $v, SQLITE3_TEXT);
    $eRes = $eStmt->execute();

    $topEvents = [];
    $nonPageViewCount = 0;
    $rawEvents = [];
    while ($er = $eRes->fetchArray(SQLITE3_ASSOC)) {
        $cnt = (int)$er['count'];
        $nonPageViewCount += $cnt;
        $rawEvents[] = ['name' => (string)$er['event_name'], 'count' => $cnt];
    }

    foreach ($rawEvents as $re) {
        $pct = $nonPageViewCount > 0 ? round(($re['count'] / $nonPageViewCount) * 100, 1) : 0;
        $topEvents[] = [
            'name' => $re['name'],
            'count' => $re['count'],
            'percentage' => $pct
        ];
    }
    // 7. Environment Breakdowns (Browsers, OS, Devices)
    $envQuery = function(string $field) use ($db, $startDateStr, $totalPageviews, $siteCondition, $siteParams): array {
        $sql = "
            SELECT 
                COALESCE(json_extract(action, '$.data.{$field}'), 'Unknown') as label,
                COUNT(*) as count
            FROM user_activity
            WHERE timestamp >= :start_date 
              AND json_extract(action, '$.name') = 'pageview'
              {$siteCondition}
            GROUP BY label
            ORDER BY count DESC
            LIMIT 8
        ";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
        foreach ($siteParams as $k => $v) $stmt->bindValue($k, $v, SQLITE3_TEXT);
        $res = $stmt->execute();
        $list = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $cnt = (int)$row['count'];
            $pct = $totalPageviews > 0 ? round(($cnt / $totalPageviews) * 100, 1) : 0;
            $list[] = [
                'name' => (string)$row['label'],
                'count' => $cnt,
                'percentage' => $pct
            ];
        }
        return $list;
    };

    $topBrowsers = $envQuery('browser');
    $topOs = $envQuery('os');
    $topDevices = $envQuery('device');

    // 8. Countries Breakdown
    $countrySql = "
        SELECT 
            COALESCE(json_extract(action, '$.data.country'), 'Unknown') as country,
            COALESCE(json_extract(action, '$.data.country_code'), 'UN') as country_code,
            COUNT(*) as count
        FROM user_activity
        WHERE timestamp >= :start_date 
          AND json_extract(action, '$.name') = 'pageview'
          {$siteCondition}
        GROUP BY country
        ORDER BY count DESC
        LIMIT 10
    ";
    $cStmt = $db->prepare($countrySql);
    $cStmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
    foreach ($siteParams as $k => $v) $cStmt->bindValue($k, $v, SQLITE3_TEXT);
    $cRes = $cStmt->execute();
    $topCountries = [];
    while ($cr = $cRes->fetchArray(SQLITE3_ASSOC)) {
        $cnt = (int)$cr['count'];
        $pct = $totalPageviews > 0 ? round(($cnt / $totalPageviews) * 100, 1) : 0;
        $topCountries[] = [
            'name' => (string)$cr['country'],
            'code' => strtoupper((string)$cr['country_code']),
            'count' => $cnt,
            'percentage' => $pct
        ];
    }

    echo json_encode([
        'success' => true,
        'range' => $range,
        'site_id' => $cleanSite,
        'available_sites' => $availableSites,
        'summary' => [
            'visitors' => $totalVisitors,
            'sessions' => $totalSessions,
            'pageviews' => $totalPageviews,
            'events' => $totalEvents,
            'bounce_rate' => $bounceRate,
            'avg_duration_seconds' => $avgDuration,
            'live_visitors' => $liveVisitors,
            'deltas' => $deltas
        ],
        'timeseries' => $timeseries,
        'top_pages' => $topPages,
        'top_referrers' => $topReferrers,
        'top_events' => $topEvents,
        'environment' => [
            'browsers' => $topBrowsers,
            'os' => $topOs,
            'devices' => $topDevices
        ],
        'countries' => $topCountries
    ], JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
