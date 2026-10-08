<?php

declare(strict_types=1);

use Minilytics\Database\Database;
use Minilytics\Database\DatabaseConnection;

/**
 * Minilytics live demo seeder.
 *
 * Generates realistic, recent traffic for the public demo website so every
 * dashboard tab (Overview, Acquisition, Events, Sessions, Funnels) has data,
 * and flags the website as public so anonymous visitors can browse it.
 *
 * Usage:
 *   php bin/seed-demo.php [--site=demo_site] [--days=30] [--reset]
 *                                  [--live-only] [--domain=example.com ...]
 *
 *   --reset      Delete the site's existing events and funnels first.
 *   --live-only  Only add a handful of visitors active in the last 5 minutes
 *                (handy from a cron job to keep the "live" counter alive).
 *   --domain     Extra hostname allowed to send tracking hits (repeatable),
 *                e.g. the production domain serving the landing page.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

$options = getopt('', ['site::', 'days::', 'reset', 'live-only', 'domain::', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "Usage: php bin/seed-demo.php [--site=demo_site] [--days=30] [--reset] [--live-only] [--domain=example.com]\n");
    exit(0);
}
$siteId = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) ($options['site'] ?? 'demo_site')));
$days = max(1, min(365, (int) ($options['days'] ?? 30)));
$extraDomains = array_filter((array) ($options['domain'] ?? []), 'is_string');

// ---------------------------------------------------------------------------
// 1. Website configuration: create if missing, flag as public, allow local hosts
// ---------------------------------------------------------------------------
if (!Database::trackingSite($siteId)) {
    Database::createSite($siteId, 'Minilytics Demo', 'localhost');
    fwrite(STDOUT, "Created website '{$siteId}'.\n");
}
$site = Database::trackingSite($siteId);
$allowed = array_merge((array) ($site['allowed_domains'] ?? []), ['localhost', '127.0.0.1'], $extraDomains);
Database::updateSiteConfig($siteId, ['is_public' => true, 'allowed_domains' => $allowed]);
fwrite(STDOUT, "Website '{$siteId}' is public (allowed domains: " . implode(', ', array_unique(array_map([Database::class, 'normalizeHost'], $allowed))) . ").\n");

$db = Database::getConnection($siteId);
ensureFunnelsTable($db);

if (isset($options['reset']) && !isset($options['live-only'])) {
    $db->exec('DELETE FROM user_activity');
    $db->exec('DELETE FROM funnels');
    fwrite(STDOUT, "Existing analytics data deleted.\n");
}

// ---------------------------------------------------------------------------
// 2. Traffic model
// ---------------------------------------------------------------------------
const PAGES = [
    '/' => 'Minilytics: private, GDPR-friendly web analytics',
    '/install.html' => 'Install Minilytics',
    '/docs/' => 'Documentation · Minilytics',
    '/docs/tracking/' => 'Tracking guide · Minilytics',
    '/docs/importing/' => 'Importing data · Minilytics',
    '/docs/privacy/' => 'Privacy & GDPR · Minilytics',
    '/blog/cookieless-analytics/' => 'Why cookieless analytics matter · Minilytics',
    '/blog/shared-hosting/' => 'Self-hosting analytics on shared hosting · Minilytics',
    '/changelog/' => 'Changelog · Minilytics',
];

// [weight, referrer host|null, utm parameters]
const SOURCES = [
    [30, null, []],
    [22, 'google.com', []],
    [14, 'github.com', []],
    [8, 'twitter.com', []],
    [7, 'news.ycombinator.com', []],
    [7, null, ['utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'october-release']],
    [4, 'reddit.com', []],
    [4, 'duckduckgo.com', []],
    [2, 'linkedin.com', ['utm_source' => 'linkedin', 'utm_medium' => 'social', 'utm_campaign' => 'launch']],
    [2, 'bing.com', []],
];

// Landing pages per source family: search lands on docs/blog, social on home.
const LANDINGS = [
    'search' => ['/' => 40, '/docs/' => 15, '/docs/tracking/' => 10, '/docs/privacy/' => 10, '/blog/cookieless-analytics/' => 15, '/blog/shared-hosting/' => 10],
    'default' => ['/' => 75, '/install.html' => 10, '/blog/cookieless-analytics/' => 8, '/changelog/' => 7],
];

// Next-page transitions (null = exit).
const NEXT = [
    '/' => ['/install.html' => 40, '/docs/' => 15, '/blog/cookieless-analytics/' => 7, '/changelog/' => 5, null => 33],
    '/install.html' => ['/docs/' => 18, '/docs/tracking/' => 22, '/' => 8, null => 52],
    '/docs/' => ['/docs/tracking/' => 35, '/docs/importing/' => 20, '/docs/privacy/' => 15, '/install.html' => 12, null => 18],
    '/docs/tracking/' => ['/docs/privacy/' => 15, '/install.html' => 20, '/docs/importing/' => 10, null => 55],
    '/docs/importing/' => ['/install.html' => 25, '/docs/' => 10, null => 65],
    '/docs/privacy/' => ['/install.html' => 20, '/' => 10, null => 70],
    '/blog/cookieless-analytics/' => ['/' => 35, '/blog/shared-hosting/' => 15, '/install.html' => 10, null => 40],
    '/blog/shared-hosting/' => ['/install.html' => 30, '/' => 15, null => 55],
    '/changelog/' => ['/install.html' => 25, '/' => 15, null => 60],
];

// [weight, code, name, [cities]]
const COUNTRIES = [
    [28, 'FR', 'France', ['Paris', 'Lyon', 'Toulouse', 'Bordeaux', 'Lille', 'Nantes']],
    [20, 'US', 'United States', ['New York', 'San Francisco', 'Seattle', 'Austin', 'Chicago']],
    [10, 'DE', 'Germany', ['Berlin', 'Munich', 'Hamburg', 'Cologne']],
    [8, 'GB', 'United Kingdom', ['London', 'Manchester', 'Edinburgh']],
    [5, 'CA', 'Canada', ['Montreal', 'Toronto', 'Vancouver']],
    [4, 'NL', 'Netherlands', ['Amsterdam', 'Rotterdam', 'Utrecht']],
    [4, 'ES', 'Spain', ['Madrid', 'Barcelona', 'Valencia']],
    [4, 'IT', 'Italy', ['Milan', 'Rome', 'Turin']],
    [4, 'IN', 'India', ['Bengaluru', 'Mumbai', 'Pune']],
    [3, 'BE', 'Belgium', ['Brussels', 'Ghent']],
    [3, 'CH', 'Switzerland', ['Zurich', 'Geneva', 'Lausanne']],
    [3, 'BR', 'Brazil', ['São Paulo', 'Rio de Janeiro']],
    [2, 'JP', 'Japan', ['Tokyo', 'Osaka']],
    [2, 'SE', 'Sweden', ['Stockholm', 'Gothenburg']],
];

// [weight, device, browser, os, screen]
const CLIENTS = [
    [26, 'Desktop', 'Chrome', 'Windows', '1920×1080'],
    [14, 'Desktop', 'Chrome', 'macOS', '1512×982'],
    [12, 'Desktop', 'Safari', 'macOS', '1440×900'],
    [11, 'Desktop', 'Firefox', 'Linux', '2560×1440'],
    [5, 'Desktop', 'Firefox', 'Windows', '1920×1080'],
    [4, 'Desktop', 'Microsoft Edge', 'Windows', '1536×864'],
    [13, 'Mobile', 'Safari', 'iOS', '393×852'],
    [11, 'Mobile', 'Chrome', 'Android', '412×915'],
    [2, 'Mobile', 'Samsung Internet', 'Android', '384×854'],
    [2, 'Tablet', 'Safari', 'iOS', '820×1180'],
];

const LANGUAGES = ['FR' => 'fr-FR', 'US' => 'en-US', 'DE' => 'de-DE', 'GB' => 'en-GB', 'CA' => 'en-CA', 'NL' => 'nl-NL', 'ES' => 'es-ES', 'IT' => 'it-IT', 'IN' => 'en-IN', 'BE' => 'fr-BE', 'CH' => 'de-CH', 'BR' => 'pt-BR', 'JP' => 'ja-JP', 'SE' => 'sv-SE'];

function pick(array $weighted): mixed
{
    $total = array_sum(array_column($weighted, 0));
    $roll = mt_rand(1, $total);
    foreach ($weighted as $row) {
        $roll -= $row[0];
        if ($roll <= 0) {
            return $row;
        }
    }
    return end($weighted);
}

function pickKey(array $weights): ?string
{
    $roll = mt_rand(1, (int) array_sum($weights));
    foreach ($weights as $key => $weight) {
        $roll -= $weight;
        if ($roll <= 0) {
            return $key === '' ? null : (string) $key;
        }
    }
    return null;
}

function randomId(): string
{
    return bin2hex(random_bytes(16));
}

function ensureFunnelsTable(DatabaseConnection $db): void
{
    // Mirrors setupFunnels() in dashboard/src/api/funnels.php.
    if ($db->isMysql()) {
        $db->exec("CREATE TABLE IF NOT EXISTS funnels (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, kind VARCHAR(20) NOT NULL DEFAULT 'funnel', steps JSON NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS funnels (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, kind TEXT NOT NULL DEFAULT 'funnel', steps TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
}

/** Builds one visit (list of [timestamp, name, data]) starting at $start. */
function buildSession(int $start, int $maxEnd, string $siteId): array
{
    [, $referrer, $utm] = pick(SOURCES);
    [, $code, $country, $cities] = pick(COUNTRIES);
    [, $device, $browser, $os, $screen] = pick(CLIENTS);
    $isSearch = in_array($referrer, ['google.com', 'duckduckgo.com', 'bing.com'], true);
    $path = pickKey(LANDINGS[$isSearch ? 'search' : 'default']);

    $base = [
        'hostname' => 'localhost',
        'language' => LANGUAGES[$code] ?? 'en-US',
        'screen' => $screen,
        '_ml_tracking_mode' => 'strict',
        'browser' => $browser,
        'os' => $os,
        'device' => $device,
        'country' => $country,
        'country_code' => $code,
        'region' => '',
        'city' => $cities[array_rand($cities)],
    ];

    $hits = [];
    $time = $start;
    $previous = $referrer;
    $first = true;
    $engaged = false;
    for ($depth = 0; $path !== null && $depth < 7 && $time <= $maxEnd; $depth++) {
        $data = ['path' => $path, 'title' => PAGES[$path], 'referrer' => $previous] + $base;
        if ($first) {
            $data += $utm;
        }
        $hits[] = [$time, 'pageview', $data];
        $first = false;
        $eventBase = ['path' => $path, 'title' => PAGES[$path], 'referrer' => 'localhost'] + $base;

        // Engagement ping after ~10 s on most visits.
        if (!$engaged && mt_rand(1, 100) <= 72) {
            $engaged = true;
            $hits[] = [$time + 10, '_ml_engaged', $eventBase];
        }

        // Custom events tied to the page being read.
        if ($path === '/install.html' && mt_rand(1, 100) <= 55) {
            $hits[] = [$time + mt_rand(15, 60), 'copy_snippet', $eventBase + ['snippet' => 'install-command']];
            if (mt_rand(1, 100) <= 62) {
                $hits[] = [$time + mt_rand(61, 140), 'download_zip', $eventBase + ['file_name' => 'minilytics.tar.gz', 'version' => 'v1.4.0']];
            }
        }
        if (str_starts_with($path, '/docs/') && $path !== '/docs/' && mt_rand(1, 100) <= 30) {
            $hits[] = [$time + mt_rand(20, 90), 'copy_snippet', $eventBase + ['snippet' => 'tracking-script']];
        }
        if (mt_rand(1, 100) <= 9) {
            $hits[] = [$time + mt_rand(5, 40), 'theme_toggle', $eventBase + ['theme' => mt_rand(0, 3) ? 'dark' : 'light']];
        }
        if (mt_rand(1, 100) <= 7) {
            $hits[] = [$time + mt_rand(8, 50), 'outbound_click', $eventBase + ['target_url' => 'https://github.com/axthauvin/minilytics', 'target_host' => 'github.com', 'link_text' => 'Explore on GitHub']];
        }

        $previous = 'localhost';
        $time += mt_rand(25, 210);
        $path = pickKey(NEXT[$path] ?? ['' => 1]);
    }

    $hits = array_values(array_filter($hits, static fn(array $hit): bool => $hit[0] <= $maxEnd));
    usort($hits, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
    return $hits;
}

// Hour-of-day traffic shape (UTC, European audience peaking mid-afternoon).
const HOURLY = [1, 1, 1, 1, 1, 2, 3, 5, 7, 9, 10, 10, 9, 10, 11, 11, 10, 9, 8, 7, 6, 4, 3, 2];

function randomTimeInDay(int $dayStart, int $now): ?int
{
    $limit = min($dayStart + 86399, $now - 360);
    if ($limit < $dayStart) {
        return null;
    }
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $weights = [];
        foreach (HOURLY as $hour => $w) {
            $weights[(string) $hour] = $w;
        }
        $hour = (int) pickKey($weights);
        $ts = $dayStart + $hour * 3600 + mt_rand(0, 3599);
        if ($ts <= $limit) {
            return $ts;
        }
    }
    return mt_rand($dayStart, $limit);
}

