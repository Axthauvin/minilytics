<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth.php';
Auth::requireLogin();
require_once __DIR__ . '/db.php';

function funnelRange(): array {
    $range = $_GET['range'] ?? '7d'; $now = time();
    if (($range === 'custom') && !empty($_GET['from']) && !empty($_GET['to'])) {
        $start = strtotime($_GET['from'] . ' 00:00:00 UTC') ?: $now - 30 * 86400;
        $end = strtotime($_GET['to'] . ' 23:59:59 UTC') ?: $now;
    } else {
        $start = match ($range) { 'today' => strtotime('today midnight UTC'), '24h' => $now - 86400, '30d' => $now - 30*86400, '90d' => $now - 90*86400, 'all' => 0, default => $now - 7*86400 };
        $end = $now;
    }
    return [gmdate('Y-m-d H:i:s', $start), gmdate('Y-m-d H:i:s', $end)];
}
function setupFunnels(SQLite3 $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS funnels (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, steps TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
}
function cleanSteps(mixed $steps): array {
    if (!is_array($steps) || count($steps) < 1 || count($steps) > 12) throw new InvalidArgumentException('Add between 1 and 12 steps.');
    $out = [];
    foreach ($steps as $step) {
        $type = ($step['type'] ?? '') === 'pageview' ? 'pageview' : 'event';
        $value = trim((string)($step['value'] ?? ''));
        $label = trim((string)($step['label'] ?? ''));
        if ($value === '') throw new InvalidArgumentException('Every step needs an event or page.');
        $out[] = ['type' => $type, 'value' => mb_substr($value, 0, 180), 'label' => mb_substr($label ?: $value, 0, 80)];
    }
    return $out;
}
function analyzeFunnel(SQLite3 $db, array $steps, string $start, string $end): array {
    $stmt = $db->prepare("SELECT session_id, action FROM user_activity WHERE timestamp >= :start AND timestamp <= :end ORDER BY session_id, timestamp, id");
    $stmt->bindValue(':start', $start, SQLITE3_TEXT); $stmt->bindValue(':end', $end, SQLITE3_TEXT); $res = $stmt->execute();
    $progress = array_fill(0, count($steps), 0); $session = null; $at = 0;
    $finish = function() use (&$session, &$at, &$progress) { if ($session !== null) for ($i = 0; $i < $at; $i++) $progress[$i]++; };
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        if ($session !== $row['session_id']) { $finish(); $session = $row['session_id']; $at = 0; }
        if ($at >= count($steps)) continue;
        $action = json_decode($row['action'], true) ?: []; $name = (string)($action['name'] ?? ''); $path = (string)($action['data']['path'] ?? ''); $wanted = $steps[$at];
        $matches = $wanted['type'] === 'pageview' ? ($name === 'pageview' && $path === $wanted['value']) : ($name === $wanted['value']);
        if ($matches) $at++;
    }
    $finish();
    return $progress;
}
try {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $siteId = $_GET['site_id'] ?? $payload['site_id'] ?? null; $db = Database::getConnection(Database::sanitizeSiteId($siteId)); setupFunnels($db);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim((string)($payload['name'] ?? '')); if ($name === '') throw new InvalidArgumentException('Give this funnel a name.');
        $steps = cleanSteps($payload['steps'] ?? []); $id = (int)($payload['id'] ?? 0);
        if ($id) { $st = $db->prepare('UPDATE funnels SET name=:name, steps=:steps WHERE id=:id'); $st->bindValue(':id', $id, SQLITE3_INTEGER); }
        else { $st = $db->prepare('INSERT INTO funnels (name, steps) VALUES (:name, :steps)'); }
        $st->bindValue(':name', mb_substr($name, 0, 80), SQLITE3_TEXT); $st->bindValue(':steps', json_encode($steps), SQLITE3_TEXT); $st->execute();
        echo json_encode(['success' => true, 'id' => $id ?: $db->lastInsertRowID()]); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') { $id = (int)($_GET['id'] ?? 0); $st = $db->prepare('DELETE FROM funnels WHERE id=:id'); $st->bindValue(':id',$id,SQLITE3_INTEGER); $st->execute(); echo json_encode(['success'=>true]); exit; }
    [$start, $end] = funnelRange(); $items = []; $result = $db->query('SELECT * FROM funnels ORDER BY created_at DESC, id DESC');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) { $steps = json_decode($row['steps'], true) ?: []; $items[] = ['id'=>(int)$row['id'], 'name'=>$row['name'], 'steps'=>$steps, 'progress'=>analyzeFunnel($db,$steps,$start,$end)]; }
    // The builder exposes the site's complete vocabulary. The selected period
    // only affects the funnel's figures, never which event/page can be chosen.
    $events = []; $er = $db->query("SELECT DISTINCT json_extract(action, '$.name') AS name FROM user_activity ORDER BY name"); while($r=$er->fetchArray(SQLITE3_ASSOC)) if($r['name'] && $r['name'] !== 'pageview') $events[]=$r['name'];
    $pages = []; $pr = $db->query("SELECT DISTINCT json_extract(action, '$.data.path') AS path FROM user_activity WHERE json_extract(action, '$.name')='pageview' ORDER BY path"); while($r=$pr->fetchArray(SQLITE3_ASSOC)) if($r['path'] !== null && $r['path'] !== '') $pages[]=$r['path'];
    echo json_encode(['success'=>true,'funnels'=>$items,'events'=>$events,'pages'=>$pages]);
} catch (Throwable $e) { http_response_code(400); echo json_encode(['error'=>$e->getMessage()]); }
