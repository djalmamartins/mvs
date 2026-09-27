<?php

declare(strict_types=1);

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Modules\Erp\Cadastros\AdministratorRepository;
use Moves\Modules\Erp\Cadastros\CadastroServiceFactory;
use Moves\Modules\Erp\Security\AdministratorTenantAccess;
use Moves\Services\Talk\TalkTenantContext;
use PHPUnit\Framework\TestCase;

final class PlatformTenantIntegrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        Environment::load(dirname(__DIR__));
        $this->pdo = Connection::getInstance();
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testAdministratorsShareCanonicalTenantsAndProductsStayIsolated(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $administrators = new AdministratorRepository($this->pdo);
        $administratorA = $administrators->create('Administradora A', null, 'A-' . $suffix);
        $administratorB = $administrators->create('Administradora B', null, 'B-' . $suffix);
        $tenantA = $this->tenantId($administratorA);
        $tenantB = $this->tenantId($administratorB);
        self::assertNotSame($tenantA, $tenantB);

        $userA = $this->user('a-' . $suffix . '@example.test');
        $userB = $this->user('b-' . $suffix . '@example.test');
        $this->membership($tenantA, $userA);
        $this->membership($tenantB, $userB);
        $this->grant($userA, $administratorA);
        $this->grant($userA, $administratorB);
        $this->grant($userB, $administratorB);

        $access = CadastroServiceFactory::access($this->pdo);
        $tenantAccess = new AdministratorTenantAccess($this->pdo);
        self::assertTrue($tenantAccess->hasAnyAdministrator($userA));
        self::assertNotNull($access->findAdministrator($userA, $administratorA));
        self::assertNull($access->findAdministrator($userA, $administratorB));
        self::assertNotNull($access->findAdministrator($userB, $administratorB));

        $this->pdo->prepare("UPDATE platform_tenant_products SET enabled=0 WHERE tenant_id=? AND product='erp'")
            ->execute([$tenantA]);
        self::assertFalse($tenantAccess->hasAnyAdministrator($userA));
        self::assertNull($access->findAdministrator($userA, $administratorA));
        $this->pdo->prepare("UPDATE platform_tenant_products SET enabled=1 WHERE tenant_id=? AND product='erp'")
            ->execute([$tenantA]);

        try {
            (new TalkTenantContext())->forUser($userA, $tenantA);
            self::fail('ERP-only tenant unexpectedly exposed Talk.');
        } catch (RuntimeException) {
            // Product gate denies Talk until it is explicitly enabled.
        }

        $this->pdo->prepare("INSERT INTO platform_tenant_products(tenant_id,product,enabled) VALUES(?,'talk',1)")
            ->execute([$tenantA]);
        self::assertSame($tenantA, (new TalkTenantContext())->forUser($userA, $tenantA));
    }

    private function tenantId(int $administratorId): int
    {
        $statement = $this->pdo->prepare('SELECT tenant_id FROM erp_administrators WHERE id=?');
        $statement->execute([$administratorId]);
        return (int) $statement->fetchColumn();
    }

    private function user(string $email): int
    {
        $statement = $this->pdo->prepare("INSERT INTO users(name,email,password,status,role) VALUES('Tester',?,'test-only','active','user')");
        $statement->execute([$email]);
        return (int) $this->pdo->lastInsertId();
    }

    private function membership(int $tenantId, int $userId): void
    {
        $this->pdo->prepare("INSERT INTO talk_tenant_users(tenant_id,user_id,role,status,is_default) VALUES(?,?,'agent','active',0)")
            ->execute([$tenantId, $userId]);
    }

    private function grant(int $userId, int $administratorId): void
    {
        $this->pdo->prepare("INSERT INTO erp_scope_grants(user_id,capability,scope_type,scope_id) VALUES(?,'erp.cadastros.read','administrator',?)")
            ->execute([$userId, $administratorId]);
    }
}
