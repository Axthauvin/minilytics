<?php

declare(strict_types=1);

namespace Minilytics\Analytics;

use InvalidArgumentException;
use Minilytics\Database\Database;
use Minilytics\Database\DatabaseConnection;
use Throwable;

/**
 * Analytics reports for one website over one period, with the dashboard
 * filters applied. Every analytics endpoint reads its numbers from here
 * instead of querying the database itself.
 */
final class SiteAnalytics
{
    public const ENVIRONMENT_FIELDS = ['browser', 'os', 'device'];

    /** Fields every event carries (see minilytics.js and track.php): the other `data` fields are the event's own properties. */
    private const CONTEXT_FIELDS = [
        'tracking_mode', '_ml_tracking_mode', 'path', 'title', 'hostname', 'referrer', 'language', 'screen', 'viewport',
        'utm', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'browser', 'os', 'device', 'country', 'country_code', 'region', 'city',
    ];

    /** Occurrences read to break an event down by property. */
    private const EVENT_SAMPLE = 5000;

    private ?string $condition = null;
    private ?array $summary = null;

    /** @param array<string, string[]> $filters see AnalyticsFilters::fromRequest() */
    public function __construct(
        private readonly DatabaseConnection $db,
        public readonly string $siteId,
        public readonly Period $period,
        public readonly array $filters = [],
    ) {}

    /** @param array<string, string[]> $filters */
    public static function open(?string $siteId, Period $period, array $filters = []): self
    {
        $siteId = Database::sanitizeSiteId($siteId);
        return new self(Database::getConnection($siteId), $siteId, $period, $filters);
    }

    /** Distinct visitors active in the last 5 minutes. */
    public function liveVisitors(): int
    {
        $rows = $this->rows(
            'SELECT COUNT(DISTINCT COALESCE(visitor_id, session_id)) AS visitors FROM user_activity WHERE timestamp >= :live_time' . $this->condition(),
            [':live_time' => gmdate('Y-m-d H:i:s', $this->period->now - 300)],
        );
        return (int) ($rows[0]['visitors'] ?? 0);
    }

    /** Headline numbers for the period, with deltas against the previous period of identical length. */
    public function summary(): array
    {
        if ($this->summary !== null) {
            return $this->summary;
        }
        $start = $this->period->startText();
        $end = $this->period->endText();
        $totals = $this->totals($start, $end, '<=');
        $visits = $this->visitMetrics($start, $end, '<=');

        $visitors = (int) ($totals['visitors'] ?? 0);
        $pageviews = (int) ($totals['pageviews'] ?? 0);
        $sessions = (int) ($visits['total_sessions'] ?? $totals['sessions'] ?? 0);
        $avgDuration = round((float) ($visits['avg_duration'] ?? 0), 1);
        $bounceRate = $sessions > 0 ? round((float) ($visits['bounce_rate'] ?? 0), 1) : 0.0;

        $deltas = ['visitors' => null, 'sessions' => null, 'pageviews' => null, 'bounce_rate' => null, 'duration' => null];
        if (!$this->period->isAll()) {
            $previousStart = gmdate('Y-m-d H:i:s', $this->period->previousStart());
            $previousTotals = $this->totals($previousStart, $start, '<');
            $previousVisits = $this->visitMetrics($previousStart, $start, '<');
            $previousSessions = (int) ($previousVisits['total_sessions'] ?? 0);
            $previousBounceRate = $previousSessions > 0 ? round((float) ($previousVisits['bounce_rate'] ?? 0), 1) : 0.0;
            $previousDuration = round((float) ($previousVisits['avg_duration'] ?? 0), 1);
            $deltas = [
                'visitors' => self::formatDiff($visitors - (int) ($previousTotals['visitors'] ?? 0)),
                'sessions' => self::formatDiff($sessions - $previousSessions),
                'pageviews' => self::formatDiff($pageviews - (int) ($previousTotals['pageviews'] ?? 0)),
                'bounce_rate' => self::formatDiff(round($bounceRate - $previousBounceRate, 1), '%'),
                'duration' => self::formatDiff(round($avgDuration - $previousDuration), 's'),
            ];
        }

        return $this->summary = [
            'visitors' => $visitors,
            'sessions' => $sessions,
            'session_count' => (int) ($totals['sessions'] ?? 0),
            'pageviews' => $pageviews,
            'events' => (int) ($totals['total_events'] ?? 0),
            'bounce_rate' => $bounceRate,
            'avg_duration_seconds' => $avgDuration,
            'live_visitors' => $this->liveVisitors(),
            'deltas' => $deltas,
        ];
    }

