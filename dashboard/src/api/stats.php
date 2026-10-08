<?php

declare(strict_types=1);

use Minilytics\Analytics\AnalyticsFilters;
use Minilytics\Auth\Auth;
use Minilytics\Database\Database;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../../vendor/autoload.php';
// Public demo sites are readable without an account.
Auth::requireSiteAccess((string)($_GET['site_id'] ?? $_GET['site'] ?? ''));


try {
    $range = $_GET['range'] ?? '7d';
    $siteId = $_GET['site_id'] ?? $_GET['site'] ?? null;
    $cleanSite = Database::sanitizeSiteId($siteId);
    $db = Database::getConnection($cleanSite);
    $availableSites = Database::getAvailableSites();
    if (Auth::isGuest()) {
        $availableSites = array_values(array_map([Database::class, 'guestSiteView'], array_filter($availableSites, static fn(array $s): bool => !empty($s['is_public']))));
    }

    // Date calculations
    $now = time();
    $from = $_GET['from'] ?? $_GET['start'] ?? $_GET['start_date'] ?? null;
    $to = $_GET['to'] ?? $_GET['end'] ?? $_GET['end_date'] ?? null;

    if ($range === 'custom' || (!empty($from) && !empty($to))) {
        $range = 'custom';
        $startUnix = strtotime($from . ' 00:00:00 UTC') ?: ($now - 30 * 86400);
        $endUnix = strtotime($to . ' 23:59:59 UTC') ?: $now;
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

    // Site filter condition (in isolated per-site DB, all records belong to the site)
    $siteCondition = '';
    $siteParams = [];

    // Dashboard filters (pages, referrers, environment, countries). They are
    // injected through $siteCondition so every query below is filtered. The
    // matching window also covers the previous period used for deltas.
    $activeFilters = AnalyticsFilters::fromRequest($_GET);
    if ($activeFilters) {
        $filterStartUnix = $range === 'all' ? 0 : max(0, $startUnix - max(3600, $endUnix - $startUnix));
        $siteCondition .= AnalyticsFilters::apply(
            $db,
            $activeFilters,
            gmdate('Y-m-d H:i:s', $filterStartUnix),
            $endDateStr
        );
    }

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
        WHERE timestamp >= :start_date AND timestamp <= :end_date {$siteCondition}
    ";
    $sumStmt = $db->prepare($summarySql);
    $sumStmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
    $sumStmt->bindValue(':end_date', $endDateStr, SQLITE3_TEXT);
    foreach ($siteParams as $k => $v) $sumStmt->bindValue($k, $v, SQLITE3_TEXT);
    $sumRow = $sumStmt->execute()->fetchArray(SQLITE3_ASSOC) ?: [];

    $totalPageviews = (int)($sumRow['pageviews'] ?? 0);
    $totalVisitors = (int)($sumRow['visitors'] ?? 0);
    $totalSessions = (int)($sumRow['sessions'] ?? 0);
    $totalEvents = (int)($sumRow['total_events'] ?? 0);

    // A visit expires after 30 minutes without an event. This keeps a browser
    // tab left open for hours from inflating the average visit duration.
    $sessionMetricsSql = "
        WITH event_gaps AS (
            SELECT id,
                   session_id,
                   timestamp,
                   action,
                   LAG(timestamp) OVER (
                       PARTITION BY session_id
                       ORDER BY timestamp, id
                   ) AS previous_timestamp
            FROM user_activity
            WHERE timestamp >= :start_date AND timestamp <= :end_date {$siteCondition}
        ),
        sessionized_events AS (
            SELECT id,
                   session_id,
                   timestamp,
                   action,
                   SUM(CASE
                       WHEN previous_timestamp IS NULL
                         OR strftime('%s', timestamp) - strftime('%s', previous_timestamp) >= 1800
                       THEN 1 ELSE 0
                   END) OVER (
                       PARTITION BY session_id
                       ORDER BY timestamp, id
                       ROWS UNBOUNDED PRECEDING
                   ) AS visit_number
            FROM event_gaps
        ),
        visits AS (
            SELECT session_id,
                   visit_number,
                   SUM(CASE WHEN json_extract(action, '$.name') NOT LIKE '_ml_%' THEN 1 ELSE 0 END) AS action_count,
                   strftime('%s', MAX(timestamp)) - strftime('%s', MIN(timestamp)) AS duration
            FROM sessionized_events
            GROUP BY session_id, visit_number
        )
        SELECT 
            COUNT(*) as total_sessions,
            AVG(duration) as avg_duration,
            100.0 * SUM(CASE WHEN action_count = 1 THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0) as bounce_rate
        FROM visits
    ";
    $sessStmt = $db->prepare($sessionMetricsSql);
    $sessStmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
    $sessStmt->bindValue(':end_date', $endDateStr, SQLITE3_TEXT);
    foreach ($siteParams as $k => $v) $sessStmt->bindValue($k, $v, SQLITE3_TEXT);
    $sessMetrics = $sessStmt->execute()->fetchArray(SQLITE3_ASSOC) ?: [];

    $totalSessions = (int)($sessMetrics['total_sessions'] ?? $totalSessions);
    $avgDuration = round((float)($sessMetrics['avg_duration'] ?? 0), 1);
    $bounceRate = $totalSessions > 0 ? round((float)($sessMetrics['bounce_rate'] ?? 0), 1) : 0.0;

    // Real comparison metrics against the previous period of identical length
    if ($range === 'all') {
        $deltas = [
            'visitors' => null,
            'sessions' => null,
            'pageviews' => null,
            'bounce_rate' => null,
            'duration' => null
        ];
    } else {
        $periodLength = max(3600, $endUnix - $startUnix);
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
            WITH event_gaps AS (
                SELECT id,
                       session_id,
                       timestamp,
                       action,
                       LAG(timestamp) OVER (
                           PARTITION BY session_id
                           ORDER BY timestamp, id
                       ) AS previous_timestamp
                FROM user_activity
                WHERE timestamp >= :prev_start AND timestamp < :prev_end {$siteCondition}
            ),
            sessionized_events AS (
                SELECT id,
                       session_id,
                       timestamp,
                       action,
                       SUM(CASE
                           WHEN previous_timestamp IS NULL
                             OR strftime('%s', timestamp) - strftime('%s', previous_timestamp) >= 1800
                           THEN 1 ELSE 0
                       END) OVER (
                           PARTITION BY session_id
                           ORDER BY timestamp, id
                           ROWS UNBOUNDED PRECEDING
                       ) AS visit_number
                FROM event_gaps
            ),
            visits AS (
                SELECT session_id,
                       visit_number,
                   SUM(CASE WHEN json_extract(action, '$.name') NOT LIKE '_ml_%' THEN 1 ELSE 0 END) AS action_count,
                       strftime('%s', MAX(timestamp)) - strftime('%s', MIN(timestamp)) AS duration
                FROM sessionized_events
                GROUP BY session_id, visit_number
            )
            SELECT 
                COUNT(*) as total_sessions,
                AVG(duration) as avg_duration,
                100.0 * SUM(CASE WHEN action_count = 1 THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0) as bounce_rate
            FROM visits
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

        $formatDiff = function ($diff, $suffix = '') {
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
    }

    // 3. Time Series Data for Chart (From REAL events in DB)
    $intervalHours = 24;
    $stepSeconds = 86400;
    $slotFormat = '%Y-%m-%d';

    if ($range === 'today' || $range === '24h') {
        $intervalHours = 1;
        $stepSeconds = 3600;
        $effectiveStart = ($range === 'today') ? strtotime('today midnight') : floor(($now - 86400) / 3600) * 3600;
        $endStep = ceil($now / 3600) * 3600;
        $slotFormat = '%Y-%m-%d %H:00:00';
    } elseif ($range === '7d') {
        $intervalHours = 24;
        $stepSeconds = 86400;
        $effectiveStart = strtotime('6 days ago midnight');
        $endStep = strtotime('today midnight');
        $slotFormat = '%Y-%m-%d';
    } elseif ($range === '30d') {
        $intervalHours = 24;
        $stepSeconds = 86400;
        $effectiveStart = strtotime('29 days ago midnight');
        $endStep = strtotime('today midnight');
        $slotFormat = '%Y-%m-%d';
    } elseif ($range === '90d') {
        $intervalHours = 24;
        $stepSeconds = 86400;
        $effectiveStart = strtotime('89 days ago midnight');
        $endStep = strtotime('today midnight');
        $slotFormat = '%Y-%m-%d';
    } elseif ($range === '6m' || $range === '180d') {
        $intervalHours = 24;
        $stepSeconds = 86400;
        $effectiveStart = strtotime('179 days ago midnight');
        $endStep = strtotime('today midnight');
        $slotFormat = '%Y-%m-%d';
    } elseif ($range === 'custom') {
        $effectiveStart = strtotime(gmdate('Y-m-d', $startUnix) . ' 00:00:00 UTC');
        $endStep = strtotime(gmdate('Y-m-d', $endUnix) . ' 00:00:00 UTC');
        $spanDays = max(1, (int)round(($endStep - $effectiveStart) / 86400));
        if ($spanDays <= 2) {
            $intervalHours = 1;
            $stepSeconds = 3600;
            $effectiveStart = floor($startUnix / 3600) * 3600;
            $endStep = ceil($endUnix / 3600) * 3600;
            $slotFormat = '%Y-%m-%d %H:00:00';
        } elseif ($spanDays <= 365) {
            $intervalHours = 24;
            $stepSeconds = 86400;
            $slotFormat = '%Y-%m-%d';
        } else {
            // Long range > 1 year: group weekly
            $intervalHours = 168;
            $stepSeconds = 7 * 86400;
            $slotFormat = '%Y-%m-%d';
        }
    } elseif ($range === 'all') {
        $minDbTime = $db->querySingle("SELECT MIN(timestamp) FROM user_activity WHERE timestamp IS NOT NULL" . $siteCondition);
        $maxDbTime = $db->querySingle("SELECT MAX(timestamp) FROM user_activity WHERE timestamp IS NOT NULL" . $siteCondition);

        if ($minDbTime) {
            $effectiveStart = strtotime(substr((string)$minDbTime, 0, 10) . ' 00:00:00 UTC');
            $maxDbUnix = strtotime(substr((string)$maxDbTime, 0, 10) . ' 00:00:00 UTC');
            $endStep = max(strtotime('today midnight'), $maxDbUnix);

            $spanDays = max(1, (int)round(($endStep - $effectiveStart) / 86400));
            if ($spanDays <= 2) {
                $intervalHours = 1;
                $stepSeconds = 3600;
                $endStep = ceil($now / 3600) * 3600;
                $slotFormat = '%Y-%m-%d %H:00:00';
            } elseif ($spanDays <= 730) {
                $intervalHours = 24;
                $stepSeconds = 86400;
                $slotFormat = '%Y-%m-%d';
            } else {
                // For long historical data (> 2 years), group weekly
                $intervalHours = 168; // 7 days
                $stepSeconds = 7 * 86400;
                $slotFormat = '%Y-%m-%d';
            }
        } else {
            $intervalHours = 24;
            $stepSeconds = 86400;
            $effectiveStart = strtotime('6 days ago midnight');
            $endStep = strtotime('today midnight');
            $slotFormat = '%Y-%m-%d';
        }
    } else {
        $intervalHours = 24;
        $stepSeconds = 86400;
        $effectiveStart = strtotime('6 days ago midnight');
        $endStep = strtotime('today midnight');
        $slotFormat = '%Y-%m-%d';
    }

    $tsSql = "
        SELECT 
            strftime('{$slotFormat}', timestamp) as slot,
            SUM(CASE WHEN json_extract(action, '$.name') = 'pageview' THEN 1 ELSE 0 END) as views,
            COUNT(DISTINCT session_id) as sessions,
            COUNT(DISTINCT COALESCE(visitor_id, session_id)) as visitors,
            SUM(CASE WHEN json_extract(action, '$.name') != 'pageview' THEN 1 ELSE 0 END) as events,
            COUNT(*) as total_actions
        FROM user_activity
        WHERE timestamp >= :start_date AND timestamp <= :end_date {$siteCondition}
        GROUP BY slot
        ORDER BY slot ASC
    ";
    $tsStmt = $db->prepare($tsSql);
    $tsStmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
    $tsStmt->bindValue(':end_date', $endDateStr, SQLITE3_TEXT);
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
    $spanDays = max(1, (int)round(($endStep - $effectiveStart) / 86400));
    $timeseries = [];
    $currStep = $effectiveStart;

    while ($currStep <= $endStep) {
        $views = 0;
        $sessions = 0;
        $visitors = 0;
        $events = 0;

        if ($intervalHours === 1) {
            $subKey = gmdate('Y-m-d H:00:00', (int)$currStep);
            if (isset($slotMap[$subKey])) {
                $views = $slotMap[$subKey]['views'];
                $sessions = $slotMap[$subKey]['sessions'];
                $visitors = $slotMap[$subKey]['visitors'];
                $events = $slotMap[$subKey]['events'];
            }
            $timeLabel = gmdate('h A', (int)$currStep);
            $dateLabel = gmdate('M d, Y', (int)$currStep);
            $fullLabel = gmdate('l, F j, Y \a\t h:i A', (int)$currStep);
        } elseif ($intervalHours === 24) {
            $dayKey = gmdate('Y-m-d', (int)$currStep);
            if (isset($slotMap[$dayKey])) {
                $views = $slotMap[$dayKey]['views'];
                $sessions = $slotMap[$dayKey]['sessions'];
                $visitors = $slotMap[$dayKey]['visitors'];
                $events = $slotMap[$dayKey]['events'];
            }
            if ($range === '7d') {
                $timeLabel = gmdate('D, j M', (int)$currStep);
            } elseif ($range === '30d') {
                $timeLabel = gmdate('M d', (int)$currStep);
            } else {
                $timeLabel = ($spanDays > 180) ? gmdate('M y', (int)$currStep) : gmdate('M d', (int)$currStep);
            }
            $dateLabel = gmdate('M d, Y', (int)$currStep);
            $fullLabel = gmdate('l, F j, Y', (int)$currStep);
        } else {
            // Multi-day interval (e.g. weekly)
            $intervalDays = (int)($intervalHours / 24);
            for ($sub = 0; $sub < $intervalDays; $sub++) {
                $dayKey = gmdate('Y-m-d', (int)($currStep + $sub * 86400));
                if (isset($slotMap[$dayKey])) {
                    $views += $slotMap[$dayKey]['views'];
                    $sessions += $slotMap[$dayKey]['sessions'];
                    $visitors += $slotMap[$dayKey]['visitors'];
                    $events += $slotMap[$dayKey]['events'];
                }
            }
            $timeLabel = gmdate('M d', (int)$currStep);
            $dateLabel = gmdate('M d, Y', (int)$currStep);
            $fullLabel = 'Week of ' . gmdate('l, F j, Y', (int)$currStep);
        }

        $timeseries[] = [
            'timestamp' => $currStep,
            'interval_hours' => $intervalHours,
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

    // Helper to normalize domains for exact match comparison
    $normalizeDomain = static function (?string $raw): string {
        if (!$raw) return '';
        $raw = trim(strtolower($raw));
        if (!str_contains($raw, '://')) {
            $raw = 'http://' . $raw;
        }
        $host = parse_url($raw, PHP_URL_HOST) ?: '';
        $host = preg_replace('/:\d+$/', '', $host); // strip port
        $host = preg_replace('/^www\./', '', $host); // strip www.
        return trim($host, '/');
    };

    // Collect own site domain(s) for exact self-referral exclusion
    $ownDomains = [];
    foreach ($availableSites as $s) {
        if (($s['id'] ?? '') === $cleanSite) {
            $d = $normalizeDomain($s['domain'] ?? '');
            if ($d !== '') $ownDomains[$d] = true;
            $sid = $normalizeDomain($s['id'] ?? '');
            if ($sid !== '' && str_contains($sid, '.')) $ownDomains[$sid] = true;
        }
    }
    try {
        $hostQuery = $db->query("
            SELECT DISTINCT json_extract(action, '$.data.hostname') as h 
            FROM user_activity 
            WHERE json_extract(action, '$.data.hostname') IS NOT NULL
            LIMIT 25
        ");
        if ($hostQuery) {
            while ($hRow = $hostQuery->fetchArray(SQLITE3_ASSOC)) {
                $dh = $normalizeDomain((string)$hRow['h']);
                if ($dh !== '') {
                    $ownDomains[$dh] = true;
                }
            }
        }
    } catch (Throwable $e) {
    }

    // 4. Real Top Pages from SQLite
    $pagesSql = "
        SELECT 
            COALESCE(json_extract(action, '$.data.path'), '/') as path,
            MAX(json_extract(action, '$.data.title')) as title,
            COUNT(*) as views,
            COUNT(DISTINCT COALESCE(visitor_id, session_id)) as visitors
        FROM user_activity
        WHERE timestamp >= :start_date AND timestamp <= :end_date
          AND json_extract(action, '$.name') = 'pageview'
          {$siteCondition}
        GROUP BY path
        ORDER BY views DESC
        LIMIT 100
    ";
    $pStmt = $db->prepare($pagesSql);
    $pStmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
    $pStmt->bindValue(':end_date', $endDateStr, SQLITE3_TEXT);
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

    // 5. Real Top Referrers from SQLite (with own domain exclusion)
    $refSql = "
        SELECT 
            json_extract(action, '$.data.referrer') as referrer,
            COUNT(*) as count
        FROM user_activity
        WHERE timestamp >= :start_date AND timestamp <= :end_date
          AND json_extract(action, '$.name') = 'pageview'
          {$siteCondition}
        GROUP BY referrer
        ORDER BY count DESC
        LIMIT 150
    ";
    $rStmt = $db->prepare($refSql);
    $rStmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
    $rStmt->bindValue(':end_date', $endDateStr, SQLITE3_TEXT);
    foreach ($siteParams as $k => $v) $rStmt->bindValue($k, $v, SQLITE3_TEXT);
    $rRes = $rStmt->execute();

    $topReferrers = [];
    while ($rr = $rRes->fetchArray(SQLITE3_ASSOC)) {
        $rawRef = trim((string)($rr['referrer'] ?? ''));
        $cleanRef = 'Direct / None';
        $domain = 'direct';

        // MySQL/MariaDB's JSON_UNQUOTE(JSON_EXTRACT(...)) turns a JSON null
        // into the literal string "null", unlike SQLite which returns SQL
        // NULL. Treat both representations as a direct visit.
        if ($rawRef !== '' && strtolower($rawRef) !== 'null') {
            $parsed = parse_url($rawRef, PHP_URL_HOST);
            $cleanRef = $parsed ?: $rawRef;
            $domain = str_replace('www.', '', $cleanRef);

            // Filter out exact self-referrals (matching the site's own domain)
            $normRef = $normalizeDomain($cleanRef);
            if ($normRef !== '' && isset($ownDomains[$normRef])) {
                continue;
            }
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

        if (count($topReferrers) >= 100) {
            break;
        }
    }

    // 6. Real Top Events from SQLite
    $evtSql = "
        SELECT 
            json_extract(action, '$.name') as event_name,
            COUNT(*) as count
        FROM user_activity
        WHERE timestamp >= :start_date AND timestamp <= :end_date
          AND json_extract(action, '$.name') != 'pageview'
          {$siteCondition}
        GROUP BY event_name
        ORDER BY count DESC
        LIMIT 100
    ";
    $eStmt = $db->prepare($evtSql);
    $eStmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
    $eStmt->bindValue(':end_date', $endDateStr, SQLITE3_TEXT);
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
    // These are audience dimensions, not pageview dimensions: a visitor is
    // counted once for each value they used during the selected period.
    $envQuery = function (string $field) use ($db, $startDateStr, $endDateStr, $totalVisitors, $siteCondition, $siteParams): array {
        $sql = "
            SELECT 
                COALESCE(json_extract(action, '$.data.{$field}'), 'Unknown') as label,
                COUNT(DISTINCT COALESCE(visitor_id, session_id)) as count
            FROM user_activity
            WHERE timestamp >= :start_date AND timestamp <= :end_date
              AND json_extract(action, '$.name') = 'pageview'
              {$siteCondition}
            GROUP BY label
            ORDER BY count DESC
            LIMIT 50
        ";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
        $stmt->bindValue(':end_date', $endDateStr, SQLITE3_TEXT);
        foreach ($siteParams as $k => $v) $stmt->bindValue($k, $v, SQLITE3_TEXT);
        $res = $stmt->execute();
        $list = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $cnt = (int)$row['count'];
            $pct = $totalVisitors > 0 ? round(($cnt / $totalVisitors) * 100, 1) : 0;
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
            COUNT(DISTINCT COALESCE(visitor_id, session_id)) as count
        FROM user_activity
        WHERE timestamp >= :start_date AND timestamp <= :end_date
          AND json_extract(action, '$.name') = 'pageview'
          {$siteCondition}
        GROUP BY country, country_code
        ORDER BY count DESC
        LIMIT 100
    ";
    $cStmt = $db->prepare($countrySql);
    $cStmt->bindValue(':start_date', $startDateStr, SQLITE3_TEXT);
    $cStmt->bindValue(':end_date', $endDateStr, SQLITE3_TEXT);
    foreach ($siteParams as $k => $v) $cStmt->bindValue($k, $v, SQLITE3_TEXT);
    $cRes = $cStmt->execute();
    $topCountries = [];
    while ($cr = $cRes->fetchArray(SQLITE3_ASSOC)) {
        $cnt = (int)$cr['count'];
        $pct = $totalVisitors > 0 ? round(($cnt / $totalVisitors) * 100, 1) : 0;
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
        'filters' => (object)$activeFilters,
        'summary' => [
            'visitors' => $totalVisitors,
            'sessions' => $totalSessions,
            'session_count' => (int)($sumRow['sessions'] ?? 0),
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
