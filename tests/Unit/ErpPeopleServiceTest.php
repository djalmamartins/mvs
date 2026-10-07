<?php

declare(strict_types=1);

use Moves\Modules\Erp\People\PeopleService;
use Moves\Modules\Erp\People\PersonLinkRepository;
use Moves\Modules\Erp\People\PersonRepository;
use Moves\Modules\Erp\Structure\PhysicalStructureRepository;
use Moves\Services\Platform\PlatformAudit;
use PHPUnit\Framework\TestCase;

final class ErpPeopleServiceTest extends TestCase
{
    private PDO $pdo;
    private PeopleService $service;

    protected function setUp(): void
    {
        $this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("CREATE TABLE erp_administrators(id INTEGER PRIMARY KEY,tenant_id INTEGER,status TEXT DEFAULT 'active')");
        $this->pdo->exec("CREATE TABLE erp_condominiums(id INTEGER PRIMARY KEY,administrator_id INTEGER,legal_name TEXT,trade_name TEXT,FOREIGN KEY(administrator_id) REFERENCES erp_administrators(id))");
        $this->pdo->exec("CREATE TABLE erp_blocks(id INTEGER PRIMARY KEY AUTOINCREMENT,condominium_id INTEGER,code TEXT,name TEXT,status TEXT DEFAULT 'active')");
        $this->pdo->exec("CREATE TABLE erp_units(id INTEGER PRIMARY KEY AUTOINCREMENT,condominium_id INTEGER,block_id INTEGER,code TEXT,complement TEXT,status TEXT DEFAULT 'active',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(condominium_id,code))");
        $this->pdo->exec("CREATE TABLE erp_people(id INTEGER PRIMARY KEY AUTOINCREMENT,administrator_id INTEGER,entity_type TEXT,full_name TEXT,trade_name TEXT,document_type TEXT,document_number TEXT,email TEXT,phone TEXT,status TEXT,created_by_user_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(administrator_id,document_type,document_number))");
        $this->pdo->exec("CREATE TABLE erp_person_links(id INTEGER PRIMARY KEY AUTOINCREMENT,administrator_id INTEGER,person_id INTEGER,condominium_id INTEGER,unit_id INTEGER,role TEXT,ownership_fraction_pct NUMERIC,starts_at TEXT,ends_at TEXT,status TEXT,source TEXT,created_by_user_id INTEGER,ended_by_user_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $this->pdo->exec('CREATE TABLE platform_audit_events(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,actor_user_id INTEGER,event_type TEXT,subject_type TEXT,subject_id INTEGER,metadata TEXT)');
        $this->pdo->exec("INSERT INTO erp_administrators(id,tenant_id) VALUES(10,100),(20,200)");
        $this->pdo->exec("INSERT INTO erp_condominiums(id,administrator_id,legal_name,trade_name) VALUES(101,10,'Condo A','A'),(102,10,'Condo A 2','A2'),(201,20,'Condo B','B')");
        $this->pdo->exec("INSERT INTO erp_units(id,condominium_id,code,status) VALUES(1001,101,'201','active'),(1002,101,'301','active'),(1003,102,'402','active'),(2001,201,'101','active')");
        $this->service=new PeopleService($this->pdo,new PersonRepository($this->pdo),new PersonLinkRepository($this->pdo),new PhysicalStructureRepository($this->pdo),new PlatformAudit($this->pdo));
    }

    public function testPersonCanHoldManyTemporalRolesAcrossUnitsAndCondominiums(): void
    {
        $created=$this->service->create(100,10,7,['entity_type'=>'person','full_name'=>' João da Silva ','document_type'=>'cpf','document_number'=>'123.456.789-01','email'=>'JOAO@example.com','create_link'=>'1','condominium_id'=>101,'unit_id'=>1001,'role'=>'owner','starts_at'=>'2024-01-01']);
        self::assertTrue($created['created']);self::assertNotNull($created['link_id']);
        $id=$created['id'];
        $this->service->addLink(100,10,7,$id,['condominium_id'=>101,'unit_id'=>1002,'role'=>'owner','starts_at'=>'2024-01-01']);
        $this->service->addLink(100,10,7,$id,['condominium_id'=>102,'unit_id'=>1003,'role'=>'owner','starts_at'=>'2025-01-01']);
        self::assertCount(3,$this->service->detail(10,$id)['links']);
        self::assertSame('João da Silva',$this->service->detail(10,$id)['person']['full_name']);
        self::assertCount(1,$this->service->linksForUnit(10,1001));
    }

    public function testOrganizationKeepsOfficialAlphanumericCnpjAndSearchesIt(): void
    {
        $base = 'AB1234567890';
        $digit = static function (string $value, array $weights): string {
            $sum = 0;
            foreach ($weights as $index => $weight) {
                $sum += (ord($value[$index]) - 48) * $weight;
            }
            $remainder = $sum % 11;
            return (string) ($remainder < 2 ? 0 : 11 - $remainder);
        };
        $cnpj = $base . $digit($base, [5,4,3,2,9,8,7,6,5,4,3,2]);
        $cnpj .= $digit($cnpj, [6,5,4,3,2,9,8,7,6,5,4,3,2]);

        $created = $this->service->create(100, 10, 7, [
            'entity_type'=>'organization',
            'full_name'=>'Empresa alfanumérica',
            'document_type'=>'cnpj',
            'document_number'=>strtolower($cnpj),
        ]);
        $detail = $this->service->detail(10, $created['id']);

        self::assertSame($cnpj, $detail['person']['document_number']);
        $matches = (new PersonRepository($this->pdo))->search(10, ['q'=>$cnpj,'status'=>'','condominium'=>'','role'=>'']);
        self::assertCount(1, $matches);
        self::assertSame('CNPJ · ••••••••' . substr($cnpj, -4), $this->service->search(10, ['q'=>$cnpj,'status'=>'','condominium'=>'','role'=>''])[0]['document_display']);
    }

    public function testOrganizationRejectsInvalidAlphanumericCnpj(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->create(100, 10, 7, [
            'entity_type'=>'organization',
            'full_name'=>'CNPJ inválido',
            'document_type'=>'cnpj',
            'document_number'=>'AB123456789000',
        ]);
    }

    public function testDuplicateDocumentIsNormalizedAndScopedToAdministrator(): void
    {
        $one=$this->service->create(100,10,7,['entity_type'=>'person','full_name'=>'Maria','document_type'=>'cpf','document_number'=>'12345678901']);
        $duplicate=$this->service->create(100,10,7,['entity_type'=>'person','full_name'=>'Maria duplicada','document_type'=>'cpf','document_number'=>'123.456.789-01']);
        $otherAdmin=$this->service->create(200,20,8,['entity_type'=>'person','full_name'=>'Pessoa B','document_type'=>'cpf','document_number'=>'123.456.789-01']);
        self::assertSame($one['id'],$duplicate['id']);self::assertFalse($duplicate['created']);self::assertNotSame($one['id'],$otherAdmin['id']);
    }

    public function testTenantCannotReadPersonAndCrossCondominiumUnitLinkIsRejected(): void
    {
        $person=$this->service->create(100,10,7,['entity_type'=>'organization','full_name'=>'Empresa A','document_type'=>'cnpj','document_number'=>'11222333000181']);
        self::assertNull($this->service->detail(20,$person['id']));
        $this->expectException(InvalidArgumentException::class);
        $this->service->addLink(100,10,7,$person['id'],['condominium_id'=>101,'unit_id'=>2001,'role'=>'owner','starts_at'=>'2026-01-01']);
    }

    public function testClosingLinkPreservesItInHistoryAndRejectsInvalidDates(): void
    {
        $person=$this->service->create(100,10,7,['entity_type'=>'person','full_name'=>'João','create_link'=>'1','condominium_id'=>101,'unit_id'=>1001,'role'=>'owner','starts_at'=>'2024-01-01']);
        self::assertTrue($this->service->endLink(100,10,7,(int)$person['link_id'],'2025-06-30'));
        $links=$this->service->detail(10,$person['id'])['links'];self::assertCount(1,$links);self::assertSame('2025-06-30',$links[0]['ends_at']);self::assertSame('inactive',$links[0]['status']);
        $this->expectException(InvalidArgumentException::class);$this->service->addLink(100,10,7,$person['id'],['condominium_id'=>101,'role'=>'resident','starts_at'=>'2025-02-30']);
    }

    public function testInitialLinkFailureRollsBackPerson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        try{$this->service->create(100,10,7,['entity_type'=>'person','full_name'=>'Atomic','create_link'=>'1','condominium_id'=>201,'role'=>'owner','starts_at'=>'2026-01-01']);}
        finally{self::assertSame(0,(int)$this->pdo->query("SELECT COUNT(*) FROM erp_people WHERE full_name='Atomic'")->fetchColumn());}
    }