    /** Traffic per hour, day or week (depending on the period length), with empty slots filled in. */
    public function timeseries(): array
    {
        $range = $this->period->range;
        $now = $this->period->now;
        $startUnix = $this->period->start;
        $endUnix = $this->period->end;
        $intervalHours = 24;
        $stepSeconds = 86400;
        $slotFormat = '%Y-%m-%d';
        $effectiveStart = strtotime('6 days ago midnight UTC', $now);
        $endStep = strtotime('today midnight UTC', $now);

        if ($range === 'today' || $range === '24h') {
            $intervalHours = 1;
            $stepSeconds = 3600;
            $effectiveStart = ($range === 'today') ? strtotime('today midnight UTC', $now) : floor(($now - 86400) / 3600) * 3600;
            $endStep = ceil($now / 3600) * 3600;
            $slotFormat = '%Y-%m-%d %H:00:00';
        } elseif ($range === '30d') {
            $effectiveStart = strtotime('29 days ago midnight UTC', $now);
        } elseif ($range === '90d') {
            $effectiveStart = strtotime('89 days ago midnight UTC', $now);
        } elseif ($range === '6m' || $range === '180d') {
            $effectiveStart = strtotime('179 days ago midnight UTC', $now);
        } elseif ($range === 'custom') {
            $effectiveStart = strtotime(gmdate('Y-m-d', $startUnix) . ' 00:00:00 UTC');
            $endStep = strtotime(gmdate('Y-m-d', $endUnix) . ' 00:00:00 UTC');
            $spanDays = max(1, (int) round(($endStep - $effectiveStart) / 86400));
            if ($spanDays <= 2) {
                $intervalHours = 1;
                $stepSeconds = 3600;
                $effectiveStart = floor($startUnix / 3600) * 3600;
                $endStep = ceil($endUnix / 3600) * 3600;
                $slotFormat = '%Y-%m-%d %H:00:00';
            } elseif ($spanDays > 365) {
                // Long range > 1 year: group weekly
                $intervalHours = 168;
                $stepSeconds = 7 * 86400;
            }
        } elseif ($range === 'all') {
            $minDbTime = $this->db->querySingle('SELECT MIN(timestamp) FROM user_activity WHERE timestamp IS NOT NULL' . $this->condition());
            $maxDbTime = $this->db->querySingle('SELECT MAX(timestamp) FROM user_activity WHERE timestamp IS NOT NULL' . $this->condition());

            if ($minDbTime) {
                $effectiveStart = strtotime(substr((string) $minDbTime, 0, 10) . ' 00:00:00 UTC');
                $maxDbUnix = strtotime(substr((string) $maxDbTime, 0, 10) . ' 00:00:00 UTC');
                $endStep = max(strtotime('today midnight UTC', $now), $maxDbUnix);

                $spanDays = max(1, (int) round(($endStep - $effectiveStart) / 86400));
                if ($spanDays <= 2) {
                    $intervalHours = 1;
                    $stepSeconds = 3600;
                    $endStep = ceil($now / 3600) * 3600;
                    $slotFormat = '%Y-%m-%d %H:00:00';
                } elseif ($spanDays > 730) {
                    // For long historical data (> 2 years), group weekly
                    $intervalHours = 168;
                    $stepSeconds = 7 * 86400;
                }
            }
        }

        $slotMap = [];
        foreach ($this->periodRows("
            SELECT
                strftime('{$slotFormat}', timestamp) as slot,
                SUM(CASE WHEN json_extract(action, '$.name') = 'pageview' THEN 1 ELSE 0 END) as views,
                COUNT(DISTINCT session_id) as sessions,
                COUNT(DISTINCT COALESCE(visitor_id, session_id)) as visitors,
                SUM(CASE WHEN json_extract(action, '$.name') != 'pageview' THEN 1 ELSE 0 END) as events,
                COUNT(*) as total_actions
            FROM user_activity
            WHERE timestamp >= :start_date AND timestamp <= :end_date {$this->condition()}
            GROUP BY slot
            ORDER BY slot ASC
        ") as $r) {
            $slotMap[$r['slot']] = [
                'views' => (int) $r['views'],
                'sessions' => (int) $r['sessions'],
                'visitors' => (int) $r['visitors'],
                'events' => (int) $r['events'],
            ];
        }

        // Build timeline sequence
        $spanDays = max(1, (int) round(($endStep - $effectiveStart) / 86400));
        $timeseries = [];
        $currStep = $effectiveStart;

        while ($currStep <= $endStep) {
            $views = 0;
            $sessions = 0;
            $visitors = 0;
            $events = 0;

            if ($intervalHours === 1) {
                $subKey = gmdate('Y-m-d H:00:00', (int) $currStep);
                if (isset($slotMap[$subKey])) {
                    $views = $slotMap[$subKey]['views'];
                    $sessions = $slotMap[$subKey]['sessions'];
                    $visitors = $slotMap[$subKey]['visitors'];
                    $events = $slotMap[$subKey]['events'];
                }
                $timeLabel = gmdate('h A', (int) $currStep);
                $dateLabel = gmdate('M d, Y', (int) $currStep);
                $fullLabel = gmdate('l, F j, Y \a\t h:i A', (int) $currStep);
            } elseif ($intervalHours === 24) {
                $dayKey = gmdate('Y-m-d', (int) $currStep);
                if (isset($slotMap[$dayKey])) {
                    $views = $slotMap[$dayKey]['views'];
                    $sessions = $slotMap[$dayKey]['sessions'];
                    $visitors = $slotMap[$dayKey]['visitors'];
                    $events = $slotMap[$dayKey]['events'];
                }
                if ($range === '7d') {
                    $timeLabel = gmdate('D, j M', (int) $currStep);
                } elseif ($range === '30d') {
                    $timeLabel = gmdate('M d', (int) $currStep);
                } else {
                    $timeLabel = ($spanDays > 180) ? gmdate('M y', (int) $currStep) : gmdate('M d', (int) $currStep);
                }
                $dateLabel = gmdate('M d, Y', (int) $currStep);
                $fullLabel = gmdate('l, F j, Y', (int) $currStep);
            } else {
                // Multi-day interval (e.g. weekly)
                $intervalDays = (int) ($intervalHours / 24);
                for ($sub = 0; $sub < $intervalDays; $sub++) {
                    $dayKey = gmdate('Y-m-d', (int) ($currStep + $sub * 86400));
                    if (isset($slotMap[$dayKey])) {
                        $views += $slotMap[$dayKey]['views'];
                        $sessions += $slotMap[$dayKey]['sessions'];
                        $visitors += $slotMap[$dayKey]['visitors'];
                        $events += $slotMap[$dayKey]['events'];
                    }
                }
                $timeLabel = gmdate('M d', (int) $currStep);
                $dateLabel = gmdate('M d, Y', (int) $currStep);
                $fullLabel = 'Week of ' . gmdate('l, F j, Y', (int) $currStep);
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
                'events' => $events,
            ];

            $currStep += $stepSeconds;
        }
        return $timeseries;
    }

    /**
     * Most viewed pages, with their share of all pageviews. `$search` keeps the
     * pages whose path contains it (case-insensitive).
     */
    public function topPages(int $limit = 100, ?string $search = null): array
    {
        $totalPageviews = $this->summary()['pageviews'];
        [$searchSql, $searchParams] = self::search("COALESCE(json_extract(action, '$.data.path'), '/')", $search);
        $pages = [];
        foreach ($this->periodRows("
            SELECT
                COALESCE(json_extract(action, '$.data.path'), '/') as path,
                MAX(json_extract(action, '$.data.title')) as title,
                COUNT(*) as views,
                COUNT(DISTINCT COALESCE(visitor_id, session_id)) as visitors
            FROM user_activity
            WHERE timestamp >= :start_date AND timestamp <= :end_date
              AND json_extract(action, '$.name') = 'pageview'
              {$searchSql}
              {$this->condition()}
            GROUP BY path
            ORDER BY views DESC
            LIMIT 100
        ", $searchParams) as $row) {
            $views = (int) $row['views'];
            $pages[] = [
                'path' => $row['path'] ?: '/',
                'title' => $row['title'] ?: $row['path'],
                'views' => $views,
                'visitors' => (int) $row['visitors'],
                'percentage' => $totalPageviews > 0 ? round(($views / $totalPageviews) * 100, 1) : 0,
            ];
        }
        return array_slice($pages, 0, $limit);
    }

    /** Referring domains by pageviews, excluding the website's own domains. */
    public function topReferrers(int $limit = 100): array
    {
        $totalPageviews = $this->summary()['pageviews'];
        $ownDomains = $this->ownDomains();
        $referrers = [];
        foreach ($this->periodRows("
            SELECT
                json_extract(action, '$.data.referrer') as referrer,
                COUNT(*) as count
            FROM user_activity
            WHERE timestamp >= :start_date AND timestamp <= :end_date
              AND json_extract(action, '$.name') = 'pageview'
              {$this->condition()}
            GROUP BY referrer
            ORDER BY count DESC
            LIMIT 150
        ") as $row) {
            $rawRef = trim((string) ($row['referrer'] ?? ''));
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
                $normRef = self::normalizeDomain($cleanRef);
                if ($normRef !== '' && isset($ownDomains[$normRef])) {
                    continue;
                }
            }

            $count = (int) $row['count'];
            $referrers[] = [
                'name' => $cleanRef,
                'domain' => $domain,
                'raw' => $rawRef,
                'views' => $count,
                'percentage' => $totalPageviews > 0 ? round(($count / $totalPageviews) * 100, 1) : 0,
            ];
            if (count($referrers) >= 100) {
                break;
            }
        }
        return array_slice($referrers, 0, $limit);
    }

    /**
     * Custom events (everything but pageviews), with their share of the 100
     * most frequent ones. `$search` keeps the events whose name contains it.
     */
    public function topEvents(int $limit = 100, ?string $search = null): array
    {
        [$searchSql, $searchParams] = self::search("json_extract(action, '$.name')", $search);
        $rows = $this->periodRows("
            SELECT
                json_extract(action, '$.name') as event_name,
                COUNT(*) as count
            FROM user_activity
            WHERE timestamp >= :start_date AND timestamp <= :end_date
              AND json_extract(action, '$.name') != 'pageview'
              {$searchSql}
              {$this->condition()}
            GROUP BY event_name
            ORDER BY count DESC
            LIMIT 100
        ", $searchParams);
        $total = array_sum(array_map(static fn(array $row): int => (int) $row['count'], $rows));
        $events = [];
        foreach ($rows as $row) {
            $count = (int) $row['count'];
            $events[] = [
                'name' => (string) $row['event_name'],
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        }
        return array_slice($events, 0, $limit);
    }

    /**
     * One custom event in detail: how often it fired, how it evolved, on which
     * pages, and the values of its own properties (the `data` fields the site
     * sent, without the context every event carries). Properties are read
     * from the most recent occurrences only, to keep the report fast.
     */
    public function eventDetails(string $event, ?string $property = null, int $limit = 10): array
    {
        $where = "timestamp >= :start_date AND timestamp <= :end_date AND json_extract(action, '$.name') = :event" . $this->condition();
        $params = [':event' => $event];
        $totals = $this->periodRows("SELECT COUNT(*) AS occurrences, COUNT(DISTINCT COALESCE(visitor_id, session_id)) AS visitors, COUNT(DISTINCT session_id) AS sessions FROM user_activity WHERE {$where}", $params)[0] ?? [];
        $occurrences = (int) ($totals['occurrences'] ?? 0);
        $details = ['event' => $event, 'occurrences' => $occurrences, 'visitors' => (int) ($totals['visitors'] ?? 0), 'sessions' => (int) ($totals['sessions'] ?? 0)];
        if ($occurrences === 0) {
            return $details;
        }

        $slot = $this->period->end - $this->period->start <= 2 * 86400 ? '%Y-%m-%d %H:00:00' : '%Y-%m-%d';
        $details['trend'] = array_map(
            static fn(array $row): array => ['date' => (string) $row['slot'], 'occurrences' => (int) $row['occurrences']],
            $this->periodRows("SELECT strftime('{$slot}', timestamp) AS slot, COUNT(*) AS occurrences FROM user_activity WHERE {$where} GROUP BY slot ORDER BY slot", $params),
        );
        $details['pages'] = array_map(
            static fn(array $row): array => ['path' => (string) $row['path'], 'occurrences' => (int) $row['occurrences']],
            $this->periodRows("SELECT COALESCE(json_extract(action, '$.data.path'), '/') AS path, COUNT(*) AS occurrences FROM user_activity WHERE {$where} GROUP BY path ORDER BY occurrences DESC LIMIT " . max(1, $limit), $params),
        );

        $samples = $this->periodRows("SELECT action FROM user_activity WHERE {$where} ORDER BY timestamp DESC LIMIT " . self::EVENT_SAMPLE, $params);
        $counts = [];
        foreach ($samples as $row) {
            $data = json_decode((string) $row['action'], true)['data'] ?? null;
            foreach (is_array($data) ? $data : [] as $key => $value) {
                if (in_array($key, self::CONTEXT_FIELDS, true) || ($property !== null && $key !== $property)) {
                    continue;
                }
                $label = is_string($value) ? $value : (string) json_encode($value);
                $label = substr($label, 0, 200);
                $counts[$key][$label] = ($counts[$key][$label] ?? 0) + 1;
            }
        }
        $details['properties'] = [];
        foreach ($counts as $key => $values) {
            arsort($values);
            $details['properties'][$key] = array_map(
                static fn($value, int $count): array => ['value' => (string) $value, 'count' => $count],
                array_keys(array_slice($values, 0, $limit, true)),
                array_values(array_slice($values, 0, $limit, true)),
            );
        }
        $details['properties_based_on'] = count($samples);
        return $details;
    }

    /**
     * How many visits reached each step of a funnel, in order. A step is a page
     * (`pageview`) or a custom event; a visit counts for a step once it went
     * through every previous step first.
     *
     * @param list<array{type: string, value: string}> $steps
     * @return list<int>
     */
    public function funnelProgress(array $steps): array
    {
        $result = $this->db->prepare('SELECT session_id, action FROM user_activity WHERE timestamp >= :start AND timestamp <= :end' . $this->condition() . ' ORDER BY session_id, timestamp, id');
        $result->bindValue(':start', $this->period->startText(), SQLITE3_TEXT);
        $result->bindValue(':end', $this->period->endText(), SQLITE3_TEXT);
        $rows = $result->execute();
        // Number of consecutive steps each session has reached so far.
        $reached = [];
        while ($rows && ($row = $rows->fetchArray(SQLITE3_ASSOC))) {
            $session = $row['session_id'];
            $at = $reached[$session] ?? 0;
            if ($at >= count($steps)) {
                continue;
            }
            $action = json_decode($row['action'], true) ?: [];
            $name = (string) ($action['name'] ?? '');
            $path = (string) ($action['data']['path'] ?? '');
            $wanted = $steps[$at];
            $matches = $wanted['type'] === 'pageview' ? ($name === 'pageview' && $path === $wanted['value']) : ($name === $wanted['value']);
            if ($matches) {
                $reached[$session] = $at + 1;
            }
        }
        $progress = array_fill(0, count($steps), 0);
        foreach ($reached as $at) {
            for ($i = 0; $i < $at; $i++) {
                $progress[$i]++;
            }
        }
        return $progress;
    }

    /**
     * Funnels, goals and journeys saved in the dashboard.
     *
     * @return list<array{id: int, name: string, kind: string, steps: list<array{type: string, value: string, label?: string}>}>
     */
    public function savedFunnels(): array
    {
        // The table only exists once someone opened the funnels page: SQLite then
        // warns and returns false, PDO (MySQL) throws.
        try {
            $result = @$this->db->query('SELECT id, name, kind, steps FROM funnels ORDER BY created_at DESC, id DESC');
        } catch (Throwable) {
            return [];
        }
        $funnels = [];
        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $funnels[] = ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'kind' => (string) ($row['kind'] ?: 'funnel'), 'steps' => json_decode((string) $row['steps'], true) ?: []];
        }
        return $funnels;
    }

    /**
     * Browsers, operating systems or devices. These are audience dimensions:
     * a visitor is counted once for each value they used during the period.
     */
    public function environment(string $field, int $limit = 50): array
    {
        if (!in_array($field, self::ENVIRONMENT_FIELDS, true)) {
            throw new InvalidArgumentException('Unknown environment dimension: ' . $field);
        }
        $totalVisitors = $this->summary()['visitors'];
        $list = [];
        foreach ($this->periodRows("
            SELECT
                COALESCE(json_extract(action, '$.data.{$field}'), 'Unknown') as label,
                COUNT(DISTINCT COALESCE(visitor_id, session_id)) as count
            FROM user_activity
            WHERE timestamp >= :start_date AND timestamp <= :end_date
              AND json_extract(action, '$.name') = 'pageview'
              {$this->condition()}
            GROUP BY label
            ORDER BY count DESC
            LIMIT 50
        ") as $row) {
            $count = (int) $row['count'];
            $list[] = [
                'name' => (string) $row['label'],
                'count' => $count,
                'percentage' => $totalVisitors > 0 ? round(($count / $totalVisitors) * 100, 1) : 0,
            ];
        }
        return array_slice($list, 0, $limit);
    }

    /** Visitors per country. */
    public function countries(int $limit = 100): array
    {
        $totalVisitors = $this->summary()['visitors'];
        $countries = [];
        foreach ($this->periodRows("
            SELECT
                COALESCE(json_extract(action, '$.data.country'), 'Unknown') as country,
                COALESCE(json_extract(action, '$.data.country_code'), 'UN') as country_code,
                COUNT(DISTINCT COALESCE(visitor_id, session_id)) as count
            FROM user_activity
            WHERE timestamp >= :start_date AND timestamp <= :end_date
              AND json_extract(action, '$.name') = 'pageview'
              {$this->condition()}
            GROUP BY country, country_code
            ORDER BY count DESC
            LIMIT 100
        ") as $row) {
            $count = (int) $row['count'];
            $countries[] = [
                'name' => (string) $row['country'],
                'code' => strtoupper((string) $row['country_code']),
                'count' => $count,
                'percentage' => $totalVisitors > 0 ? round(($count / $totalVisitors) * 100, 1) : 0,
            ];
        }
        return array_slice($countries, 0, $limit);
    }

    /**
     * Sessions per source, medium, campaign, channel, landing and exit page.
     * Each session is attributed from its first pageview (exit page: its last).
     *
     * @return array<string, list<array{name: string, sessions: int}>>
     */
    public function acquisition(): array
    {
        $rows = $this->rows(
            "WITH pageviews AS (SELECT session_id, timestamp, id, action, ROW_NUMBER() OVER (PARTITION BY session_id ORDER BY timestamp,id) AS first_n, ROW_NUMBER() OVER (PARTITION BY session_id ORDER BY timestamp DESC,id DESC) AS last_n FROM user_activity WHERE timestamp BETWEEN :start AND :end AND json_extract(action,'$.name')='pageview'" . $this->condition() . ') SELECT session_id, action, first_n, last_n FROM pageviews WHERE first_n=1 OR last_n=1',
            [':start' => $this->period->startText(), ':end' => $this->period->endText()],
        );
        $reports = ['sources' => [],'mediums' => [],'campaigns' => [],'contents' => [],'terms' => [],'channels' => [],'landing_pages' => [],'exit_pages' => []];
        $inc = function (string $report, string $key) use (&$reports): void {
            $key = trim($key) ?: '(not set)';
            $reports[$report][$key] = ($reports[$report][$key] ?? 0) + 1;
        };
        foreach ($rows as $row) {
            $data = (json_decode($row['action'], true)['data'] ?? []);
            if ((int) $row['first_n'] === 1) {
                $utm = is_array($data['utm'] ?? null) ? $data['utm'] : [];
                $source = (string) ($data['utm_source'] ?? $utm['utm_source'] ?? '');
                $medium = (string) ($data['utm_medium'] ?? $utm['utm_medium'] ?? '');
                $ref = (string) ($data['referrer'] ?? '');
                $inc('sources', $source ?: ($ref ?: 'Direct'));
                $inc('mediums', $medium ?: ($ref ? 'referral' : '(none)'));
                $inc('campaigns', (string) ($data['utm_campaign'] ?? $utm['utm_campaign'] ?? ''));
                $inc('contents', (string) ($data['utm_content'] ?? $utm['utm_content'] ?? ''));
                $inc('terms', (string) ($data['utm_term'] ?? $utm['utm_term'] ?? ''));
                $inc('landing_pages', (string) ($data['path'] ?? '/'));
                $channel = $medium ? ucfirst(strtolower($medium)) : ($ref ? 'Referral' : 'Direct');
                // If the source or referrer is a search engine, attribute the session to the Organic Search channel.
                if (preg_match('/google|bing|duckduckgo|yahoo/', $source . ' ' . $ref)) {
                    $channel = 'Organic Search';
                } elseif (preg_match('/facebook|instagram|linkedin|twitter|tiktok/', $source . ' ' . $ref)) {
                    $channel = 'Social';
                }
                // Webmail clients commonly appear as referrers without UTM
                // parameters. Attribute those sessions to Email rather than the
                // generic Referral channel.
                elseif (preg_match('/email|newsletter/', $medium) || preg_match('/(^|\.)(mail\.google|gmail|outlook|outlook\.office|mail\.yahoo|mail\.proton|protonmail|mail\.icloud)\./i', $ref)) {
                    $channel = 'Email';
                } elseif (preg_match('/cpc|ppc|paid|display/', $medium)) {
                    $channel = 'Paid';
                }
                $inc('channels', $channel);
            }
            if ((int) $row['last_n'] === 1) {
                $inc('exit_pages', (string) ($data['path'] ?? '/'));
            }
        }
        foreach ($reports as $key => $list) {
            arsort($list);
            $reports[$key] = array_map(fn($name, $count) => ['name' => $name,'sessions' => $count], array_keys(array_slice($list, 0, 100, true)), array_values(array_slice($list, 0, 100, true)));
        }
        return $reports;
    }

    /** The 100 most recent requests rejected as bot traffic. */
    public function botActivity(): array
    {
        return $this->rows(
            'SELECT reason, user_agent, origin, timestamp FROM bot_activity WHERE timestamp BETWEEN :start AND :end ORDER BY id DESC LIMIT 100',
            [':start' => $this->period->startText(), ':end' => $this->period->endText()],
        );
    }

    /** SQL fragment (starting with " AND ") restricting a query to the sessions matching the filters. */
    private function condition(): string
    {
        // The matching window also covers the previous period used for deltas.
        return $this->condition ??= AnalyticsFilters::apply(
            $this->db,
            $this->filters,
            gmdate('Y-m-d H:i:s', $this->period->previousStart()),
            $this->period->endText(),
        );
    }

    /** Pageview, session and visitor counts between two dates. */
    private function totals(string $start, string $end, string $endOperator): array
    {
        $rows = $this->rows("
            SELECT
                COUNT(*) as total_events,
                SUM(CASE WHEN json_extract(action, '$.name') = 'pageview' THEN 1 ELSE 0 END) as pageviews,
                COUNT(DISTINCT session_id) as sessions,
                COUNT(DISTINCT COALESCE(visitor_id, session_id)) as visitors
            FROM user_activity
            WHERE timestamp >= :start_date AND timestamp {$endOperator} :end_date {$this->condition()}
        ", [':start_date' => $start, ':end_date' => $end]);
        return $rows[0] ?? [];
    }

    /**
     * Visit count, average duration and bounce rate between two dates. A visit
     * expires after 30 minutes without an event, so a browser tab left open for
     * hours does not inflate the average visit duration.
     */
    private function visitMetrics(string $start, string $end, string $endOperator): array
    {
        $rows = $this->rows("
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
                WHERE timestamp >= :start_date AND timestamp {$endOperator} :end_date {$this->condition()}
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
        ", [':start_date' => $start, ':end_date' => $end]);
        return $rows[0] ?? [];
    }

    /** Hostnames the website itself is served from, to exclude self-referrals. */
    private function ownDomains(): array
    {
        $ownDomains = [];
        foreach (Database::getAvailableSites() as $site) {
            if (($site['id'] ?? '') === $this->siteId) {
                $domain = self::normalizeDomain($site['domain'] ?? '');
                if ($domain !== '') {
                    $ownDomains[$domain] = true;
                }
                $siteId = self::normalizeDomain($site['id'] ?? '');
                if ($siteId !== '' && str_contains($siteId, '.')) {
                    $ownDomains[$siteId] = true;
                }
            }
        }
        try {
            $hosts = $this->db->query("
                SELECT DISTINCT json_extract(action, '$.data.hostname') as h
                FROM user_activity
                WHERE json_extract(action, '$.data.hostname') IS NOT NULL
                LIMIT 25
            ");
            if ($hosts) {
                while ($row = $hosts->fetchArray(SQLITE3_ASSOC)) {
                    $host = self::normalizeDomain((string) $row['h']);
                    if ($host !== '') {
                        $ownDomains[$host] = true;
                    }
                }
            }
        } catch (Throwable) {
        }
        return $ownDomains;
    }

    /** Normalizes a domain or URL for exact comparison (no scheme, port or "www."). */
    private static function normalizeDomain(?string $raw): string
    {
        if (!$raw) {
            return '';
        }
        $raw = trim(strtolower($raw));
        if (!str_contains($raw, '://')) {
            $raw = 'http://' . $raw;
        }
        $host = parse_url($raw, PHP_URL_HOST) ?: '';
        $host = preg_replace('/:\d+$/', '', $host); // strip port
        $host = preg_replace('/^www\./', '', $host); // strip www.
        return trim($host, '/');
    }

    private static function formatDiff(int|float $diff, string $suffix = ''): string
    {
        if ($diff > 0) {
            return "+{$diff}{$suffix}";
        }
        if ($diff < 0) {
            return "{$diff}{$suffix}";
        }
        return "0{$suffix}";
    }

    /**
     * Runs a query bound to the current period (`:start_date` / `:end_date`).
     *
     * @param array<string, string> $params other placeholders of the query
     */
    private function periodRows(string $sql, array $params = []): array
    {
        return $this->rows($sql, [':start_date' => $this->period->startText(), ':end_date' => $this->period->endText()] + $params);
    }

    /**
     * Case-insensitive "contains" condition on an SQL expression, or nothing
     * without a search. instr() avoids escaping LIKE wildcards.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private static function search(string $expression, ?string $search): array
    {
        $search = trim((string) $search);
        return $search === '' ? ['', []] : ["AND instr(lower({$expression}), lower(:search)) > 0", [':search' => $search]];
    }

    /**
     * @param array<string, string> $params
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, SQLITE3_TEXT);
        }
        $result = $stmt->execute();
        $rows = [];
        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $rows[] = $row;
        }
        return $rows;
    }
}
