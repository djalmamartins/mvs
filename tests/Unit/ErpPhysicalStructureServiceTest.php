<?php
declare(strict_types=1);
use Moves\Modules\Erp\Security\ScopeGrantRepository;
use Moves\Modules\Erp\Security\ScopedAccess;
use Moves\Modules\Erp\Structure\PhysicalStructureRepository;
use Moves\Modules\Erp\Structure\PhysicalStructureService;
use PHPUnit\Framework\TestCase;
final class ErpPhysicalStructureServiceTest extends TestCase {
 private PDO $pdo; private PhysicalStructureService $service;
 protected function setUp(): void {
  $this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  $this->pdo->exec('CREATE TABLE erp_blocks (id INTEGER PRIMARY KEY AUTOINCREMENT, condominium_id INTEGER, code TEXT, name TEXT, status TEXT DEFAULT "active")');
  $this->pdo->exec('CREATE TABLE erp_units (id INTEGER PRIMARY KEY AUTOINCREMENT, condominium_id INTEGER, block_id INTEGER, code TEXT, ideal_fraction REAL, status TEXT DEFAULT "active")');
  $this->pdo->exec('CREATE TABLE erp_scope_grants (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,capability TEXT,scope_type TEXT,scope_id INTEGER,revoked_at TEXT)');
  $this->pdo->exec("INSERT INTO erp_blocks(condominium_id,code,name) VALUES(10,'A','Bloco A'),(20,'B','Bloco B')");
  $this->service=new PhysicalStructureService(new PhysicalStructureRepository($this->pdo),new ScopedAccess(new ScopeGrantRepository($this->pdo)));
 }
 public function testWriteRequiresMatchingCondominiumGrant(): void {
  $this->grant(7,'erp.structure.write',10);
  self::assertNotNull($this->service->createUnit(7,10,1,'101',0.01));
  self::assertNull($this->service->createUnit(8,10,1,'102',0.01));
 }
 public function testCrossCondominiumBlockIsRejected(): void {
  $this->grant(7,'erp.structure.write',10);
  $this->expectException(InvalidArgumentException::class);$this->service->createUnit(7,10,2,'101',0.01);
 }
 public function testBulkImportReportsInvalidRowsWithoutCrossTenantWrite(): void {
  $this->grant(7,'erp.structure.write',10);
  $result=$this->service->importUnits(7,10,[['code'=>'101','block_id'=>1,'ideal_fraction'=>0.02],['code'=>'102','block_id'=>2]]);
  self::assertCount(1,$result['created']);self::assertCount(1,$result['errors']);
 }
 private function grant(int $userId,string $capability,int $scopeId): void {
  $stmt=$this->pdo->prepare('INSERT INTO erp_scope_grants(user_id,capability,scope_type,scope_id,revoked_at) VALUES(?,?,?, ?,NULL)');
  $stmt->execute([$userId,$capability,'condominium',$scopeId]);
 }
}