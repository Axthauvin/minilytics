<?php

/*
Minilytics Event Tracking Endpoint
Stores activity in isolated SQLite databases per site under data/<site_id>.db
*/

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include_once("session.php");

function check_sended_action_json($action): bool|string
{
    if (!is_array($action)) {
        return "invalid JSON string";
    }

    $name = $action['name'] ?? $action['title'] ?? null;
    if (!isset($name) || !is_string($name)) {
        return "expected 'name' key with a string value";
    }

    if (!isset($action['data']) || (!is_string($action['data']) && !is_array($action['data']))) {
        return "expected 'data' key with a string or object value";
    }

    return true;
}

function error_json($message)
{
    header('Content-Type: application/json');
    echo json_encode(["error" => $message]);
    exit;
}

function success_json($message, array $extra = [])
{
    header('Content-Type: application/json');
    echo json_encode(array_merge(["success" => $message], $extra));
    exit;
}

// Check it has access to SQLite3 extension
if (!extension_loaded('sqlite3')) {
    http_response_code(500);
    error_json("SQLite3 extension is not enabled on the server");
    exit;
}

// Read raw JSON from request body
$raw_input = file_get_contents('php://input');
$action = json_decode($raw_input, true);

$json_err = check_sended_action_json($action);
if ($json_err !== true) {
    error_json($json_err);
    exit;
}

// Normalize name if title was sent
if (!isset($action['name']) && isset($action['title'])) {
    $action['name'] = $action['title'];
}

$websiteId = $action['site_id'] ?? $action['website_id'] ?? $_POST['site_id'] ?? $_GET['site_id'] ?? 'default_site';
$cleanSiteId = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$websiteId) ?: 'default_site';

$session_id = (isset($action['session_id']) && is_string($action['session_id']) && trim($action['session_id']) !== '')
    ? trim($action['session_id'])
    : generateSessionId($cleanSiteId);

$visitor_id = (isset($action['visitor_id']) && is_string($action['visitor_id']) && trim($action['visitor_id']) !== '')
    ? trim($action['visitor_id'])
    : generateSessionId($cleanSiteId);

function parseUserAgent(?string $ua): array {
    $ua = $ua ?? '';
    $browser = 'Other';
    $os = 'Other';
    $device = 'Desktop';

    if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $ua)) {
        $device = 'Tablet';
    } elseif (preg_match('/(mobile|iphone|ipod|blackberry|iemobile|opera mini)/i', $ua)) {
        $device = 'Mobile';
    }

    if (preg_match('/windows nt/i', $ua)) $os = 'Windows';
    elseif (preg_match('/macintosh|mac os x/i', $ua)) $os = 'macOS';
    elseif (preg_match('/android/i', $ua)) $os = 'Android';
    elseif (preg_match('/iphone|ipad|ipod/i', $ua)) $os = 'iOS';
    elseif (preg_match('/linux/i', $ua)) $os = 'Linux';

    if (preg_match('/edg\/|edge\//i', $ua)) $browser = 'Edge';
    elseif (preg_match('/opr\/|opera\//i', $ua)) $browser = 'Opera';
    elseif (preg_match('/chrome|crios/i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/firefox|fxios/i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/safari/i', $ua) && !preg_match('/chrome|crios/i', $ua)) $browser = 'Safari';

    return ['browser' => $browser, 'os' => $os, 'device' => $device];
}

function parseCountry(): array {
    $code = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? $_SERVER['HTTP_X_COUNTRY_CODE'] ?? $_SERVER['GEOIP_COUNTRY_CODE'] ?? null;
    
    if (empty($code) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        if (preg_match('/[a-z]{2}-([A-Z]{2})/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $m)) {
            $code = strtoupper($m[1]);
        } elseif (preg_match('/^([a-z]{2})/i', trim($_SERVER['HTTP_ACCEPT_LANGUAGE']), $m)) {
            $langToCountry = [
                'fr' => 'FR', 'en' => 'US', 'de' => 'DE', 'es' => 'ES', 
                'it' => 'IT', 'pt' => 'BR', 'ja' => 'JP', 'zh' => 'CN', 'nl' => 'NL'
            ];
            $code = $langToCountry[strtolower($m[1])] ?? 'US';
        }
    }

    $code = strtoupper($code ?: 'US');
    $countries = [
        'FR' => 'France', 'US' => 'United States', 'GB' => 'United Kingdom',
        'DE' => 'Germany', 'ES' => 'Spain', 'IT' => 'Italy', 'CA' => 'Canada',
        'NL' => 'Netherlands', 'BR' => 'Brazil', 'JP' => 'Japan', 'CH' => 'Switzerland',
        'BE' => 'Belgium', 'AU' => 'Australia', 'IN' => 'India'
    ];
    $name = $countries[$code] ?? $code;

    return ['code' => $code, 'name' => $name];
}

// Server-side enrichment if data was not provided by client
if (is_array($action['data'])) {
    if (empty($action['data']['referrer']) && !empty($_SERVER['HTTP_REFERER'])) {
        $action['data']['referrer'] = $_SERVER['HTTP_REFERER'];
    }
    if (empty($action['data']['language']) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $action['data']['language'] = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE'])[0];
    }

    $uaInfo = parseUserAgent($_SERVER['HTTP_USER_AGENT'] ?? null);
    $action['data']['browser'] = $action['data']['browser'] ?? $uaInfo['browser'];
    $action['data']['os'] = $action['data']['os'] ?? $uaInfo['os'];
    $action['data']['device'] = $action['data']['device'] ?? $uaInfo['device'];

    $geo = parseCountry();
    $action['data']['country_code'] = $action['data']['country_code'] ?? $geo['code'];
    $action['data']['country'] = $action['data']['country'] ?? $geo['name'];
}

// Target database in data/<site_id>.db
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$dbPath = "{$dataDir}/{$cleanSiteId}.db";
$db = new SQLite3($dbPath);
if (!$db) {
    error_json("Failed to connect to the site database");
    exit;
}

$db->busyTimeout(5000);
$db->exec('PRAGMA journal_mode = WAL;');

// Ensure table exists with session_id and visitor_id
$db->exec("CREATE TABLE IF NOT EXISTS user_activity (
    id INTEGER PRIMARY KEY AUTOINCREMENT, 
    session_id TEXT NOT NULL, 
    visitor_id TEXT,
    action TEXT NOT NULL, 
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Auto-migrate if visitor_id column doesn't exist
$cols = $db->query("PRAGMA table_info(user_activity)");
$hasVisitorId = false;
while ($col = $cols->fetchArray(SQLITE3_ASSOC)) {
    if ($col['name'] === 'visitor_id') $hasVisitorId = true;
}
if (!$hasVisitorId) {
    @$db->exec("ALTER TABLE user_activity ADD COLUMN visitor_id TEXT");
    @$db->exec("UPDATE user_activity SET visitor_id = session_id WHERE visitor_id IS NULL");
}

// Insert user activity into the dedicated site database
$stmt = $db->prepare("INSERT INTO user_activity (session_id, visitor_id, action) VALUES (:session_id, :visitor_id, :action)");
$stmt->bindValue(':session_id', $session_id, SQLITE3_TEXT);
$stmt->bindValue(':visitor_id', $visitor_id, SQLITE3_TEXT);
$stmt->bindValue(':action', json_encode($action, JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
$stmt->execute();

success_json("Action logged successfully", ["site_id" => $cleanSiteId]);
?>