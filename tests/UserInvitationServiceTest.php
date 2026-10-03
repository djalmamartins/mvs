<?php

declare(strict_types=1);

use Moves\Services\Auth\UserInvitationService;
use PHPUnit\Framework\TestCase;

final class UserInvitationServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, password TEXT, status TEXT)');
        $this->pdo->exec('CREATE TABLE talk_tenants (id INTEGER PRIMARY KEY, name TEXT, status TEXT)');
        $this->pdo->exec('CREATE TABLE platform_roles (id INTEGER PRIMARY KEY, tenant_id INTEGER, slug TEXT, name TEXT)');
        $this->pdo->exec('CREATE TABLE talk_tenant_users (tenant_id INTEGER, user_id INTEGER, role TEXT, role_id INTEGER, status TEXT, is_default INTEGER DEFAULT 0, PRIMARY KEY(tenant_id,user_id))');
        $this->pdo->exec('CREATE TABLE platform_user_invitations (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, role_id INTEGER, token_hash TEXT UNIQUE, expires_at TEXT, accepted_at TEXT NULL, revoked_at TEXT NULL, invited_by INTEGER NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo->exec("INSERT INTO users VALUES(1,'Admin','admin@example.com','hash','active'),(2,'Ana','ana@example.com','pending','inactive')");
        $this->pdo->exec("INSERT INTO talk_tenants VALUES(10,'Tenant A','active'),(20,'Tenant B','active')");
        $this->pdo->exec("INSERT INTO platform_roles VALUES(100,10,'agent','Atendente'),(200,20,'agent','Atendente')");
        $this->pdo->exec("INSERT INTO talk_tenant_users(tenant_id,user_id,role,role_id,status) VALUES(10,2,'agent',100,'inactive')");
    }

    public function testCreatesOnlyHashedTokenForMatchingTenantMembership(): void
    {
        $result = (new UserInvitationService($this->pdo))->create(10, 2, 100, 1);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['token']);
        $stored = (string) $this->pdo->query('SELECT token_hash FROM platform_user_invitations')->fetchColumn();
        self::assertNotSame($result['token'], $stored);
        self::assertSame(hash('sha256', $result['token']), $stored);
    }

    public function testRejectsRoleFromAnotherTenant(): void
    {
        $this->expectException(RuntimeException::class);
        (new UserInvitationService($this->pdo))->create(10, 2, 200, 1);
    }

    public function testNewInvitationRevokesPreviousPendingInvitation(): void
    {
        $service = new UserInvitationService($this->pdo);
        $first = $service->create(10, 2, 100, 1);
        $second = $service->create(10, 2, 100, 1);
        self::assertNull($service->valid($first['token']));
        self::assertNotNull($service->valid($second['token']));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM platform_user_invitations WHERE revoked_at IS NOT NULL')->fetchColumn());
    }

    public function testExpiredInvitationIsRejected(): void
    {
        $service = new UserInvitationService($this->pdo);
        $invite = $service->create(10, 2, 100, 1);
        $this->pdo->exec("UPDATE platform_user_invitations SET expires_at='2020-01-01 00:00:00'");
        self::assertNull($service->valid($invite['token']));
        self::assertFalse($service->accept($invite['token'], password_hash('Strong!Pass1', PASSWORD_DEFAULT)));
    }

    public function testAcceptActivatesOnlyInvitedTenantAndCannotBeReused(): void
    {
        $this->pdo->exec("INSERT INTO talk_tenant_users(tenant_id,user_id,role,role_id,status) VALUES(20,2,'agent',200,'inactive')");
        $service = new UserInvitationService($this->pdo);
        $invite = $service->create(10, 2, 100, 1);
        $hash = password_hash('Strong!Pass1', PASSWORD_DEFAULT);

        self::assertTrue($service->accept($invite['token'], $hash));
        self::assertFalse($service->accept($invite['token'], $hash));
        self::assertSame('active', (string) $this->pdo->query('SELECT status FROM users WHERE id=2')->fetchColumn());
        self::assertSame('active', (string) $this->pdo->query('SELECT status FROM talk_tenant_users WHERE tenant_id=10 AND user_id=2')->fetchColumn());
        self::assertSame('inactive', (string) $this->pdo->query('SELECT status FROM talk_tenant_users WHERE tenant_id=20 AND user_id=2')->fetchColumn());
        self::assertNotNull($this->pdo->query('SELECT accepted_at FROM platform_user_invitations')->fetchColumn());
    }
}
