<?php

declare(strict_types=1);

/** Public ingestion: registered sites, per-site keys, allowed origins only. */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
// A preflight contains no site key. The actual POST below performs the
// authoritative allowlist check before returning a CORS response.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    if (!empty($_SERVER['HTTP_ORIGIN'])) header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    http_response_code(204);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}
require_once __DIR__ . '/dashboard/src/api/db.php';
// GeoIP enrichment is optional: an incomplete deployment or an unavailable
// extension must never make the public collection endpoint return a 500.
try {
    $geoHelper = __DIR__ . '/dashboard/src/api/geo.php';
    if (is_file($geoHelper)) require_once $geoHelper;
} catch (Throwable $error) {
    error_log('[Minilytics] GeoIP module could not be loaded: ' . $error->getMessage());
}
require_once __DIR__ . '/session.php';

function failTracking(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}
function trackingHost(string $value): string
{
    return Database::normalizeHost((string)(parse_url($value, PHP_URL_HOST) ?: $value));
}
function trackingIp(): string
{
    return trim(explode(',', $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]);
}
function botReason(string $ua): ?string
{
    return $ua === '' ? 'missing_user_agent' : (preg_match('/bot|crawler|spider|slurp|facebookexternalhit|preview|headless|lighthouse|pingdom|uptimerobot|curl|wget/i', $ua) ? 'known_bot_user_agent' : null);
}
function browserFromUserAgent(string $ua): string
{
    if (preg_match('/edg(?:e|a|ios)?\//i', $ua)) return 'Microsoft Edge';
    if (preg_match('/opr\//i', $ua) || stripos($ua, 'opera') !== false) return 'Opera';
    if (stripos($ua, 'samsungbrowser') !== false) return 'Samsung Internet';
    if (stripos($ua, 'firefox') !== false || stripos($ua, 'fxios') !== false) return 'Firefox';
    if (stripos($ua, 'crios') !== false) return 'Chrome';
    if (stripos($ua, 'chrome') !== false || stripos($ua, 'chromium') !== false) return 'Chrome';
    if (stripos($ua, 'safari') !== false) return 'Safari';
    return 'Other';
}
function osFromUserAgent(string $ua): string
{
    if (stripos($ua, 'cros') !== false) return 'Chrome OS';
    if (stripos($ua, 'windows') !== false) return 'Windows';
    if (stripos($ua, 'android') !== false) return 'Android';
    if (preg_match('/iphone|ipad|ipod/i', $ua)) return 'iOS';
    if (stripos($ua, 'mac os') !== false || stripos($ua, 'macintosh') !== false) return 'macOS';
    if (stripos($ua, 'linux') !== false) return 'Linux';
    return 'Other';
}
function deviceFromUserAgent(string $ua): string
{
    if (preg_match('/ipad|tablet|kindle|silk\//i', $ua) || (stripos($ua, 'android') !== false && !preg_match('/mobile/i', $ua))) return 'Tablet';
    if (preg_match('/mobile|iphone|ipod|android/i', $ua)) return 'Mobile';
    return 'Desktop';
}
function trackingLocation(SQLite3 $db, string $ip): array
{
    // Keep geolocation server-side and privacy-preserving. Different managed
    // proxies expose the same ISO country code under different header names.
    // Do not use X-Forwarded-For here: it is an IP chain, not a country code.
    $locationHeaders = [
        ['country' => 'HTTP_CF_IPCOUNTRY', 'region' => 'HTTP_CF_REGION_CODE', 'city' => 'HTTP_CF_IPCITY'],
        ['country' => 'HTTP_X_VERCEL_IP_COUNTRY', 'region' => 'HTTP_X_VERCEL_IP_COUNTRY_REGION', 'city' => 'HTTP_X_VERCEL_IP_CITY'],
        ['country' => 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'region' => 'HTTP_CLOUDFRONT_VIEWER_COUNTRY_REGION', 'city' => 'HTTP_CLOUDFRONT_VIEWER_CITY'],
        ['country' => 'HTTP_FASTLY_CLIENT_COUNTRY_CODE', 'region' => '', 'city' => ''],
        ['country' => 'GEOIP_COUNTRY_CODE', 'region' => 'GEOIP_REGION_NAME', 'city' => 'GEOIP_CITY'],
    ];
    foreach ($locationHeaders as $headers) {
        $candidate = trim((string)($_SERVER[$headers['country']] ?? ''));
        if ($candidate !== '') {
            $code = strtoupper($candidate);
            if (preg_match('/^[A-Z]{2}$/', $code) && $code !== 'XX') {
                $name = class_exists('Locale') ? Locale::getDisplayRegion('und_' . $code, 'en') : $code;
                return ['country' => $name ?: $code, 'country_code' => $code, 'region' => trim((string)($_SERVER[$headers['region']] ?? '')), 'city' => trim((string)($_SERVER[$headers['city']] ?? ''))];
            }
        }
    }
    if ($ip === '::1' || str_starts_with($ip, '127.') || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return ['country' => 'Local development', 'country_code' => 'UN', 'region' => '', 'city' => ''];
    if (class_exists('GeoLocation')) {
        try {
            $location = GeoLocation::lookup($ip);
            if (is_array($location)) return $location;
        } catch (Throwable $error) {
            error_log('[Minilytics] GeoIP lookup failed: ' . $error->getMessage());
        }
    }
    return ['country' => 'Unknown', 'country_code' => 'UN', 'region' => '', 'city' => ''];
}
function activeSessionId(SQLite3 $db, string $visitorId): ?string
{
    $cutoff = gmdate('Y-m-d H:i:s', time() - 1800);
    $stmt = $db->prepare('SELECT session_id FROM user_activity WHERE visitor_id = :visitor AND timestamp >= :cutoff ORDER BY timestamp DESC, id DESC LIMIT 1');
    $stmt->bindValue(':visitor', $visitorId, SQLITE3_TEXT);
    $stmt->bindValue(':cutoff', $cutoff, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return is_array($row) && !empty($row['session_id']) ? (string)$row['session_id'] : null;
}
function cleanValue(mixed $value, int $depth = 0): mixed
{
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
function recordIgnored(SQLite3 $db, string $reason, string $ua, string $origin, string $ip): void
{
    $stmt = $db->prepare('INSERT INTO bot_activity (reason, user_agent, origin, ip_hash) VALUES (:reason,:ua,:origin,:ip)');
    $stmt->bindValue(':reason', $reason, SQLITE3_TEXT);
    $stmt->bindValue(':ua', substr($ua, 0, 500), SQLITE3_TEXT);
    $stmt->bindValue(':origin', substr($origin, 0, 255), SQLITE3_TEXT);
    $stmt->bindValue(':ip', hash('sha256', $ip), SQLITE3_TEXT);
    $stmt->execute();
}
function withinRateLimit(SQLite3 $db, string $ip): bool
{
    $bucket = gmdate('YmdHi');
    $hash = hash('sha256', $ip);
    $stmt = $db->prepare('INSERT INTO rate_limits (bucket,ip_hash,count) VALUES (:bucket,:hash,1) ON CONFLICT(bucket,ip_hash) DO UPDATE SET count=count+1');
    $stmt->bindValue(':bucket', $bucket, SQLITE3_TEXT);
    $stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
    $stmt->execute();
    $check = $db->prepare('SELECT count FROM rate_limits WHERE bucket=:bucket AND ip_hash=:hash');
    $check->bindValue(':bucket', $bucket, SQLITE3_TEXT);
    $check->bindValue(':hash', $hash, SQLITE3_TEXT);
    return (int)$check->execute()->fetchArray(SQLITE3_NUM)[0] <= 240;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload) || !is_string($payload['name'] ?? null) || !is_array($payload['data'] ?? null)) failTracking('Expected name and data object.');
$site = Database::trackingSite((string)($payload['site_id'] ?? ''));
if (!$site) failTracking('Unknown website.', 404);
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
$originHost = trackingHost($origin);
$allowed = array_values(array_filter(array_map([Database::class, 'normalizeHost'], (array)($site['allowed_domains'] ?? []))));
if (!$allowed) failTracking('No allowed domains are configured for this website.', 403);
if ($originHost === '' || !in_array($originHost, $allowed, true)) failTracking('Origin is not authorized for this website.', 403);
if (!hash_equals((string)($site['write_key'] ?? ''), (string)($payload['site_key'] ?? ''))) failTracking('Invalid tracking key.', 403);
if (!empty($_SERVER['HTTP_ORIGIN'])) header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
$db = Database::getConnection($site['id']);
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$ip = trackingIp();
if (!withinRateLimit($db, $ip)) failTracking('Rate limit exceeded.', 429);
if (($reason = botReason($ua)) !== null) {
    recordIgnored($db, $reason, $ua, $originHost, $ip);
    echo json_encode(['ignored' => 'bot']);
    exit;
}
if (in_array($ip, (array)($site['internal_ips'] ?? []), true)) {
    recordIgnored($db, 'internal_traffic', $ua, $originHost, $ip);
    echo json_encode(['ignored' => 'internal']);
    exit;
}

$name = preg_replace('/[^a-zA-Z0-9_\-:.]/', '_', substr((string)$payload['name'], 0, 100));
if ($name === '') failTracking('Invalid event name.');
$data = cleanValue($payload['data']);
unset($data['url'], $data['search'], $data['hash']);
$trackingMode = ($data['tracking_mode'] ?? 'strict') === 'enriched' ? 'enriched' : 'strict';
unset($data['tracking_mode']);
$data['_ml_tracking_mode'] = $trackingMode;
if (isset($data['path'])) $data['path'] = '/' . ltrim((string)$data['path'], '/');
if (isset($data['referrer'])) $data['referrer'] = trackingHost((string)$data['referrer']);
$data['browser'] = browserFromUserAgent($ua);
$data['os'] = osFromUserAgent($ua);
$data['device'] = deviceFromUserAgent($ua);
try {
    $data = array_merge($data, trackingLocation($db, $ip));
} catch (Throwable $error) {
    error_log('[Minilytics] GeoIP enrichment failed: ' . $error->getMessage());
    $data = array_merge($data, ['country' => 'Unknown', 'country_code' => 'UN', 'region' => '', 'city' => '']);
}
if ($trackingMode === 'strict') {
    $visitor = generateVisitorId((string)$site['id'], $ip, $ua);
    $session = activeSessionId($db, $visitor) ?? generateSessionId();
} else {
    $session = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($payload['session_id'] ?? '')) ?: generateSessionId();
    $visitor = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($payload['visitor_id'] ?? '')) ?: $session;
}
$action = ['site_id' => $site['id'], 'name' => $name, 'data' => $data];
$stmt = $db->prepare('INSERT INTO user_activity (session_id, visitor_id, action) VALUES (:session,:visitor,:action)');
$stmt->bindValue(':session', $session, SQLITE3_TEXT);
$stmt->bindValue(':visitor', $visitor, SQLITE3_TEXT);
$stmt->bindValue(':action', json_encode($action, JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
$stmt->execute();
echo json_encode(['success' => true]);