    public function testMultipleOwnersCanBeLinkedToSameUnit(): void
    {
        foreach(['Ana','Bia'] as $name){$person=$this->service->create(100,10,7,['entity_type'=>'person','full_name'=>$name]);$this->service->addLink(100,10,7,$person['id'],['condominium_id'=>101,'unit_id'=>1001,'role'=>'owner','starts_at'=>'2026-01-01']);}
        self::assertCount(2,$this->service->linksForUnit(10,1001));
    }


    public function testOwnershipFractionIsOptionalBoundedAndScopedToOwnerUnitLinks(): void
    {
        $person=$this->service->create(100,10,7,['entity_type'=>'person','full_name'=>'Proprietária','create_link'=>'1','condominium_id'=>101,'unit_id'=>1001,'role'=>'owner','ownership_fraction_pct'=>'25,5','starts_at'=>'2026-01-01']);
        $detail=$this->service->detail(10,$person['id']);
        self::assertSame(25.5,(float)$detail['links'][0]['ownership_fraction_pct']);
        self::assertStringContainsString('25.5', (string)$this->pdo->query("SELECT metadata FROM platform_audit_events WHERE event_type='erp.person_link.created'")->fetchColumn());

        $this->expectException(InvalidArgumentException::class);
        $this->service->addLink(100,10,7,$person['id'],['condominium_id'=>101,'unit_id'=>1001,'role'=>'owner','ownership_fraction_pct'=>'100.0001','starts_at'=>'2026-02-01']);
    }

