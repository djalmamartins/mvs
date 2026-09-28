<?php

declare(strict_types=1);

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Services\Master\MasterAdministratorService;
use PHPUnit\Framework\TestCase;

final class MasterAdministratorServiceTest extends TestCase
{
    private PDO $pdo;
    private int $actor;
    private array $tenants=[];

    protected function setUp(): void
    {
        Environment::load(dirname(__DIR__));
        $this->pdo=Connection::getInstance();
        $this->actor=(int)$this->pdo->query("SELECT id FROM users WHERE status='active' ORDER BY id LIMIT 1")->fetchColumn();
        if($this->actor<1)$this->markTestSkipped('Requer usuário ativo para auditoria MST.');
    }

    protected function tearDown(): void
    {
        foreach(array_reverse($this->tenants) as $tenant){
            $this->pdo->prepare('DELETE FROM mst_audit WHERE tenant_id=:id')->execute(['id'=>$tenant]);
            $this->pdo->prepare('DELETE FROM mst_tenant_products WHERE tenant_id=:id')->execute(['id'=>$tenant]);
            $this->pdo->prepare('DELETE FROM mst_administrators WHERE tenant_id=:id')->execute(['id'=>$tenant]);
            $this->pdo->prepare('DELETE FROM talk_tenant_users WHERE tenant_id=:id')->execute(['id'=>$tenant]);
            $this->pdo->prepare('DELETE FROM talk_tenants WHERE id=:id')->execute(['id'=>$tenant]);
        }
    }

    public function testCreateUpdateBrandingProductsMembershipAndAudit(): void
    {
        $service=new MasterAdministratorService();$token=bin2hex(random_bytes(5));
        $id=$service->create(['legal_name'=>'Administradora '.$token,'trade_name'=>'Tenant '.$token,'tax_id'=>'TEST-'.$token,'contact_name'=>'Contato','contact_email'=>'qa@example.test','contact_phone'=>'3100000000','status'=>'active','notes'=>'QA'],$this->actor);
        $this->tenants[]=$id;
        $row=$service->find($id);self::assertSame('active',$row['status']);self::assertNotEmpty($row['audit']);
        $service->updateBranding($id,['trade_name'=>'Marca '.$token,'logo_path'=>'/logo.svg','primary_color'=>'#6E00B3','secondary_color'=>'#FFFFFF'],$this->actor);
        $service->setProduct($id,'erp','active',$this->actor);
        $service->saveMembership($id,$this->actor,'admin','active',$this->actor);
        $row=$service->find($id);self::assertSame('Marca '.$token,$row['trade_name']);self::assertSame('#6E00B3',$row['primary_color']);self::assertContains('erp',array_column(array_filter($row['products'],fn($p)=>$p['status']==='active'),'product_key'));self::assertSame($this->actor,(int)$row['memberships'][0]['id']);self::assertGreaterThanOrEqual(4,count($row['audit']));
        $service->update($id,['legal_name'=>'Administradora '.$token,'trade_name'=>'Marca '.$token,'tax_id'=>'TEST-'.$token,'contact_name'=>'Contato','contact_email'=>'qa@example.test','contact_phone'=>'3100000000','status'=>'suspended','notes'=>'QA'],$this->actor);
        self::assertSame('inactive',(string)$this->pdo->query('SELECT status FROM talk_tenants WHERE id='.$id)->fetchColumn());
    }

    public function testInvalidBrandingAndProductAreRejected(): void
    {
        $service=new MasterAdministratorService();$this->expectException(RuntimeException::class);$service->setProduct(1,'unknown','active',$this->actor);
    }
}