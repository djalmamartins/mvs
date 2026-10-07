<?php

declare(strict_types=1);

use Moves\Modules\Erp\Structure\PhysicalStructureRepository;
use Moves\Modules\Erp\Structure\PhysicalStructureService;
use Moves\Services\Platform\PlatformAudit;
use PHPUnit\Framework\TestCase;

final class ErpPhysicalStructureServiceTest extends TestCase
{
    private PDO $pdo;
    private PhysicalStructureService $service;

    protected function setUp(): void
    {
        $this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE erp_administrators(id INTEGER PRIMARY KEY,tenant_id INTEGER)');
        $this->pdo->exec('CREATE TABLE erp_condominiums(id INTEGER PRIMARY KEY,administrator_id INTEGER,legal_name TEXT,trade_name TEXT)');
        $this->pdo->exec("CREATE TABLE erp_blocks(id INTEGER PRIMARY KEY AUTOINCREMENT,condominium_id INTEGER,code TEXT,name TEXT,status TEXT DEFAULT 'active',UNIQUE(condominium_id,code))");
        $this->pdo->exec("CREATE TABLE erp_units(id INTEGER PRIMARY KEY AUTOINCREMENT,condominium_id INTEGER,block_id INTEGER,code TEXT,complement TEXT,status TEXT DEFAULT 'active',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(condominium_id,code))");
        $this->pdo->exec('CREATE TABLE platform_audit_events(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,actor_user_id INTEGER,event_type TEXT,subject_type TEXT,subject_id INTEGER,metadata TEXT)');
        $this->pdo->exec('INSERT INTO erp_administrators(id,tenant_id) VALUES(10,100),(20,200)');
        $this->pdo->exec("INSERT INTO erp_condominiums(id,administrator_id,legal_name,trade_name) VALUES(101,10,'A','A'),(201,20,'B','B')");
        $this->service=new PhysicalStructureService($this->pdo,new PhysicalStructureRepository($this->pdo),new PlatformAudit($this->pdo));
    }

    public function testCreatesUnitAndReusesBlockWithinCondominium(): void
    {
        $one=$this->service->createUnit(100,10,7,['condominium_id'=>101,'block'=>'Torre A','code'=>'201']);
        $two=$this->service->createUnit(100,10,7,['condominium_id'=>101,'block'=>'torre a','code'=>'301']);
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM erp_blocks')->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query('SELECT block_id FROM erp_units WHERE id='.(int)$one)->fetchColumn());
        self::assertNotSame($one,$two);self::assertCount(2,$this->service->units(10));self::assertNull($this->service->unit(20,$one));
    }

    public function testCondominiumOutsideTenantCannotCreateUnit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->createUnit(100,10,7,['condominium_id'=>201,'block'=>'','code'=>'101']);
    }

    public function testDuplicateUnitCodeFailsWithoutPartialBlockOrAudit(): void
    {
        $this->service->createUnit(100,10,7,['condominium_id'=>101,'block'=>'New Tower','code'=>'201']);
        try{$this->service->createUnit(100,10,7,['condominium_id'=>101,'block'=>'Other Tower','code'=>'201']);self::fail('duplicate should fail');}
        catch(PDOException){}
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM erp_blocks')->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM platform_audit_events')->fetchColumn());
    }

    public function testAuditFailureRollsBackUnitAndBlock(): void
    {
        $this->pdo->exec("CREATE TRIGGER fail_audit BEFORE INSERT ON platform_audit_events BEGIN SELECT RAISE(ABORT,'audit unavailable'); END");
        try{$this->service->createUnit(100,10,7,['condominium_id'=>101,'block'=>'Atomic','code'=>'202']);self::fail('audit should fail');}catch(PDOException){}
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM erp_units')->fetchColumn());self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM erp_blocks')->fetchColumn());
    }

    public function testUpdatesOnlyUnitIdentifierAndComplementAndAuditsChange(): void
    {
        $id=$this->service->createUnit(100,10,7,['condominium_id'=>101,'block'=>'Torre A','code'=>'201','complement'=>'Fundos']);
        $before=$this->service->unit(10,$id);
        self::assertTrue($this->service->updateUnit(100,10,7,$id,['code'=>'201-B','complement'=>'Cobertura']));
        $after=$this->service->unit(10,$id);
        self::assertSame('201-B',$after['code']);
        self::assertSame('Cobertura',$after['complement']);
        self::assertSame($before['condominium_id'],$after['condominium_id']);
        self::assertSame($before['block_id'],$after['block_id']);
        self::assertSame($before['status'],$after['status']);
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM platform_audit_events')->fetchColumn());
        self::assertStringContainsString('201-B',(string)$this->pdo->query("SELECT metadata FROM platform_audit_events WHERE event_type='erp.unit.updated'")->fetchColumn());
    }

    public function testUpdateRejectsDuplicateIdentifierWithoutChangingUnitOrAudit(): void
    {
        $first=$this->service->createUnit(100,10,7,['condominium_id'=>101,'block'=>'','code'=>'201']);
        $this->service->createUnit(100,10,7,['condominium_id'=>101,'block'=>'','code'=>'202']);
        try {
            $this->service->updateUnit(100,10,7,$first,['code'=>'202','complement'=>'conflict']);
            self::fail('duplicate identifier should be rejected');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('Já existe',$exception->getMessage());
        }
        self::assertSame('201',$this->service->unit(10,$first)['code']);
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM platform_audit_events')->fetchColumn());
    }

    public function testUnitOutsideAdministratorCannotBeUpdated(): void
    {
        $unitId=$this->service->createUnit(100,10,7,['condominium_id'=>101,'block'=>'','code'=>'201']);
        self::assertFalse($this->service->updateUnit(100,20,8,$unitId,['code'=>'999','complement'=>'cross-tenant']));
        self::assertSame('201',$this->service->unit(10,$unitId)['code']);
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM platform_audit_events')->fetchColumn());
    }

    public function testUpdateRejectsNonTextFieldsWithoutChangingUnitOrAudit(): void
    {
        $unitId=$this->service->createUnit(100,10,7,['condominium_id'=>101,'block'=>'','code'=>'201']);
        try {
            $this->service->updateUnit(100,10,7,$unitId,['code'=>['202'],'complement'=>'invalid']);
            self::fail('non-text identifier should be rejected');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('devem ser texto',$exception->getMessage());
        }
        self::assertSame('201',$this->service->unit(10,$unitId)['code']);
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM platform_audit_events')->fetchColumn());
    }

    public function testAuditFailureRollsBackUnitUpdate(): void
    {
        $unitId=$this->service->createUnit(100,10,7,['condominium_id'=>101,'block'=>'','code'=>'201','complement'=>'Antes']);
        $this->pdo->exec("CREATE TRIGGER fail_audit BEFORE INSERT ON platform_audit_events BEGIN SELECT RAISE(ABORT,'audit unavailable'); END");
        try {
            $this->service->updateUnit(100,10,7,$unitId,['code'=>'202','complement'=>'Depois']);
            self::fail('audit failure should roll back the unit update');
        } catch (PDOException) {
        }
        $unit=$this->service->unit(10,$unitId);
        self::assertSame('201',$unit['code']);
        self::assertSame('Antes',$unit['complement']);
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM platform_audit_events')->fetchColumn());
    }
}