    public function testOwnershipFractionCannotBeAssignedToNonOwnerOrWithoutUnit(): void
    {
        $person=$this->service->create(100,10,7,['entity_type'=>'person','full_name'=>'Morador']);
        try {
            $this->service->addLink(100,10,7,$person['id'],['condominium_id'=>101,'unit_id'=>1001,'role'=>'resident','ownership_fraction_pct'=>'50','starts_at'=>'2026-01-01']);
            self::fail('Fração ideal não pode ser atribuída a não proprietário.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('proprietário',$exception->getMessage());
        }
        $this->expectException(InvalidArgumentException::class);
        $this->service->addLink(100,10,7,$person['id'],['condominium_id'=>101,'unit_id'=>'','role'=>'owner','ownership_fraction_pct'=>'50','starts_at'=>'2026-01-01']);
    }

    public function testFutureEndDateDoesNotPrematurelyDeactivateCurrentLink(): void
    {
        $person=$this->service->create(100,10,7,['entity_type'=>'person','full_name'=>'Vínculo com prazo','create_link'=>'1','condominium_id'=>101,'unit_id'=>1001,'role'=>'resident','starts_at'=>date('Y-m-d'),'ends_at'=>date('Y-m-d',strtotime('+30 days'))]);
        $link=$this->service->detail(10,$person['id'])['links'][0];
        self::assertSame('active',$link['status']);self::assertSame(1,(int)$link['is_current']);
    }
}
