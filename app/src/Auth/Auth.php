<?php

declare(strict_types=1);

namespace Minilytics\Auth;

use Delight\Auth\Auth as AuthEngine;
use Delight\Auth\Role;
use Minilytics\Database\Database;
use PDO;
use SQLite3;

/**
 * Authentication and invitation helpers for the private dashboard.
 *
 * Accounts, sessions and login throttling are handled by delight-im/auth;
 * this class keeps the dashboard's small API ("admin" / "member" roles).
 */
final class Auth
{
    private static ?AuthEngine $engine = null;

    public static function dataDir(): string
    {
        return Database::getDataDir();
    }

    public static function dbPath(): string
    {
        return self::dataDir() . '/auth.db';
    }
    public static function hasDatabase(): bool
    {
        return is_file(self::dbPath());
    }

    public static function startSession(): void
    {
        // Before onboarding there is no auth database to attach the engine to.
        if (!self::hasDatabase()) {
            self::configureSession();
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            return;
        }
        self::engine();
    }

    public static function db(): SQLite3
    {
        $db = new SQLite3(self::dbPath());
        $db->busyTimeout(5000);
        $db->exec('PRAGMA journal_mode = WAL;');
        AuthSchema::ensure($db);
        return $db;
    }

    public static function engine(): AuthEngine
    {
        if (self::$engine === null) {
            self::configureSession();
            self::db()->close();
            $pdo = new PDO('sqlite:' . self::dbPath());
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA busy_timeout = 5000;');
            // Resync on every request so role changes and deleted accounts apply immediately.
            self::$engine = new AuthEngine($pdo, null, null, null, 0);
        }
        return self::$engine;
    }

    private static function configureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('minilytics_session');
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
        }
    }

    public static function isAuthenticated(): bool
    {
        return self::hasDatabase() && self::engine()->isLoggedIn();
    }
    public static function user(): ?array
    {
        if (!self::isAuthenticated()) {
            return null;
        }
        $auth = self::engine();
        return [
            'id' => (int) $auth->getUserId(),
            'email' => (string) $auth->getEmail(),
            'role' => $auth->hasRole(Role::ADMIN) ? 'admin' : 'member',
        ];
    }
    public static function requireLogin(): void
    {
        if (!self::hasDatabase()) {
            http_response_code(409);
            self::jsonError('Onboarding is required.');
        }
        if (!self::user()) {
            http_response_code(401);
            self::jsonError('Authentication required.');
        }
        // Every state-changing dashboard API call goes through here.
        Csrf::requireValid();
    }
    /**
     * Read access to a website's analytics: members see every site, anonymous
     * visitors only the sites flagged "is_public" (live demo).
     */
    public static function requireSiteAccess(?string $siteId): void
    {
        if (!self::hasDatabase()) {
            http_response_code(409);
            self::jsonError('Onboarding is required.');
        }
        if (self::user()) {
            return;
        }
        if (Database::isPublicSite((string) $siteId)) {
            return;
        }
        http_response_code(401);
        self::jsonError('Authentication required.');
    }
    public static function isGuest(): bool
    {
        return self::user() === null;
    }
    public static function requireAdmin(): array
    {
        self::requireLogin();
        $user = self::user();
        if (($user['role'] ?? '') !== 'admin') {
            http_response_code(403);
            self::jsonError('Administrator access required.');
        }
        return $user;
    }
    public static function jsonError(string $message): never
    {
        echo json_encode(['error' => $message]);
        exit;
    }
    /** Creates a verified account and returns its ID. */
    public static function createUser(string $email, string $password, string $role): int
    {
        $admin = self::engine()->admin();
        $id = (int) $admin->createUser($email, $password);
        if ($role === 'admin') {
            $admin->addRoleForUserById($id, Role::ADMIN);
        }
        return $id;
    }
    public static function loginById(int $id): void
    {
        self::engine()->admin()->logInAsUserById($id);
    }
    public static function logout(): void
    {
        if (self::hasDatabase()) {
            self::engine()->logOut();
            self::engine()->destroySession();
            return;
        }
        self::startSession();
        $_SESSION = [];
        session_destroy();
    }
    public static function validEmail(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    public static function passwordError(string $password): ?string
    {
        if (strlen($password) < 10) {
            return 'Your password must contain at least 10 characters.';
        }
        if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[^a-zA-Z\d]/', $password)) {
            return 'Use uppercase, lowercase, a number, and a symbol.';
        }
        return null;
    }
}
