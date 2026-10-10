<?php

declare(strict_types=1);

use Minilytics\Analytics\Period;
use Minilytics\Auth\Auth;
use Minilytics\Database\Database;
use Minilytics\Database\DatabaseConnection;

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../vendor/autoload.php';
// Funnels of public demo sites are readable; creating or deleting them needs an account.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    Auth::requireSiteAccess((string) ($_GET['site_id'] ?? ''));
} else {
    Auth::requireLogin();
}

function setupFunnels(DatabaseConnection $db): void
{
    if ($db->isMysql()) {
        $db->exec("CREATE TABLE IF NOT EXISTS funnels (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, kind VARCHAR(20) NOT NULL DEFAULT 'funnel', steps JSON NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS funnels (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, kind TEXT NOT NULL DEFAULT 'funnel', steps TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $columns = $db->query('PRAGMA table_info(funnels)');
    $hasKind = false;
    while ($column = $columns->fetchArray(SQLITE3_ASSOC)) {
        if ($column['name'] === 'kind') {
            $hasKind = true;
        }
    }
    if (!$hasKind) {
        $db->exec("ALTER TABLE funnels ADD COLUMN kind TEXT NOT NULL DEFAULT 'funnel'");
    }
}
function cleanSteps(mixed $steps): array
{
    if (!is_array($steps) || count($steps) < 1 || count($steps) > 12) {
        throw new InvalidArgumentException('Add between 1 and 12 steps.');
    }
    $out = [];
    foreach ($steps as $step) {
        $type = ($step['type'] ?? '') === 'pageview' ? 'pageview' : 'event';
        $value = trim((string) ($step['value'] ?? ''));
        $label = trim((string) ($step['label'] ?? ''));
        if ($value === '') {
            throw new InvalidArgumentException('Every step needs an event or page.');
        }
        $out[] = ['type' => $type, 'value' => mb_substr($value, 0, 180), 'label' => mb_substr($label ?: $value, 0, 80)];
    }
    return $out;
}
function analyzeFunnel(DatabaseConnection $db, array $steps, string $start, string $end): array
{
    $stmt = $db->prepare("SELECT session_id, action FROM user_activity WHERE timestamp >= :start AND timestamp <= :end ORDER BY session_id, timestamp, id");
    $stmt->bindValue(':start', $start, SQLITE3_TEXT);
    $stmt->bindValue(':end', $end, SQLITE3_TEXT);
    $res = $stmt->execute();
    // Number of consecutive steps each session has reached so far.
    $reached = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
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
/** Adds one completed journey to the tree, counting each step along its branch. */
function addJourneyPath(array &$tree, array $branchPath): void
{
    $node = & $tree;
    $node['count']++;
    foreach ($branchPath as $branch) {
        if (!isset($node['children'][$branch['key']])) {
            $node['children'][$branch['key']] = ['type' => $branch['type'], 'value' => $branch['value'], 'count' => 0, 'children' => []];
        }
        $node = & $node['children'][$branch['key']];
        $node['count']++;
    }
    unset($node);
}
function analyzeJourneySources(DatabaseConnection $db, array $steps, string $start, string $end): array
{
    if (count($steps) < 2) {
        return ['entered' => 0, 'completed' => 0, 'tree' => ['count' => 0, 'children' => []]];
    }
    $stmt = $db->prepare("SELECT session_id, action FROM user_activity WHERE timestamp >= :start AND timestamp <= :end ORDER BY session_id, timestamp, id");
    $stmt->bindValue(':start', $start, SQLITE3_TEXT);
    $stmt->bindValue(':end', $end, SQLITE3_TEXT);
    $res = $stmt->execute();
    $entry = $steps[0];
    $exit = $steps[1];
    $currentSession = null;
    $hasEntered = false;
    $capturing = false;
    $branchPath = [];
    $entered = 0;
    $completed = 0;
    $tree = ['count' => 0, 'children' => []];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        if ($currentSession !== $row['session_id']) {
            $currentSession = $row['session_id'];
            $hasEntered = false;
            $capturing = false;
            $branchPath = [];
        }
        $action = json_decode($row['action'], true) ?: [];
        $name = (string) ($action['name'] ?? '');
        $actionPath = (string) ($action['data']['path'] ?? '');
        $isEntry = $entry['type'] === 'pageview' ? ($name === 'pageview' && $actionPath === $entry['value']) : ($name === $entry['value']);
        if ($isEntry && !$hasEntered) {
            $entered++;
            $hasEntered = true;
            $capturing = true;
            $branchPath = [];
            continue;
        }
        if (!$capturing) {
            continue;
        }
        $type = $name === 'pageview' ? 'pageview' : 'event';
        $value = $type === 'pageview' ? ($actionPath ?: '/') : $name;
        if ($value === '') {
            continue;
        }
        $branchPath[] = ['key' => $type . '|' . $value, 'type' => $type, 'value' => $value];
        $isExit = $exit['type'] === 'pageview' ? ($name === 'pageview' && $actionPath === $exit['value']) : ($name === $exit['value']);
        if ($isExit) {
            $completed++;
            addJourneyPath($tree, $branchPath);
            $capturing = false;
        } elseif (count($branchPath) >= 12) {
            $capturing = false;
        }
    }
    $trimTree = function (array $node) use (&$trimTree): array {
        $children = array_values($node['children'] ?? []);
        usort($children, fn($a, $b) => $b['count'] <=> $a['count']);
        $node['children'] = array_map($trimTree, array_slice($children, 0, 3));
        return $node;
    };
    return ['entered' => $entered, 'completed' => $completed, 'tree' => $trimTree($tree)];
}
try {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $siteId = $_GET['site_id'] ?? $payload['site_id'] ?? null;
    $db = Database::getConnection(Database::sanitizeSiteId($siteId));
    setupFunnels($db);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Give this funnel a name.');
        }
        $steps = cleanSteps($payload['steps'] ?? []);
        $kind = in_array($payload['kind'] ?? 'funnel', ['funnel', 'goal', 'journey'], true) ? $payload['kind'] : 'funnel';
        if ($kind === 'goal') {
            $steps = [reset($steps)];
        }
        if ($kind === 'journey') {
            if (count($steps) < 2) {
                throw new InvalidArgumentException('A journey needs an entry and an exit.');
            } $steps = [$steps[0], $steps[1]];
        }
        $id = (int) ($payload['id'] ?? 0);
        if ($id) {
            $st = $db->prepare('UPDATE funnels SET name=:name, kind=:kind, steps=:steps WHERE id=:id');
            $st->bindValue(':id', $id, SQLITE3_INTEGER);
        } else {
            $st = $db->prepare('INSERT INTO funnels (name, kind, steps) VALUES (:name, :kind, :steps)');
        }
        $st->bindValue(':name', mb_substr($name, 0, 80), SQLITE3_TEXT);
        $st->bindValue(':kind', $kind, SQLITE3_TEXT);
        $st->bindValue(':steps', json_encode($steps), SQLITE3_TEXT);
        $st->execute();
        echo json_encode(['success' => true, 'id' => $id ?: $db->lastInsertRowID()]);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        $st = $db->prepare('DELETE FROM funnels WHERE id=:id');
        $st->bindValue(':id', $id, SQLITE3_INTEGER);
        $st->execute();
        echo json_encode(['success' => true]);
        exit;
    }
    $period = Period::fromRequest($_GET);
    [$start, $end] = [$period->startText(), $period->endText()];
    $items = [];
    $result = $db->query('SELECT * FROM funnels ORDER BY created_at DESC, id DESC');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $steps = json_decode($row['steps'], true) ?: [];
        $kind = $row['kind'] ?: 'funnel';
        $item = ['id' => (int) $row['id'], 'name' => $row['name'], 'kind' => $kind, 'steps' => $steps, 'progress' => analyzeFunnel($db, $steps, $start, $end)];
        if ($kind === 'journey') {
            $item['journey'] = analyzeJourneySources($db, $steps, $start, $end);
            if (($item['journey']['completed'] ?? 0) === 0) {
                $item['journey']['fallback'] = analyzeJourneySources($db, $steps, '1970-01-01 00:00:00', '9999-12-31 23:59:59');
            }
        } $items[] = $item;
    }
    // The builder exposes the site's complete vocabulary. The selected period
    // only affects the funnel's figures, never which event/page can be chosen.
    $events = [];
    $er = $db->query("SELECT DISTINCT json_extract(action, '$.name') AS name FROM user_activity ORDER BY name");
    while ($r = $er->fetchArray(SQLITE3_ASSOC)) {
        if ($r['name'] && $r['name'] !== 'pageview') {
            $events[] = $r['name'];
        }
    }
    $pages = [];
    $pr = $db->query("SELECT DISTINCT json_extract(action, '$.data.path') AS path FROM user_activity WHERE json_extract(action, '$.name')='pageview' ORDER BY path");
    while ($r = $pr->fetchArray(SQLITE3_ASSOC)) {
        if ($r['path'] !== null && $r['path'] !== '') {
            $pages[] = $r['path'];
        }
    }
    $audienceStmt = $db->prepare('SELECT COUNT(DISTINCT COALESCE(visitor_id, session_id)) FROM user_activity WHERE timestamp >= :start AND timestamp <= :end');
    $audienceStmt->bindValue(':start', $start, SQLITE3_TEXT);
    $audienceStmt->bindValue(':end', $end, SQLITE3_TEXT);
    $audience = (int) $audienceStmt->execute()->fetchArray(SQLITE3_NUM)[0];
    echo json_encode(['success' => true,'funnels' => $items,'audience' => $audience,'events' => $events,'pages' => $pages]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
