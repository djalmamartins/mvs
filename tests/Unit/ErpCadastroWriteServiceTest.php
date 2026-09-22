<?php

declare(strict_types=1);

use Moves\Modules\Erp\Cadastros\AdministratorRepository;
use Moves\Modules\Erp\Cadastros\CadastroWriteService;
use Moves\Modules\Erp\Cadastros\CondominiumRepository;
use Moves\Modules\Erp\Security\ScopeGrantRepository;
use Moves\Modules\Erp\Security\ScopedAccess;
use Moves\Modules\Erp\Security\SecurityAuditRepository;
use PHPUnit\Framework\TestCase;

final class ErpCadastroWriteServiceTest extends TestCase
{
    private PDO $pdo;
    private CadastroWriteService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE erp_administrators (id INTEGER PRIMARY KEY, legal_name TEXT, trade_name TEXT, tax_id TEXT, status TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE erp_condominiums (id INTEGER PRIMARY KEY AUTOINCREMENT, administrator_id INTEGER, legal_name TEXT, trade_name TEXT, tax_id TEXT, timezone TEXT, status TEXT DEFAULT \'active\', created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo->exec('CREATE TABLE erp_scope_grants (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, capability TEXT, scope_type TEXT, scope_id INTEGER, revoked_at TEXT)');
        $this->pdo->exec('CREATE TABLE erp_security_audit (id INTEGER PRIMARY KEY AUTOINCREMENT, event_type TEXT, actor_user_id INTEGER, subject_user_id INTEGER, metadata_json TEXT, created_at TEXT)');
        $this->pdo->exec("INSERT INTO erp_administrators VALUES (10, 'Admin A', NULL, 'A', 'active', '', ''), (20, 'Admin B', NULL, 'B', 'active', '', '')");

        $this->service = new CadastroWriteService(
            new AdministratorRepository($this->pdo),
            new CondominiumRepository($this->pdo),
            new ScopedAccess(new ScopeGrantRepository($this->pdo)),
            new SecurityAuditRepository($this->pdo),
        );
    }

    public function testCreateCondominiumRequiresWriteGrantForAdministratorScope(): void
    {
        $this->grant(7, 10);

        $id = $this->service->createCondominium(7, 10, 'Condomínio A', null, '123', 'America/Sao_Paulo');
        self::assertNotNull($id);
        self::assertSame('Condomínio A', $this->pdo->query('SELECT legal_name FROM erp_condominiums WHERE id = '.(int) $id)->fetchColumn());

        self::assertNull($this->service->createCondominium(7, 20, 'Condomínio B', null, '456'));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM erp_condominiums')->fetchColumn());
    }

    public function testSuccessfulWriteAppendsActorAndScopeMetadataToAudit(): void
    {
        $this->grant(7, 10);
        $id = $this->service->createCondominium(7, 10, 'Condomínio A', null, '123');

        $audit = $this->pdo->query('SELECT event_type, actor_user_id, metadata_json FROM erp_security_audit')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('erp.cadastros.condominium.created', $audit['event_type']);
        self::assertSame(7, (int) $audit['actor_user_id']);
        self::assertSame(['administrator_id' => 10, 'condominium_id' => $id], json_decode((string) $audit['metadata_json'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function testDeniedWriteDoesNotCreateAuditEvent(): void
    {
        self::assertNull($this->service->createCondominium(8, 10, 'Sem acesso', null, '999'));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM erp_security_audit')->fetchColumn());
    }

    private function grant(int $userId, int $administratorId): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO erp_scope_grants (user_id, capability, scope_type, scope_id, revoked_at) VALUES (?, ?, ?, ?, NULL)');
        $stmt->execute([$userId, 'erp.cadastros.write', 'administrator', $administratorId]);
    }
}
