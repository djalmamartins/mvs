<?php
declare(strict_types=1);

use Moves\Modules\Erp\People\PersonLinkRepository;
use Moves\Modules\Erp\People\PersonRepository;
use Moves\Modules\Erp\People\PeopleService;
use Moves\Modules\Erp\Security\ScopeGrantRepository;
use Moves\Modules\Erp\Security\ScopedAccess;
use PHPUnit\Framework\TestCase;

final class ErpPeopleServiceTest extends TestCase
{
    private PDO $pdo;
    private PersonRepository $people;
    private PeopleService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE erp_people (id INTEGER PRIMARY KEY AUTOINCREMENT, full_name TEXT, document_type TEXT, document_number TEXT, email TEXT, phone TEXT, status TEXT DEFAULT "active")');
        $this->pdo->exec('CREATE TABLE erp_units (id INTEGER PRIMARY KEY, condominium_id INTEGER)');
        $this->pdo->exec('CREATE TABLE erp_person_links (id INTEGER PRIMARY KEY AUTOINCREMENT, person_id INTEGER, condominium_id INTEGER, unit_id INTEGER, role TEXT, starts_at TEXT, ends_at TEXT, status TEXT DEFAULT "active")');
        $this->pdo->exec('CREATE TABLE erp_scope_grants (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,capability TEXT,scope_type TEXT,scope_id INTEGER,revoked_at TEXT)');
        $this->pdo->exec('INSERT INTO erp_units(id,condominium_id) VALUES(101,10),(202,20)');
        $this->people = new PersonRepository($this->pdo);
        $this->service = new PeopleService($this->people, new PersonLinkRepository($this->pdo), new ScopedAccess(new ScopeGrantRepository($this->pdo)));
    }

    public function testPersonIsDeduplicatedByNormalizedDocument(): void
    {
        $a = $this->people->create('Pessoa Teste', 'cpf', '111.222.333-44');
        $b = $this->people->create('Pessoa Duplicada', 'CPF', '11122233344');
        self::assertSame($a, $b);
    }

    public function testPersonCreationRequiresWriteScopeAndDeduplicates(): void
    {
        $this->grant(7, 'erp.people.write', 10);
        $a = $this->service->createPerson(7, 10, 'Pessoa Teste', 'cpf', '111.222.333-44');
        $b = $this->service->createPerson(7, 10, 'Pessoa Duplicada', 'CPF', '11122233344');
        self::assertNotNull($a);
        self::assertSame($a, $b);
        self::assertNull($this->service->createPerson(8, 10, 'Sem Permissão'));
    }

    public function testLinkRequiresMatchingScopeAndRejectsCrossCondominiumUnit(): void
    {
        $personId = $this->people->create('Pessoa Teste');
        $this->grant(7, 'erp.people.write', 10);
        self::assertNotNull($this->service->createLink(7, $personId, 10, 101, 'owner', '2026-01-01'));
        self::assertNull($this->service->createLink(8, $personId, 10, 101, 'resident', '2026-01-01'));
        $this->expectException(InvalidArgumentException::class);
        $this->service->createLink(7, $personId, 10, 202, 'owner', '2026-01-01');
    }

    public function testTimelineRequiresReadScopeAndPreservesEndedLink(): void
    {
        $personId = $this->people->create('Pessoa Teste');
        $this->grant(7, 'erp.people.write', 10);
        $this->grant(7, 'erp.people.read', 10);
        $linkId = $this->service->createLink(7, $personId, 10, 101, 'tenant', '2026-01-01');
        self::assertNotNull($linkId);
        self::assertTrue($this->service->endLink(7, $linkId, 10, '2026-06-30'));
        $timeline = $this->service->timeline(7, $personId, 10);
        self::assertCount(1, $timeline);
        self::assertSame('2026-06-30', $timeline[0]['ends_at']);
        self::assertSame('inactive', $timeline[0]['status']);
        self::assertSame([], $this->service->timeline(8, $personId, 10));
    }

    public function testInvalidLinkDateIsRejected(): void
    {
        $personId = $this->people->create('Pessoa Teste');
        $this->grant(7, 'erp.people.write', 10);
        $this->expectException(InvalidArgumentException::class);
        $this->service->createLink(7, $personId, 10, 101, 'owner', '2026-02-30');
    }

    private function grant(int $userId, string $capability, int $scopeId): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO erp_scope_grants(user_id,capability,scope_type,scope_id,revoked_at) VALUES(?,?,?,?,NULL)');
        $stmt->execute([$userId,$capability,'condominium',$scopeId]);
    }
}