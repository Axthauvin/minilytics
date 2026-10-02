<?php

/*
This is a simple PHP script that tracks user activity on a website. It connects to a SQLite database and logs the user's actions. 
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

    /* Check the keys
    The JSON must contain : 
    - a "name" key with a string value (fallback to "title" if provided)
    - a "data" key with either a string or an object value
    */

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

function success_json($message)
{
    header('Content-Type: application/json');
    echo json_encode(["success" => $message]);
    exit;
}

$db = new SQLite3('database.db');
if (!$db) {
    error_json("Failed to connect to the database");
    exit;
}

// Ensure table exists with session_id
$db->exec("CREATE TABLE IF NOT EXISTS user_activity (id INTEGER PRIMARY KEY AUTOINCREMENT, 
    session_id TEXT NOT NULL, 
    action TEXT NOT NULL, 
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP)"
);

// If database had an older table schema with user_id instead of session_id, migrate it
$cols = $db->query("PRAGMA table_info(user_activity)");
$hasSessionId = false;
$hasUserId = false;
while ($col = $cols->fetchArray(SQLITE3_ASSOC)) {
    if ($col['name'] === 'session_id') $hasSessionId = true;
    if ($col['name'] === 'user_id') $hasUserId = true;
}
if ($hasUserId && !$hasSessionId) {
    $db->exec("ALTER TABLE user_activity RENAME COLUMN user_id TO session_id");
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
$session_id = (isset($action['session_id']) && is_string($action['session_id']) && trim($action['session_id']) !== '')
    ? trim($action['session_id'])
    : generateSessionId($websiteId);

// Insert user activity into the database
$stmt = $db->prepare("INSERT INTO user_activity (session_id, action) VALUES (:session_id, :action)");
$stmt->bindValue(':session_id', $session_id, SQLITE3_TEXT);
$stmt->bindValue(':action', json_encode($action), SQLITE3_TEXT);
$stmt->execute();

success_json("Action logged successfully");
?>