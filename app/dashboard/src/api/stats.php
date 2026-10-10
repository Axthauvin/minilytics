<?php

declare(strict_types=1);

use Minilytics\Analytics\AnalyticsFilters;
use Minilytics\Analytics\Period;
use Minilytics\Analytics\SiteAnalytics;
use Minilytics\Auth\Auth;
use Minilytics\Database\Database;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../../vendor/autoload.php';
// Public demo sites are readable without an account.
Auth::requireSiteAccess((string) ($_GET['site_id'] ?? $_GET['site'] ?? ''));


try {
    $analytics = SiteAnalytics::open($_GET['site_id'] ?? $_GET['site'] ?? null, Period::fromRequest($_GET), AnalyticsFilters::fromRequest($_GET));
    $availableSites = Database::getAvailableSites();
    if (Auth::isGuest()) {
        $availableSites = array_values(array_map([Database::class, 'guestSiteView'], array_filter($availableSites, static fn(array $s): bool => !empty($s['is_public']))));
    }

    echo json_encode([
        'success' => true,
        'range' => $analytics->period->range,
        'site_id' => $analytics->siteId,
        'available_sites' => $availableSites,
        'filters' => (object) $analytics->filters,
        'summary' => $analytics->summary(),
        'timeseries' => $analytics->timeseries(),
        'top_pages' => $analytics->topPages(),
        'top_referrers' => $analytics->topReferrers(),
        'top_events' => $analytics->topEvents(),
        'environment' => [
            'browsers' => $analytics->environment('browser'),
            'os' => $analytics->environment('os'),
            'devices' => $analytics->environment('device'),
        ],
        'countries' => $analytics->countries(),
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
