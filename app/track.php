<?php

declare(strict_types=1);

use Minilytics\Tracking\Tracker;

/** Collection endpoint called by minilytics.js. The work happens in Minilytics\Tracking\Tracker. */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
// A preflight contains no site key. The actual POST below performs the
// authoritative allowlist check before returning a CORS response.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    if (!empty($_SERVER['HTTP_ORIGIN'])) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    }
    http_response_code(204);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}
require_once __DIR__ . '/vendor/autoload.php';

$response = Tracker::handle($_SERVER, (string) file_get_contents('php://input'));
if ($response->allowOrigin !== null) {
    header('Access-Control-Allow-Origin: ' . $response->allowOrigin);
}
http_response_code($response->status);
echo json_encode($response->body);
