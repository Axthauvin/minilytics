<?php

declare(strict_types=1);

use Minilytics\Auth\Auth;
use Minilytics\Database\Database;

require_once __DIR__ . '/../../../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

try {
    Auth::requireAdmin();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        echo json_encode(['success' => true, 'config' => Database::publicDatabaseConfig()]);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        exit;
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Invalid database settings.');
    }
    $action = $input['action'] ?? 'test';

    if ($action === 'test') {
        if (($input['password'] ?? '') === '' && ($input['driver'] ?? '') === (Database::getDatabaseConfig()['driver'] ?? '')) {
            $input['password'] = Database::getDatabaseConfig()['password'] ?? '';
        }
        $result = Database::testDatabaseConfig($input);
        echo json_encode(['success' => true, 'message' => 'Connection successful.', 'connection' => $result]);
        exit;
    }
    if ($action === 'save') {
        $config = Database::saveDatabaseConfig($input);
        echo json_encode(['success' => true, 'message' => 'Database connector saved.', 'config' => $config]);
        exit;
    }
    throw new InvalidArgumentException('Unknown database action.');
} catch (Throwable $error) {
    http_response_code(400);
    echo json_encode(['error' => $error->getMessage()]);
}
