<?php
declare(strict_types=1);

use Moves\Modules\Erp\Parties\PartyRepository;
use Moves\Modules\Erp\Parties\PartyService;
use Moves\Modules\Erp\Security\ScopeGrantRepository;
use Moves\Modules\Erp\Security\ScopedAccess;
use PHPUnit\Framework\TestCase;

final class ErpPartyServiceTest extends TestCase
{
    private PDO $pdo;
    private PartyService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE erp_parties (id INTEGER PRIMARY KEY AUTOINCREMENT, condominium_id INTEGER, kind TEXT, legal_name TEXT, trade_name TEXT, tax_id TEXT, email TEXT, phone TEXT, status TEXT DEFAULT "active", created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo->exec('CREATE TABLE erp_scope_grants (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,capability TEXT,scope_type TEXT,scope_id INTEGER,revoked_at TEXT)');
        $this->service = new PartyService(new PartyRepository($this->pdo), new ScopedAccess(new ScopeGrantRepository($this->pdo)));
    }

    public function testWriteRequiresCondominiumScope(): void
    {
        $this->grant(7, 'erp.parties.write', 10);
        self::assertNotNull($this->service->create(7, 10, 'supplier', 'Fornecedor Teste'));
        self::assertNull($this->service->create(8, 10, 'supplier', 'Sem Permissão'));
    }

    public function testReadDoesNotLeakAnotherCondominium(): void
    {
        $repo = new PartyRepository($this->pdo);
        $repo->create(10, 'employee', 'Funcionário Teste');
        $repo->create(20, 'contractor', 'Terceiro Teste');
        $this->grant(7, 'erp.parties.read', 10);
        $rows = $this->service->list(7, 10);
        self::assertCount(1, $rows);
        self::assertSame(10, (int) $rows[0]['condominium_id']);
        self::assertSame([], $this->service->list(7, 20));
    }

    public function testUnsupportedKindIsRejected(): void
    {
        $this->grant(7, 'erp.parties.write', 10);
        $this->expectException(InvalidArgumentException::class);
        $this->service->create(7, 10, 'unknown', 'Inválido');
    }

    private function grant(int $userId, string $capability, int $scopeId): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO erp_scope_grants(user_id,capability,scope_type,scope_id,revoked_at) VALUES(?,?,?,?,NULL)');
        $stmt->execute([$userId,$capability,'condominium',$scopeId]);
    }
}