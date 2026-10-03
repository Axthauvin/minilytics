<?php
declare(strict_types=1);

/** Public ingestion: registered sites, per-site keys, allowed origins only. */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
// A preflight contains no site key. The actual POST below performs the
// authoritative allowlist check before returning a CORS response.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { if (!empty($_SERVER['HTTP_ORIGIN'])) header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']); http_response_code(204); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST required']); exit; }
require_once __DIR__ . '/dashboard/src/api/db.php';

function failTracking(string $message, int $status = 400): never { http_response_code($status); echo json_encode(['error' => $message]); exit; }
function trackingHost(string $value): string { return Database::normalizeHost((string)(parse_url($value, PHP_URL_HOST) ?: $value)); }
function trackingIp(): string { return trim(explode(',', $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]); }
function botReason(string $ua): ?string { return $ua === '' ? 'missing_user_agent' : (preg_match('/bot|crawler|spider|slurp|facebookexternalhit|preview|headless|lighthouse|pingdom|uptimerobot|curl|wget/i', $ua) ? 'known_bot_user_agent' : null); }
function cleanValue(mixed $value, int $depth = 0): mixed {
    if ($depth > 3) return null;
    if (is_string($value)) return substr($value, 0, 500);
    if (is_bool($value) || is_int($value) || is_float($value) || $value === null) return $value;
    if (!is_array($value)) return null;
    $safe = [];
    foreach ($value as $key => $item) {
        $key = substr((string)$key, 0, 80);
        if (!preg_match('/password|token|secret|email|phone|address|card|authorization/i', $key)) $safe[$key] = cleanValue($item, $depth + 1);
    }
    return $safe;
}
function recordIgnored(SQLite3 $db, string $reason, string $ua, string $origin, string $ip): void {
    $stmt = $db->prepare('INSERT INTO bot_activity (reason, user_agent, origin, ip_hash) VALUES (:reason,:ua,:origin,:ip)');
    $stmt->bindValue(':reason', $reason, SQLITE3_TEXT); $stmt->bindValue(':ua', substr($ua, 0, 500), SQLITE3_TEXT); $stmt->bindValue(':origin', substr($origin, 0, 255), SQLITE3_TEXT); $stmt->bindValue(':ip', hash('sha256', $ip), SQLITE3_TEXT); $stmt->execute();
}
function withinRateLimit(SQLite3 $db, string $ip): bool {
    $bucket = gmdate('YmdHi'); $hash = hash('sha256', $ip);
    $stmt = $db->prepare('INSERT INTO rate_limits (bucket,ip_hash,count) VALUES (:bucket,:hash,1) ON CONFLICT(bucket,ip_hash) DO UPDATE SET count=count+1');
    $stmt->bindValue(':bucket',$bucket,SQLITE3_TEXT); $stmt->bindValue(':hash',$hash,SQLITE3_TEXT); $stmt->execute();
    $check=$db->prepare('SELECT count FROM rate_limits WHERE bucket=:bucket AND ip_hash=:hash');$check->bindValue(':bucket',$bucket,SQLITE3_TEXT);$check->bindValue(':hash',$hash,SQLITE3_TEXT);
    return (int)$check->execute()->fetchArray(SQLITE3_NUM)[0] <= 240;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload) || !is_string($payload['name'] ?? null) || !is_array($payload['data'] ?? null)) failTracking('Expected name and data object.');
$site = Database::trackingSite((string)($payload['site_id'] ?? ''));
if (!$site) failTracking('Unknown website.', 404);
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? ''); $originHost = trackingHost($origin);
$allowed = array_values(array_filter(array_map([Database::class, 'normalizeHost'], (array)($site['allowed_domains'] ?? []))));
if (!$allowed) failTracking('No allowed domains are configured for this website.', 403);
if ($originHost === '' || !in_array($originHost, $allowed, true)) failTracking('Origin is not authorized for this website.', 403);
if (!hash_equals((string)($site['write_key'] ?? ''), (string)($payload['site_key'] ?? ''))) failTracking('Invalid tracking key.', 403);
if (!empty($_SERVER['HTTP_ORIGIN'])) header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
$db = Database::getConnection($site['id']); $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? ''); $ip = trackingIp();
if (!withinRateLimit($db, $ip)) failTracking('Rate limit exceeded.', 429);
if (($reason = botReason($ua)) !== null) { recordIgnored($db, $reason, $ua, $originHost, $ip); echo json_encode(['ignored' => 'bot']); exit; }
if (in_array($ip, (array)($site['internal_ips'] ?? []), true)) { recordIgnored($db, 'internal_traffic', $ua, $originHost, $ip); echo json_encode(['ignored' => 'internal']); exit; }

$name = preg_replace('/[^a-zA-Z0-9_\-:.]/', '_', substr((string)$payload['name'], 0, 100));
if ($name === '') failTracking('Invalid event name.');
$data = cleanValue($payload['data']);
unset($data['url'], $data['search'], $data['hash'], $data['screen'], $data['viewport']);
if (isset($data['path'])) $data['path'] = '/' . ltrim((string)$data['path'], '/');
if (isset($data['referrer'])) $data['referrer'] = trackingHost((string)$data['referrer']);
$session = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($payload['session_id'] ?? '')) ?: bin2hex(random_bytes(16));
$visitor = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($payload['visitor_id'] ?? '')) ?: $session;
$action = ['site_id' => $site['id'], 'name' => $name, 'data' => $data];
$stmt = $db->prepare('INSERT INTO user_activity (session_id, visitor_id, action) VALUES (:session,:visitor,:action)');
$stmt->bindValue(':session', $session, SQLITE3_TEXT); $stmt->bindValue(':visitor', $visitor, SQLITE3_TEXT); $stmt->bindValue(':action', json_encode($action, JSON_UNESCAPED_SLASHES), SQLITE3_TEXT); $stmt->execute();
echo json_encode(['success' => true]);
