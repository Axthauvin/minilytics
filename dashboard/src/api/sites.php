<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php';

try {
    // 1. DELETE site
    if ($method === 'DELETE' || (isset($_GET['action']) && $_GET['action'] === 'delete')) {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true) ?: [];
        $siteId = trim($body['id'] ?? $_GET['id'] ?? '');

        if (empty($siteId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Website identifier is required for deletion']);
            exit;
        }

        Database::deleteSite($siteId);
        echo json_encode([
            'success' => true,
            'message' => "Website '{$siteId}' and its database were deleted successfully."
        ]);
        exit;
    }

    // 2. POST: Create new website
    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true) ?: $_POST;

        $id = trim($body['id'] ?? '');
        $name = trim($body['name'] ?? '');
        $domain = trim($body['domain'] ?? '');

        if (empty($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Website identifier (site ID) is required']);
            exit;
        }

        $site = Database::createSite($id, $name, $domain);

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $scriptUrl = "{$protocol}{$host}/minilytics.js";
        $trackingSnippet = "<script defer src=\"{$scriptUrl}\" data-site-id=\"{$site['id']}\"></script>";

        echo json_encode([
            'success' => true,
            'site' => $site,
            'snippet' => $trackingSnippet
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // 3. GET: List all websites with live statistics
    $sites = Database::getSitesWithStats();
    echo json_encode([
        'success' => true,
        'sites' => $sites
    ], JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
