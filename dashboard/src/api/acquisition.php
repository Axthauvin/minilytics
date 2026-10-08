<?php

declare(strict_types=1);

use Minilytics\Auth\Auth;
use Minilytics\Database\Database;

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../vendor/autoload.php';
Auth::requireSiteAccess((string) ($_GET['site_id'] ?? ''));
try {
    $site = Database::sanitizeSiteId($_GET['site_id'] ?? null);
    $db = Database::getConnection($site);
    $range = $_GET['range'] ?? '7d';
    $now = time();
    $days = ['today' => 0,'7d' => 7,'30d' => 30,'90d' => 90,'6m' => 180];
    if ($range === 'custom' && !empty($_GET['from']) && !empty($_GET['to'])) {
        $start = strtotime($_GET['from'] . ' 00:00:00 UTC');
        $end = strtotime($_GET['to'] . ' 23:59:59 UTC');
    } else {
        $start = $range === 'all' ? 0 : $now - (($days[$range] ?? 7) * 86400);
        $end = $now;
    }
    $startText = gmdate('Y-m-d H:i:s', $start);
    $endText = gmdate('Y-m-d H:i:s', $end);
    $rows = $db->prepare("WITH pageviews AS (SELECT session_id, timestamp, id, action, ROW_NUMBER() OVER (PARTITION BY session_id ORDER BY timestamp,id) AS first_n, ROW_NUMBER() OVER (PARTITION BY session_id ORDER BY timestamp DESC,id DESC) AS last_n FROM user_activity WHERE timestamp BETWEEN :start AND :end AND json_extract(action,'$.name')='pageview') SELECT session_id, action, first_n, last_n FROM pageviews WHERE first_n=1 OR last_n=1");
    $rows->bindValue(':start', $startText, SQLITE3_TEXT);
    $rows->bindValue(':end', $endText, SQLITE3_TEXT);
    $result = $rows->execute();
    $reports = ['sources' => [],'mediums' => [],'campaigns' => [],'contents' => [],'terms' => [],'channels' => [],'landing_pages' => [],'exit_pages' => []];
    $inc = function (string $report, string $key) use (&$reports): void {
        $key = trim($key) ?: '(not set)';
        $reports[$report][$key] = ($reports[$report][$key] ?? 0) + 1;
    };
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
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
    $bot = $db->prepare('SELECT reason, user_agent, origin, timestamp FROM bot_activity WHERE timestamp BETWEEN :start AND :end ORDER BY id DESC LIMIT 100');
    $bot->bindValue(':start', $startText, SQLITE3_TEXT);
    $bot->bindValue(':end', $endText, SQLITE3_TEXT);
    $br = $bot->execute();
    $bots = [];
    while ($r = $br->fetchArray(SQLITE3_ASSOC)) {
        $bots[] = $r;
    }
    echo json_encode(['success' => true,'reports' => $reports,'bots' => $bots], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
