<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function seedAnalyticsData(int $days = 7, bool $clearFirst = false): array {
    $db = Database::getConnection();

    if ($clearFirst) {
        $db->exec("DELETE FROM user_activity;");
    }

    $pages = [
        ['path' => '/', 'title' => 'Home - Minilytics', 'weight' => 35],
        ['path' => '/pricing', 'title' => 'Pricing & Plans', 'weight' => 25],
        ['path' => '/docs', 'title' => 'Documentation - Getting Started', 'weight' => 18],
        ['path' => '/features', 'title' => 'Features Overview', 'weight' => 12],
        ['path' => '/signup', 'title' => 'Create Account', 'weight' => 7],
        ['path' => '/blog/launch', 'title' => 'Introducing Minilytics v1.0', 'weight' => 3],
    ];

    $referrers = [
        'https://google.com' => 30,
        'https://news.ycombinator.com' => 20,
        'https://github.com' => 18,
        'https://twitter.com' => 12,
        'https://reddit.com/r/webdev' => 8,
        '' => 12 // Direct
    ];

    $customEvents = [
        ['name' => 'signup_click', 'data' => ['plan' => 'pro', 'cta' => 'hero_button']],
        ['name' => 'signup_click', 'data' => ['plan' => 'starter', 'cta' => 'pricing_card']],
        ['name' => 'copy_snippet', 'data' => ['snippet_type' => 'html_script', 'lang' => 'javascript']],
        ['name' => 'docs_search', 'data' => ['query' => 'session tracking', 'results' => 4]],
        ['name' => 'docs_search', 'data' => ['query' => 'privacy compliance', 'results' => 2]],
        ['name' => 'theme_toggle', 'data' => ['mode' => 'dark']],
        ['name' => 'demo_interaction', 'data' => ['button' => 'test_beacon', 'speed_ms' => 42]],
        ['name' => 'contact_sales', 'data' => ['company_size' => '25-50', 'interest' => 'self-hosted']]
    ];

    $pickWeighted = function(array $items) {
        $total = 0;
        foreach ($items as $k => $item) {
            $total += is_array($item) ? ($item['weight'] ?? 1) : $item;
        }
        $rand = mt_rand(1, max(1, $total));
        $curr = 0;
        foreach ($items as $k => $item) {
            $curr += is_array($item) ? ($item['weight'] ?? 1) : $item;
            if ($rand <= $curr) {
                return is_array($item) && isset($item['weight']) ? $item : $k;
            }
        }
        return is_array($items[0]) ? $items[0] : array_key_first($items);
    };

    $now = time();
    $startTime = $now - ($days * 86400);

    $db->exec('BEGIN TRANSACTION;');
    $stmt = $db->prepare("INSERT INTO user_activity (session_id, action, timestamp) VALUES (:session_id, :action, :timestamp)");

    $totalInserted = 0;
    $totalSessions = 0;

    // Generate sessions across the days
    $numSessions = $days * 60 + mt_rand(20, 50);

    for ($s = 0; $s < $numSessions; $s++) {
        $sessTime = mt_rand($startTime, $now);
        // Hour of the day affects volume: curve with night dip and day peak
        $hour = (int)date('G', $sessTime);
        $hourProb = 0.2 + 0.8 * sin(max(0, ($hour - 4)) / 19.0 * M_PI);
        if (mt_rand(1, 100) > ($hourProb * 100)) {
            // Skips low traffic hours with probability to model natural diurnal cycle
            continue;
        }

        $sessionId = bin2hex(random_bytes(16));
        $totalSessions++;
        $referrer = $pickWeighted($referrers);

        // Decide session length (number of actions)
        $isBounce = (mt_rand(1, 100) <= 24); // ~24% bounce rate
        $actionCount = $isBounce ? 1 : mt_rand(2, 6);

        $currTimestamp = $sessTime;

        for ($a = 0; $a < $actionCount; $a++) {
            $isCustom = ($a > 0 && mt_rand(1, 100) <= 30);

            if ($isCustom) {
                $evt = $customEvents[array_rand($customEvents)];
                $actionPayload = [
                    'site_id' => 'minilytics_prod',
                    'session_id' => $sessionId,
                    'name' => $evt['name'],
                    'data' => $evt['data']
                ];
            } else {
                $page = $pickWeighted($pages);
                $actionPayload = [
                    'site_id' => 'minilytics_prod',
                    'session_id' => $sessionId,
                    'name' => 'pageview',
                    'data' => [
                        'path' => $page['path'],
                        'title' => $page['title'],
                        'referrer' => ($a === 0 && !empty($referrer)) ? $referrer : null
                    ]
                ];
            }

            $dateStr = gmdate('Y-m-d H:i:s', $currTimestamp);
            $stmt->bindValue(':session_id', $sessionId, SQLITE3_TEXT);
            $stmt->bindValue(':action', json_encode($actionPayload, JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
            $stmt->bindValue(':timestamp', $dateStr, SQLITE3_TEXT);
            $stmt->execute();
            $stmt->reset();

            $totalInserted++;

            // User spends 15s to 90s before next action
            $currTimestamp += mt_rand(15, 90);
            if ($currTimestamp > $now) break;
        }
    }

    // Always add a few live visitors right now (last 3 minutes)
    for ($live = 0; $live < 4; $live++) {
        $liveSessId = bin2hex(random_bytes(16));
        $liveTime = $now - mt_rand(10, 150);
        $page = $pickWeighted($pages);
        $payload = [
            'site_id' => 'minilytics_prod',
            'session_id' => $liveSessId,
            'name' => 'pageview',
            'data' => [
                'path' => $page['path'],
                'title' => $page['title'],
                'referrer' => 'https://news.ycombinator.com'
            ]
        ];
        $stmt->bindValue(':session_id', $liveSessId, SQLITE3_TEXT);
        $stmt->bindValue(':action', json_encode($payload, JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $stmt->bindValue(':timestamp', gmdate('Y-m-d H:i:s', $liveTime), SQLITE3_TEXT);
        $stmt->execute();
        $stmt->reset();
        $totalInserted++;
    }

    $db->exec('COMMIT;');

    return [
        'success' => true,
        'inserted_events' => $totalInserted,
        'sessions' => $totalSessions,
        'days' => $days
    ];
}

// If run from web API or CLI
if (php_sapi_name() === 'cli') {
    echo "Seeding analytics data...\n";
    $res = seedAnalyticsData(7, true);
    echo "Done! Inserted {$res['inserted_events']} events across {$res['sessions']} sessions.\n";
} elseif (isset($_GET['run']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $clear = isset($_GET['clear']) || isset($_POST['clear']);
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
    $res = seedAnalyticsData($days, $clear);
    echo json_encode($res);
    exit;
}
