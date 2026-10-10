<?php

declare(strict_types=1);

namespace Minilytics\Mcp;

use Closure;
use InvalidArgumentException;
use Minilytics\Analytics\AnalyticsFilters;
use Minilytics\Analytics\Period;
use Minilytics\Analytics\SiteAnalytics;
use Minilytics\Database\Database;

/**
 * The analytics exposed to AI assistants over MCP.
 *
 * Every report tool shares the same `site_id`, period and filter arguments and
 * reads its numbers from SiteAnalytics: exposing a new report is one more
 * entry in all().
 */
final class AnalyticsTools
{
    private const ACQUISITION_REPORTS = ['channels', 'sources', 'mediums', 'campaigns', 'contents', 'terms', 'landing_pages', 'exit_pages'];
    private const FILTER_DIMENSIONS = ['page', 'referrer', 'browser', 'os', 'device', 'country'];

    /** @return list<Tool> */
    public static function all(): array
    {
        $limit = ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10, 'description' => 'Maximum number of rows to return.']];

        return [
            new Tool(
                'list_sites',
                'List websites',
                'Lists the websites tracked by this Minilytics instance. Call it first: every other tool takes one of these site_id values.',
                [],
                [],
                static fn(): array => ['sites' => array_map(static fn(array $site): array => [
                    'site_id' => $site['id'],
                    'name' => $site['name'] ?? $site['id'],
                    'domain' => $site['domain'] ?? '',
                ], Database::getAvailableSites())],
            ),
            self::report(
                'get_overview',
                'Traffic overview',
                'Visitors, visits, pageviews, events, bounce rate (%), average visit duration (seconds) and visitors active in the last 5 minutes. Deltas compare with the previous period of the same length.',
                static fn(SiteAnalytics $analytics): array => ['summary' => $analytics->summary()],
            ),
            self::report(
                'get_timeseries',
                'Traffic over time',
                'Pageviews, visits, visitors and events per hour (periods up to 2 days), per day, or per week (periods over a year).',
                static fn(SiteAnalytics $analytics): array => ['timeseries' => array_map(
                    static fn(array $slot): array => ['date' => $slot['full_label']] + array_intersect_key($slot, array_flip(['timestamp', 'pageviews', 'sessions', 'visitors', 'events'])),
                    $analytics->timeseries(),
                )],
            ),
            self::report(
                'get_top_pages',
                'Top pages',
                'Most viewed pages with their views, unique visitors and share of all pageviews.',
                static fn(SiteAnalytics $analytics, array $args): array => ['pages' => $analytics->topPages(self::limit($args))],
                $limit,
            ),
            self::report(
                'get_top_referrers',
                'Top referrers',
                'Websites sending traffic, by pageviews ("Direct / None" when there is no referrer). The website\'s own domains are excluded.',
                static fn(SiteAnalytics $analytics, array $args): array => ['referrers' => $analytics->topReferrers(self::limit($args))],
                $limit,
            ),
            self::report(
                'get_top_events',
                'Top custom events',
                'Custom events (everything except pageviews) by number of occurrences.',
                static fn(SiteAnalytics $analytics, array $args): array => ['events' => $analytics->topEvents(self::limit($args))],
                $limit,
            ),
            self::report(
                'get_countries',
                'Visitors by country',
                'Unique visitors per country, with their share of all visitors.',
                static fn(SiteAnalytics $analytics, array $args): array => ['countries' => $analytics->countries(self::limit($args))],
                $limit,
            ),
            self::report(
                'get_environment',
                'Browsers, operating systems and devices',
                'Unique visitors per browser, operating system or device type. A visitor using several values is counted once for each.',
                static function (SiteAnalytics $analytics, array $args): array {
                    $dimension = self::choice($args, 'dimension', SiteAnalytics::ENVIRONMENT_FIELDS);
                    return ['dimension' => $dimension, 'values' => $analytics->environment($dimension, self::limit($args))];
                },
                ['dimension' => ['type' => 'string', 'enum' => SiteAnalytics::ENVIRONMENT_FIELDS]] + $limit,
                ['dimension'],
            ),
            self::report(
                'get_acquisition',
                'Acquisition',
                'Visits per marketing channel, source, UTM medium/campaign/content/term, landing page or exit page. Each visit is attributed from its first pageview.',
                static function (SiteAnalytics $analytics, array $args): array {
                    $report = self::choice($args, 'report', self::ACQUISITION_REPORTS);
                    return ['report' => $report, 'values' => array_slice($analytics->acquisition()[$report], 0, self::limit($args))];
                },
                ['report' => ['type' => 'string', 'enum' => self::ACQUISITION_REPORTS]] + $limit,
                ['report'],
            ),
        ];
    }

    /**
     * A tool reporting on one website over one period.
     *
     * @param Closure(SiteAnalytics, array<string, mixed>): array<string, mixed> $report
     * @param array<string, array<string, mixed>> $properties arguments added to the shared ones
     * @param list<string> $required
     */
    private static function report(string $name, string $title, string $description, Closure $report, array $properties = [], array $required = []): Tool
    {
        return new Tool($name, $title, $description, self::sharedProperties() + $properties, $required, static function (array $args) use ($report): array {
            $analytics = SiteAnalytics::open(self::siteId($args), self::period($args), self::filters($args));
            return [
                'site_id' => $analytics->siteId,
                'period' => ['range' => $analytics->period->range, 'start' => gmdate('c', $analytics->period->start), 'end' => gmdate('c', $analytics->period->end)],
            ] + $report($analytics, $args);
        });
    }

    /** @return array<string, array<string, mixed>> */
    private static function sharedProperties(): array
    {
        $values = ['type' => 'array', 'items' => ['type' => 'string']];
        return [
            'site_id' => ['type' => 'string', 'description' => 'Website ID from list_sites. Optional when the instance tracks a single website.'],
            'range' => ['type' => 'string', 'enum' => Period::RANGES, 'default' => '7d', 'description' => 'Period ending now ("6m" is 180 days), or "custom" with from/to.'],
            'from' => ['type' => 'string', 'format' => 'date', 'description' => 'First day (YYYY-MM-DD, UTC) of a custom period.'],
            'to' => ['type' => 'string', 'format' => 'date', 'description' => 'Last day (YYYY-MM-DD, UTC) of a custom period, included.'],
            'filters' => [
                'type' => 'object',
                'description' => 'Only count visits that viewed a matching page. Values of one dimension are combined with OR, dimensions with AND. Referrers are domains such as "google.com" or "direct".',
                'properties' => array_fill_keys(self::FILTER_DIMENSIONS, $values),
                'additionalProperties' => false,
            ],
        ];
    }

    private static function siteId(array $args): string
    {
        if (isset($args['site_id']) && is_string($args['site_id']) && $args['site_id'] !== '') {
            return $args['site_id'];
        }
        $sites = Database::getAvailableSites();
        if (count($sites) === 1) {
            return (string) $sites[0]['id'];
        }
        throw new InvalidArgumentException('site_id is required: call list_sites to get the available websites.');
    }

    private static function period(array $args): Period
    {
        $range = $args['range'] ?? '7d';
        if (!is_string($range) || !in_array($range, Period::RANGES, true)) {
            throw new InvalidArgumentException('range must be one of: ' . implode(', ', Period::RANGES) . '.');
        }
        foreach (['from', 'to'] as $key) {
            if (isset($args[$key]) && (!is_string($args[$key]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $args[$key]))) {
                throw new InvalidArgumentException("{$key} must be a date formatted as YYYY-MM-DD.");
            }
        }
        if ($range === 'custom' && (empty($args['from']) || empty($args['to']))) {
            throw new InvalidArgumentException('A custom range needs both from and to.');
        }
        return Period::from($range, $args['from'] ?? null, $args['to'] ?? null);
    }

    /** @return array<string, string[]> */
    private static function filters(array $args): array
    {
        $filters = is_array($args['filters'] ?? null) ? $args['filters'] : [];
        $query = [];
        foreach (self::FILTER_DIMENSIONS as $dimension) {
            if (isset($filters[$dimension])) {
                $query['filter_' . $dimension] = $filters[$dimension];
            }
        }
        return AnalyticsFilters::fromRequest($query);
    }

    private static function limit(array $args): int
    {
        return max(1, min(100, (int) ($args['limit'] ?? 10)));
    }

    /** @param list<string> $allowed */
    private static function choice(array $args, string $key, array $allowed): string
    {
        $value = $args[$key] ?? null;
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new InvalidArgumentException("{$key} must be one of: " . implode(', ', $allowed) . '.');
        }
        return $value;
    }
}
