<?php

declare(strict_types=1);

namespace Minilytics\Auth;

use Minilytics\Database\Database;
use SQLite3;

/** Authentication and invitation helpers for the private dashboard. */
final class Auth {
    public static function dataDir(): string {
        $dir = dirname(__DIR__, 2) . '/data';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        return $dir;
    }

    public static function dbPath(): string { return self::dataDir() . '/auth.db'; }
    public static function hasDatabase(): bool { return is_file(self::dbPath()); }

    public static function startSession(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('minilytics_session');
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
            session_start();
        }
    }

    public static function db(): SQLite3 {
        $db = new SQLite3(self::dbPath());
        $db->busyTimeout(5000);
        $db->exec('PRAGMA journal_mode = WAL;');
        $db->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE COLLATE NOCASE, password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT "member", created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $db->exec('CREATE TABLE IF NOT EXISTS invitations (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL COLLATE NOCASE, token_hash TEXT NOT NULL UNIQUE, expires_at TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, created_by INTEGER, accepted_at TEXT, FOREIGN KEY(created_by) REFERENCES users(id))');
        return $db;
    }

    public static function isAuthenticated(): bool { self::startSession(); return !empty($_SESSION['user_id']); }
    public static function user(): ?array {
        if (!self::isAuthenticated() || !self::hasDatabase()) return null;
        $db = self::db(); $stmt = $db->prepare('SELECT id, email, role FROM users WHERE id = :id');
        $stmt->bindValue(':id', (int)$_SESSION['user_id'], SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC) ?: null;
        if (!$row) self::logout();
        return $row;
    }
    public static function requireLogin(): void {
        if (!self::hasDatabase()) { http_response_code(409); self::jsonError('Onboarding is required.'); }
        if (!self::user()) { http_response_code(401); self::jsonError('Authentication required.'); }
    }
    /**
     * Read access to a website's analytics: members see every site, anonymous
     * visitors only the sites flagged "is_public" (live demo).
     */
    public static function requireSiteAccess(?string $siteId): void {
        if (!self::hasDatabase()) { http_response_code(409); self::jsonError('Onboarding is required.'); }
        if (self::user()) return;
        if (Database::isPublicSite((string)$siteId)) return;
        http_response_code(401); self::jsonError('Authentication required.');
    }
    public static function isGuest(): bool { return self::user() === null; }
    public static function requireAdmin(): array {
        self::requireLogin(); $user = self::user();
        if (($user['role'] ?? '') !== 'admin') { http_response_code(403); self::jsonError('Administrator access required.'); }
        return $user;
    }
    public static function jsonError(string $message): never { echo json_encode(['error' => $message]); exit; }
    public static function login(array $user): void { self::startSession(); session_regenerate_id(true); $_SESSION['user_id'] = (int)$user['id']; }
    public static function logout(): void { self::startSession(); $_SESSION = []; session_destroy(); }
    public static function validEmail(string $email): bool { return (bool)filter_var($email, FILTER_VALIDATE_EMAIL); }
    public static function passwordError(string $password): ?string {
        if (strlen($password) < 10) return 'Your password must contain at least 10 characters.';
        if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[^a-zA-Z\d]/', $password)) {
            return 'Use uppercase, lowercase, a number, and a symbol.';
        }
        return null;
    }
}
