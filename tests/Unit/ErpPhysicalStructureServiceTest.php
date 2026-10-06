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
}
