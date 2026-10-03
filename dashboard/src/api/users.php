<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/auth.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Authentication is required for any user operations
Auth::requireLogin();
$currentUser = Auth::user();
$db = Auth::db();

try {
    // 1. GET: List all authorized users and include current user info
    if ($method === 'GET') {
        $result = $db->query('SELECT id, email, role, created_at FROM users ORDER BY created_at ASC');
        $users = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $users[] = [
                'id' => (int)$row['id'],
                'email' => $row['email'],
                'role' => $row['role'],
                'created_at' => $row['created_at'],
            ];
        }
        echo json_encode([
            'users' => $users,
            'current_user' => [
                'id' => (int)($currentUser['id'] ?? 0),
                'email' => $currentUser['email'] ?? '',
                'role' => $currentUser['role'] ?? 'member',
            ]
        ]);
        exit;
    }

    // All modifications (invite, update role, delete user) require admin privileges
    $admin = Auth::requireAdmin();

    $rawInput = file_get_contents('php://input');
    $body = json_decode($rawInput, true) ?: $_POST;
    $action = $body['action'] ?? $_GET['action'] ?? null;

    // 2. DELETE: Remove an authorized user
    if ($method === 'DELETE' || $action === 'delete') {
        $userId = (int)($body['id'] ?? $_GET['id'] ?? 0);
        if ($userId <= 0) {
            http_response_code(400);
            Auth::jsonError('A valid user ID is required.');
        }

        // Prevent self-deletion
        if ($userId === (int)$admin['id']) {
            http_response_code(400);
            Auth::jsonError('You cannot delete your own account.');
        }

        // Check if user exists
        $stmt = $db->prepare('SELECT id, email, role FROM users WHERE id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $userToDelete = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$userToDelete) {
            http_response_code(404);
            Auth::jsonError('User not found.');
        }

        // If target is an admin, ensure at least one other administrator remains
        if ($userToDelete['role'] === 'admin') {
            $adminCount = (int)$db->querySingle("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            if ($adminCount <= 1) {
                http_response_code(400);
                Auth::jsonError('Cannot delete the only administrator.');
            }
        }

        // Clean up any invitations created by this user
        $cleanInvites = $db->prepare('UPDATE invitations SET created_by = NULL WHERE created_by = :id');
        $cleanInvites->bindValue(':id', $userId, SQLITE3_INTEGER);
        $cleanInvites->execute();

        // Delete the user record
        $deleteStmt = $db->prepare('DELETE FROM users WHERE id = :id');
        $deleteStmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $deleteStmt->execute();

        echo json_encode([
            'success' => true,
            'message' => 'User deleted successfully.'
        ]);
        exit;
    }

    // 3. UPDATE ROLE: Promote or demote user (grant or revoke admin status)
    if ($method === 'PATCH' || $method === 'PUT' || $action === 'update_role' || $action === 'set_role') {
        $userId = (int)($body['id'] ?? $_GET['id'] ?? 0);
        $newRole = trim(strtolower((string)($body['role'] ?? '')));

        if ($userId <= 0 || !in_array($newRole, ['admin', 'member'], true)) {
            http_response_code(400);
            Auth::jsonError('A valid user ID and role ("admin" or "member") are required.');
        }

        // Check if user exists
        $stmt = $db->prepare('SELECT id, email, role FROM users WHERE id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $targetUser = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$targetUser) {
            http_response_code(404);
            Auth::jsonError('User not found.');
        }

        // Prevent self-demotion from administrator status
        if ($userId === (int)$admin['id'] && $newRole !== 'admin') {
            http_response_code(400);
            Auth::jsonError('You cannot revoke your own administrator status.');
        }

        // If demoting an admin, ensure at least one other administrator remains
        if ($targetUser['role'] === 'admin' && $newRole !== 'admin') {
            $adminCount = (int)$db->querySingle("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            if ($adminCount <= 1) {
                http_response_code(400);
                Auth::jsonError('Cannot remove the only administrator.');
            }
        }

        if ($targetUser['role'] !== $newRole) {
            $updateStmt = $db->prepare('UPDATE users SET role = :role WHERE id = :id');
            $updateStmt->bindValue(':role', $newRole, SQLITE3_TEXT);
            $updateStmt->bindValue(':id', $userId, SQLITE3_INTEGER);
            $updateStmt->execute();
        }

        echo json_encode([
            'success' => true,
            'message' => $newRole === 'admin' ? 'User granted administrator status.' : 'Administrator status removed.',
            'role' => $newRole
        ]);
        exit;
    }

    // 4. POST: Create invitation link (default POST action)
    if ($method === 'POST') {
        $email = trim(strtolower($body['email'] ?? ''));
        if (!Auth::validEmail($email)) {
            http_response_code(400);
            Auth::jsonError('A valid email address is required.');
        }

        $s = $db->prepare('SELECT id FROM users WHERE email = :email');
        $s->bindValue(':email', $email, SQLITE3_TEXT);
        if ($s->execute()->fetchArray()) {
            http_response_code(409);
            Auth::jsonError('This email already has an account.');
        }

        $token = bin2hex(random_bytes(32));
        $s = $db->prepare('UPDATE invitations SET accepted_at = CURRENT_TIMESTAMP WHERE email = :email AND accepted_at IS NULL');
        $s->bindValue(':email', $email, SQLITE3_TEXT);
        $s->execute();

        $s = $db->prepare('INSERT INTO invitations (email, token_hash, expires_at, created_by) VALUES (:email, :hash, :expires, :by)');
        $s->bindValue(':email', $email, SQLITE3_TEXT);
        $s->bindValue(':hash', hash('sha256', $token), SQLITE3_TEXT);
        $s->bindValue(':expires', gmdate('Y-m-d H:i:s', time() + 7 * 86400), SQLITE3_TEXT);
        $s->bindValue(':by', (int)$admin['id'], SQLITE3_INTEGER);
        $s->execute();

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        echo json_encode([
            'success' => true,
            'invite_url' => "$scheme://$host/dashboard/accept-invite.php?token=$token",
            'expires_at' => gmdate('c', time() + 7 * 86400)
        ]);
        exit;
    }

    http_response_code(405);
    Auth::jsonError('Method not allowed.');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
