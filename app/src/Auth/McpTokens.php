<?php

declare(strict_types=1);

namespace Minilytics\Auth;

/**
 * Read-only access tokens for the MCP server, used by AI assistants that
 * cannot sign in with OAuth. Only a SHA-256 hash of each token is stored:
 * the token itself is shown once, when it is created.
 */
final class McpTokens
{
    private const PREFIX = 'mlt_';

    /**
     * Creates a token for a user and returns it with its plain-text value.
     *
     * @return array{id: int, name: string, hint: string, created_at: string, last_used_at: null, token: string}
     */
    public static function create(int $userId, string $name): array
    {
        $token = self::PREFIX . bin2hex(random_bytes(24));
        $hint = substr($token, 0, strlen(self::PREFIX) + 4) . '…' . substr($token, -4);
        $now = time();
        $db = Auth::db();
        $stmt = $db->prepare('INSERT INTO mcp_tokens (user_id, name, token_hash, hint, created_at) VALUES (:user, :name, :hash, :hint, :now)');
        $stmt->bindValue(':user', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':hash', hash('sha256', $token), SQLITE3_TEXT);
        $stmt->bindValue(':hint', $hint, SQLITE3_TEXT);
        $stmt->bindValue(':now', $now, SQLITE3_INTEGER);
        $stmt->execute();
        return ['id' => $db->lastInsertRowID(), 'name' => $name, 'hint' => $hint, 'created_at' => (string) BearerTokens::date($now), 'last_used_at' => null, 'token' => $token];
    }

    /** @return list<array{id: int, name: string, hint: string, created_at: string, last_used_at: ?string}> */
    public static function forUser(int $userId): array
    {
        $stmt = Auth::db()->prepare('SELECT id, name, hint, created_at, last_used_at FROM mcp_tokens WHERE user_id = :user ORDER BY id DESC');
        $stmt->bindValue(':user', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $tokens = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $tokens[] = ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'hint' => (string) $row['hint'], 'created_at' => (string) BearerTokens::date($row['created_at']), 'last_used_at' => BearerTokens::date($row['last_used_at'])];
        }
        return $tokens;
    }

    /** Deletes one of the user's tokens. Returns false when it does not exist. */
    public static function revoke(int $userId, int $id): bool
    {
        $db = Auth::db();
        $stmt = $db->prepare('DELETE FROM mcp_tokens WHERE id = :id AND user_id = :user');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':user', $userId, SQLITE3_INTEGER);
        $stmt->execute();
        return $db->changes() > 0;
    }

    public static function revokeAllForUser(int $userId): void
    {
        $stmt = Auth::db()->prepare('DELETE FROM mcp_tokens WHERE user_id = :user');
        $stmt->bindValue(':user', $userId, SQLITE3_INTEGER);
        $stmt->execute();
    }

    /** @return array{id: int, email: string}|null */
    public static function authenticate(string $token): ?array
    {
        return str_starts_with($token, self::PREFIX) ? BearerTokens::owner('mcp_tokens', 'token_hash', $token) : null;
    }
}
