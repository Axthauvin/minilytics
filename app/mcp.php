<?php

declare(strict_types=1);

use Minilytics\Auth\McpTokens;
use Minilytics\Auth\BearerTokens;
use Minilytics\Http\Http;
use Minilytics\Mcp\AnalyticsTools;
use Minilytics\Mcp\McpServer;

/**
 * MCP endpoint giving AI assistants read-only access to the analytics.
 *
 * Streamable HTTP transport, answering every request with a single JSON
 * response (no SSE stream). Requests carry an access token from
 * Settings > AI assistants: `Authorization: Bearer <token>`.
 */
require_once __DIR__ . '/vendor/autoload.php';
Http::allowCors('POST');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'This MCP endpoint only accepts POST requests.']);
    exit;
}

$token = BearerTokens::fromRequest();
$user = $token === null ? null : McpTokens::authenticate($token);
if ($user === null) {
    http_response_code(401);
    header('WWW-Authenticate: Bearer' . ($token === null ? '' : ' error="invalid_token"'));
    echo json_encode(['error' => 'Send an access token: Authorization: Bearer <token>.']);
    exit;
}

$message = json_decode((string) file_get_contents('php://input'), true);
$response = json_last_error() === JSON_ERROR_NONE
    ? (new McpServer(AnalyticsTools::all()))->handle($message)
    : McpServer::error(null, -32700, 'Parse error.');

if ($response === null) {
    http_response_code(202);
    exit;
}
echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
