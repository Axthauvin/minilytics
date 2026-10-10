<?php

declare(strict_types=1);

namespace Minilytics\Tests\Auth;

use Delight\Auth\Role;
use Delight\Auth\UserAlreadyExistsException;
use Minilytics\Auth\Auth;
use Minilytics\Auth\McpTokens;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
    private const PASSWORD = 'Test-Passw0rd!';

    #[DataProvider('passwords')]
    public function testPasswordRules(string $password, bool $accepted): void
    {
        $this->assertSame($accepted, Auth::passwordError($password) === null);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function passwords(): iterable
    {
        yield 'strong' => [self::PASSWORD, true];
        yield 'too short' => ['Ab1!', false];
        yield 'no uppercase' => ['test-passw0rd!', false];
        yield 'no lowercase' => ['TEST-PASSW0RD!', false];
        yield 'no digit' => ['Test-Password!', false];
        yield 'no symbol' => ['TestPassw0rd1', false];
    }

    public function testEmailValidation(): void
    {
        $this->assertTrue(Auth::validEmail('owner@minilytics.test'));
        $this->assertFalse(Auth::validEmail('not-an-email'));
    }

    public function testCreatesAdminsAndMembers(): void
    {
        $admin = Auth::createUser('admin-' . uniqid() . '@minilytics.test', self::PASSWORD, 'admin');
        $member = Auth::createUser('member-' . uniqid() . '@minilytics.test', self::PASSWORD, 'member');

        $this->assertTrue($this->isAdmin($admin));
        $this->assertFalse($this->isAdmin($member));
    }

    public function testRefusesADuplicateEmail(): void
    {
        $email = 'twice-' . uniqid() . '@minilytics.test';
        Auth::createUser($email, self::PASSWORD, 'member');

        $this->expectException(UserAlreadyExistsException::class);
        Auth::createUser($email, self::PASSWORD, 'member');
    }

    public function testAnMcpTokenAuthenticatesItsOwner(): void
    {
        $email = 'mcp-' . uniqid() . '@minilytics.test';
        $userId = Auth::createUser($email, self::PASSWORD, 'member');
        $token = McpTokens::create($userId, 'Claude');

        $this->assertSame(['id' => $userId, 'email' => $email], McpTokens::authenticate($token['token']));
        $this->assertNull(McpTokens::authenticate('mlt_not-a-real-token'));
    }

    public function testListedTokensNeverExposeTheirValue(): void
    {
        $userId = Auth::createUser('list-' . uniqid() . '@minilytics.test', self::PASSWORD, 'member');
        $token = McpTokens::create($userId, 'Cursor');

        $listed = McpTokens::forUser($userId);
        $this->assertCount(1, $listed);
        $this->assertArrayNotHasKey('token', $listed[0]);
        $this->assertStringNotContainsString($token['token'], (string) json_encode($listed));
    }

    public function testOnlyTheOwnerCanRevokeAToken(): void
    {
        $owner = Auth::createUser('owner-' . uniqid() . '@minilytics.test', self::PASSWORD, 'member');
        $other = Auth::createUser('other-' . uniqid() . '@minilytics.test', self::PASSWORD, 'member');
        $token = McpTokens::create($owner, 'Codex');

        $this->assertFalse(McpTokens::revoke($other, $token['id']));
        $this->assertNotNull(McpTokens::authenticate($token['token']));

        $this->assertTrue(McpTokens::revoke($owner, $token['id']));
        $this->assertNull(McpTokens::authenticate($token['token']));
    }

    private function isAdmin(int $userId): bool
    {
        $stmt = Auth::db()->prepare('SELECT roles_mask FROM users WHERE id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        return ((int) $stmt->execute()->fetchArray(SQLITE3_NUM)[0] & Role::ADMIN) !== 0;
    }
}