// ---------------------------------------------------------------------------
// 3. Generate & insert
// ---------------------------------------------------------------------------
$now = time();
$insert = $db->prepare('INSERT INTO user_activity (session_id, visitor_id, action, timestamp) VALUES (:session, :visitor, :action, :ts)');
$visitorPool = [];
$events = 0;
$sessions = 0;

$store = static function (array $hits, string $visitor) use ($insert, $siteId, &$events, &$sessions): void {
    if (!$hits) {
        return;
    }
    $session = randomId();
    foreach ($hits as [$ts, $name, $data]) {
        $insert->bindValue(':session', $session, SQLITE3_TEXT);
        $insert->bindValue(':visitor', $visitor, SQLITE3_TEXT);
        $insert->bindValue(':action', json_encode(['site_id' => $siteId, 'name' => $name, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
        $insert->bindValue(':ts', gmdate('Y-m-d H:i:s', $ts), SQLITE3_TEXT);
        $insert->execute();
        $events++;
    }
    $sessions++;
};

$visitorFor = static function () use (&$visitorPool): string {
    // About a quarter of visits come from returning visitors.
    if ($visitorPool && mt_rand(1, 100) <= 25) {
        return $visitorPool[array_rand($visitorPool)];
    }
    $id = 'vid_' . substr(randomId(), 0, 12);
    $visitorPool[] = $id;
    return $id;
};

$db->exec('BEGIN');
try {
    if (!isset($options['live-only'])) {
        $spikeDay = min($days - 1, 11); // A "Hacker News launch" a dozen days ago.
        for ($d = $days - 1; $d >= 0; $d--) {
            $dayStart = strtotime(gmdate('Y-m-d 00:00:00', $now - $d * 86400) . ' UTC');
            $weekday = (int) gmdate('N', $dayStart);
            $growth = 1 + ($days - $d) / $days * 0.6;              // steady growth
            $weekend = $weekday >= 6 ? 0.6 : 1.0;                   // quieter weekends
            $spike = $d === $spikeDay ? 3.2 : ($d === $spikeDay - 1 ? 1.8 : 1.0);
            $count = (int) round(38 * $growth * $weekend * $spike * (mt_rand(85, 115) / 100));
            if ($d === 0) {
                $count = (int) round($count * min(1, ($now - $dayStart) / 86400 + 0.1));
            }
            for ($i = 0; $i < $count; $i++) {
                $start = randomTimeInDay($dayStart, $now);
                if ($start === null) {
                    continue;
                }
                $store(buildSession($start, $now - 300, $siteId), $visitorFor());
            }
        }
    }

    // Visitors active right now (within the 5-minute live window).
    $live = mt_rand(3, 7);
    for ($i = 0; $i < $live; $i++) {
        $store(buildSession($now - mt_rand(20, 240), $now, $siteId), $visitorFor());
    }
    $db->exec('COMMIT');
} catch (Throwable $e) {
    $db->exec('ROLLBACK');
    fwrite(STDERR, 'Seeding failed: ' . $e->getMessage() . "\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// 4. Predefined funnels
// ---------------------------------------------------------------------------
$funnels = [
    ['Install conversion', 'funnel', [
        ['type' => 'pageview', 'value' => '/', 'label' => 'Landing page'],
        ['type' => 'pageview', 'value' => '/install.html', 'label' => 'Install guide'],
        ['type' => 'event', 'value' => 'copy_snippet', 'label' => 'Copied command'],
        ['type' => 'event', 'value' => 'download_zip', 'label' => 'Downloaded'],
    ]],
    ['Download', 'goal', [
        ['type' => 'event', 'value' => 'download_zip', 'label' => 'Downloaded Minilytics'],
    ]],
    ['Docs to install', 'journey', [
        ['type' => 'pageview', 'value' => '/docs/', 'label' => 'Documentation'],
        ['type' => 'pageview', 'value' => '/install.html', 'label' => 'Install guide'],
    ]],
];
$existing = [];
$result = $db->query('SELECT name FROM funnels');
while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
    $existing[$row['name']] = true;
}
$funnelInsert = $db->prepare('INSERT INTO funnels (name, kind, steps) VALUES (:name, :kind, :steps)');
$createdFunnels = 0;
foreach ($funnels as [$name, $kind, $steps]) {
    if (isset($existing[$name])) {
        continue;
    }
    $funnelInsert->bindValue(':name', $name, SQLITE3_TEXT);
    $funnelInsert->bindValue(':kind', $kind, SQLITE3_TEXT);
    $funnelInsert->bindValue(':steps', json_encode($steps), SQLITE3_TEXT);
    $funnelInsert->execute();
    $createdFunnels++;
}

fwrite(STDOUT, "Inserted {$events} events across {$sessions} sessions ({$live} live now) and {$createdFunnels} funnels into '{$siteId}'.\n");
