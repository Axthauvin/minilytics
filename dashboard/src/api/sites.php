<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
require_once __DIR__ . '/auth.php';
Auth::requireLogin();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php';

try {
    $action = $_GET['action'] ?? '';
    // Tracking secrets and configuration are administrator-only.
    if ($action === 'tracking-config') {
        Auth::requireAdmin();
        $site = Database::trackingSite((string)($_GET['id'] ?? ''));
        if (!$site) throw new InvalidArgumentException('Website not found.');
        echo json_encode(['success' => true, 'site' => $site]); exit;
    }
    if ($method === 'PATCH' || $action === 'update-config') {
        Auth::requireAdmin();
        $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $site = Database::updateSiteConfig((string)($body['id'] ?? $_GET['id'] ?? ''), $body);
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        echo json_encode(['success' => true, 'site' => $site, 'snippet' => Database::trackingSnippet($site, "$scheme://$host/minilytics.js")], JSON_UNESCAPED_SLASHES); exit;
    }
    // 1. DELETE site
    if ($method === 'DELETE' || (isset($_GET['action']) && $_GET['action'] === 'delete')) {
        Auth::requireAdmin();
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
        Auth::requireAdmin();
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
        if (Database::normalizeHost($domain) === '') {
            http_response_code(400);
            echo json_encode(['error' => 'A production domain is required to protect the tracking endpoint.']);
            exit;
        }

        $site = Database::createSite($id, $name, $domain);

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $scriptUrl = "{$protocol}{$host}/minilytics.js";
        $trackingSnippet = Database::trackingSnippet($site, $scriptUrl);

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
