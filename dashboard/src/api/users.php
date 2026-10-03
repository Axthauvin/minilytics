<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth.php';
$admin = Auth::requireAdmin();
try {
    $db = Auth::db(); $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') { $result = $db->query('SELECT id, email, role, created_at FROM users ORDER BY created_at ASC'); $users = []; while ($row = $result->fetchArray(SQLITE3_ASSOC)) $users[] = $row; echo json_encode(['users' => $users]); exit; }
    if ($method !== 'POST') { http_response_code(405); Auth::jsonError('Method not allowed.'); }
    $body = json_decode(file_get_contents('php://input'), true) ?: $_POST; $email = trim(strtolower($body['email'] ?? ''));
    if (!Auth::validEmail($email)) { http_response_code(400); Auth::jsonError('A valid email address is required.'); }
    $s = $db->prepare('SELECT id FROM users WHERE email = :email'); $s->bindValue(':email', $email, SQLITE3_TEXT);
    if ($s->execute()->fetchArray()) { http_response_code(409); Auth::jsonError('This email already has an account.'); }
    $token = bin2hex(random_bytes(32)); $s = $db->prepare('UPDATE invitations SET accepted_at = CURRENT_TIMESTAMP WHERE email = :email AND accepted_at IS NULL'); $s->bindValue(':email', $email, SQLITE3_TEXT); $s->execute();
    $s = $db->prepare('INSERT INTO invitations (email, token_hash, expires_at, created_by) VALUES (:email, :hash, :expires, :by)'); $s->bindValue(':email', $email, SQLITE3_TEXT); $s->bindValue(':hash', hash('sha256', $token), SQLITE3_TEXT); $s->bindValue(':expires', gmdate('Y-m-d H:i:s', time() + 7 * 86400), SQLITE3_TEXT); $s->bindValue(':by', (int)$admin['id'], SQLITE3_INTEGER); $s->execute();
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'; $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    echo json_encode(['success' => true, 'invite_url' => "$scheme://$host/dashboard/accept-invite.php?token=$token", 'expires_at' => gmdate('c', time() + 7 * 86400)]);
} catch (Throwable $e) { http_response_code(400); echo json_encode(['error' => $e->getMessage()]); }
