<?php

declare(strict_types=1);

use Moves\Modules\Erp\Cadastros\AdministratorRepository;
use Moves\Modules\Erp\Cadastros\CadastroAccessService;
use Moves\Modules\Erp\Cadastros\CondominiumRepository;
use Moves\Modules\Erp\Security\ScopeGrantRepository;
use Moves\Modules\Erp\Security\ScopedAccess;
use PHPUnit\Framework\TestCase;

final class ErpCadastroAccessServiceTest extends TestCase
{
    private PDO $pdo;
    private CadastroAccessService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE erp_administrators (id INTEGER PRIMARY KEY, legal_name TEXT, trade_name TEXT, tax_id TEXT, status TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE erp_condominiums (id INTEGER PRIMARY KEY, administrator_id INTEGER, legal_name TEXT, trade_name TEXT, tax_id TEXT, timezone TEXT, status TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE erp_scope_grants (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, capability TEXT, scope_type TEXT, scope_id INTEGER, revoked_at TEXT)');

        $this->pdo->exec("INSERT INTO erp_administrators VALUES (10, 'Admin A', NULL, 'A', 'active', '', ''), (20, 'Admin B', NULL, 'B', 'active', '', '')");
        $this->pdo->exec("INSERT INTO erp_condominiums VALUES (101, 10, 'Condo A', NULL, 'CA', 'America/Sao_Paulo', 'active', '', ''), (202, 20, 'Condo B', NULL, 'CB', 'America/Sao_Paulo', 'active', '', '')");

        $access = new ScopedAccess(new ScopeGrantRepository($this->pdo));
        $this->service = new CadastroAccessService(
            new AdministratorRepository($this->pdo),
            new CondominiumRepository($this->pdo),
            $access,
        );
    }

    public function testAdministratorReadRequiresMatchingGrant(): void
    {
        $this->grant(7, 'administrator', 10);

        self::assertSame(10, (int) $this->service->findAdministrator(7, 10)['id']);
        self::assertNull($this->service->findAdministrator(7, 20));
        self::assertNull($this->service->findAdministrator(8, 10));
    }

    public function testCondominiumReadDoesNotCrossTenantBoundary(): void
    {
        $this->grant(7, 'condominium', 101);
        $this->grant(7, 'condominium', 202);

        self::assertSame(101, (int) $this->service->findCondominium(7, 10, 101)['id']);
        self::assertNull($this->service->findCondominium(7, 10, 202));
    }

    public function testRevokedGrantCannotReadCadastro(): void
    {
        $this->grant(7, 'administrator', 10, '2026-09-22 00:00:00');

        self::assertNull($this->service->findAdministrator(7, 10));
        self::assertSame([], $this->service->listCondominiums(7, 10));
    }

    private function grant(int $userId, string $scopeType, int $scopeId, ?string $revokedAt = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO erp_scope_grants (user_id, capability, scope_type, scope_id, revoked_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$userId, 'erp.cadastros.read', $scopeType, $scopeId, $revokedAt]);
    }
}
