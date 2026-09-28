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
    private array $users=[];

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
            $this->pdo->prepare('DELETE FROM mst_user_invitations WHERE tenant_id=:id')->execute(['id'=>$tenant]);
            $this->pdo->prepare('DELETE FROM mst_audit WHERE tenant_id=:id')->execute(['id'=>$tenant]);
            $this->pdo->prepare('DELETE FROM mst_tenant_products WHERE tenant_id=:id')->execute(['id'=>$tenant]);
            $this->pdo->prepare('DELETE FROM mst_administrators WHERE tenant_id=:id')->execute(['id'=>$tenant]);
            $this->pdo->prepare('DELETE FROM talk_tenant_users WHERE tenant_id=:id')->execute(['id'=>$tenant]);
            $this->pdo->prepare('DELETE FROM talk_tenants WHERE id=:id')->execute(['id'=>$tenant]);
        }
        foreach($this->users as $user){$this->pdo->prepare('DELETE FROM users WHERE id=:id')->execute(['id'=>$user]);}
    }

    public function testCreateUpdateBrandingProductsMembershipAndAudit(): void
    {
        $service=new MasterAdministratorService();$token=bin2hex(random_bytes(5));
        $id=$service->create(['legal_name'=>'Administradora '.$token,'trade_name'=>'Tenant '.$token,'tax_id'=>'TEST-'.$token,'contact_name'=>'Contato','contact_email'=>'qa@example.test','contact_phone'=>'3100000000','status'=>'active','notes'=>'QA'],$this->actor);
        $this->tenants[]=$id;
        $row=$service->find($id);self::assertSame('active',$row['status']);self::assertNotEmpty($row['audit']);
        $service->updateBranding($id,['trade_name'=>'Marca '.$token,'logo_path'=>'/logo.svg','primary_color'=>'#6E00B3','secondary_color'=>'#FFFFFF'],$this->actor);
        $service->setProduct($id,'erp','active',$this->actor);
        $service->updateSecurity($id,['require_mfa'=>1,'session_timeout_minutes'=>60,'allowed_email_domains'=>'moves.com.br'],$this->actor);
        $service->saveMembership($id,$this->actor,'admin','active',$this->actor);
        $row=$service->find($id);self::assertSame('Marca '.$token,$row['trade_name']);self::assertSame('#6E00B3',$row['primary_color']);self::assertSame(1,(int)$row['security']['require_mfa']);self::assertSame(60,(int)$row['security']['session_timeout_minutes']);self::assertContains('erp',array_column(array_filter($row['products'],fn($p)=>$p['status']==='active'),'product_key'));self::assertSame($this->actor,(int)$row['memberships'][0]['id']);self::assertGreaterThanOrEqual(5,count($row['audit']));
        $service->update($id,['legal_name'=>'Administradora '.$token,'trade_name'=>'Marca '.$token,'tax_id'=>'TEST-'.$token,'contact_name'=>'Contato','contact_email'=>'qa@example.test','contact_phone'=>'3100000000','status'=>'suspended','notes'=>'QA'],$this->actor);
        self::assertSame('active',(string)$this->pdo->query('SELECT status FROM talk_tenants WHERE id='.$id)->fetchColumn());
        $service->updateStatus($id,'suspended','CONFIRMAR','Suspensão de QA',$this->actor);self::assertSame('inactive',(string)$this->pdo->query('SELECT status FROM talk_tenants WHERE id='.$id)->fetchColumn());
    }

    public function testInvitationCreatesMembershipAndAudit(): void
    {
        $service=new MasterAdministratorService();$token=bin2hex(random_bytes(5));$id=$service->create(['legal_name'=>'Invite '.$token,'trade_name'=>'Invite '.$token,'tax_id'=>'INV-'.$token,'contact_name'=>'','contact_email'=>'','contact_phone'=>'','status'=>'active','notes'=>''],$this->actor);$this->tenants[]=$id;
        $email='mst-'.$token.'@example.test';$invitation=$service->inviteUser($id,['name'=>'Usuário MST','email'=>$email,'role'=>'supervisor'],$this->actor);$user=$invitation['user_id'];$this->users[]=$user;$row=$service->find($id);
        self::assertSame($email,$row['memberships'][0]['email']);self::assertSame('supervisor',$row['memberships'][0]['role']);self::assertSame('inactive',$row['memberships'][0]['global_status']);self::assertSame('inactive',$row['memberships'][0]['membership_status']);self::assertContains('mst.membership.invited',array_column($row['audit'],'event_type'));
    }

    public function testInvitationActivationIsAtomicAndSingleUse(): void
    {
        $service=new MasterAdministratorService();$token=bin2hex(random_bytes(5));$id=$service->create(['legal_name'=>'Activation '.$token,'trade_name'=>'Activation '.$token,'tax_id'=>'ACT-'.$token,'contact_name'=>'','contact_email'=>'','contact_phone'=>'','status'=>'active','notes'=>''],$this->actor);$this->tenants[]=$id;
        $invitation=$service->inviteUser($id,['name'=>'Convite MST','email'=>'activate-'.$token.'@example.test','role'=>'agent'],$this->actor);$userId=$invitation['user_id'];$this->users[]=$userId;self::assertNotNull($invitation['invitation_token']);
        $service->acceptInvitation((string)$invitation['invitation_token'],'SenhaSegura!123');
        $user=$this->pdo->query('SELECT status FROM users WHERE id='.$userId)->fetchColumn();self::assertSame('active',$user);
        $membership=$this->pdo->query('SELECT status FROM talk_tenant_users WHERE tenant_id='.$id.' AND user_id='.$userId)->fetchColumn();self::assertSame('active',$membership);
        $this->expectException(RuntimeException::class);$this->expectExceptionMessage('inválido ou expirado');$service->acceptInvitation((string)$invitation['invitation_token'],'OutraSenha!123');
    }

    public function testExpiredInvitationCannotBeAccepted(): void
    {
        $service=new MasterAdministratorService();$token=bin2hex(random_bytes(5));$id=$service->create(['legal_name'=>'Expired '.$token,'trade_name'=>'Expired '.$token,'tax_id'=>'EXP-'.$token,'contact_name'=>'','contact_email'=>'','contact_phone'=>'','status'=>'active','notes'=>''],$this->actor);$this->tenants[]=$id;
        $invitation=$service->inviteUser($id,['name'=>'Convite Expirado','email'=>'expired-'.$token.'@example.test','role'=>'agent'],$this->actor);$userId=$invitation['user_id'];$this->users[]=$userId;$this->pdo->prepare('UPDATE mst_user_invitations SET expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE tenant_id=:tenant AND user_id=:user')->execute(['tenant'=>$id,'user'=>$userId]);
        $this->expectException(RuntimeException::class);$this->expectExceptionMessage('inválido ou expirado');$service->acceptInvitation((string)$invitation['invitation_token'],'SenhaSegura!123');
    }

    public function testLastActiveAdminCannotBeRemoved(): void
    {
        $service=new MasterAdministratorService();$token=bin2hex(random_bytes(5));$id=$service->create(['legal_name'=>'Admin Guard '.$token,'trade_name'=>'Admin Guard '.$token,'tax_id'=>'ADM-'.$token,'contact_name'=>'','contact_email'=>'','contact_phone'=>'','status'=>'active','notes'=>''],$this->actor);$this->tenants[]=$id;$service->saveMembership($id,$this->actor,'admin','active',$this->actor);
        $this->expectException(RuntimeException::class);$this->expectExceptionMessage('ao menos um administrador ativo');$service->saveMembership($id,$this->actor,'agent','active',$this->actor);
    }

    public function testDuplicateTaxIdIsRejected(): void
    {
        $service=new MasterAdministratorService();$token=bin2hex(random_bytes(5));$data=['legal_name'=>'Duplicada '.$token,'trade_name'=>'Duplicada '.$token,'tax_id'=>'DUP-'.$token,'contact_name'=>'','contact_email'=>'','contact_phone'=>'','status'=>'active','notes'=>''];$id=$service->create($data,$this->actor);$this->tenants[]=$id;
        $this->expectException(RuntimeException::class);$this->expectExceptionMessage('Já existe uma administradora');$service->create($data,$this->actor);
    }

    public function testLifecycleRequiresExplicitConfirmation(): void
    {
        $service=new MasterAdministratorService();$token=bin2hex(random_bytes(5));$id=$service->create(['legal_name'=>'Lifecycle '.$token,'trade_name'=>'Lifecycle '.$token,'tax_id'=>'LIFE-'.$token,'contact_name'=>'','contact_email'=>'','contact_phone'=>'','status'=>'active','notes'=>''],$this->actor);$this->tenants[]=$id;
        try{$service->updateStatus($id,'suspended','','Motivo válido',$this->actor);self::fail('Suspensão deve exigir confirmação.');}catch(RuntimeException $e){self::assertStringContainsString('CONFIRMAR',$e->getMessage());}
        self::assertSame('active',$service->find($id)['status']);
    }

    public function testSearchPaginatesAndReturnsProducts(): void
    {
        $service=new MasterAdministratorService();$result=$service->search('', '', 1, 10);self::assertArrayHasKey('pages',$result);self::assertArrayHasKey('filtered',$result);self::assertLessThanOrEqual(10,count($result['items']));if($result['items']!==[])self::assertArrayHasKey('products',$result['items'][0]);
    }

    public function testBrandingRejectsUnsafeLogoSource(): void
    {
        $service=new MasterAdministratorService();$token=bin2hex(random_bytes(5));$id=$service->create(['legal_name'=>'Brand '.$token,'trade_name'=>'Brand '.$token,'tax_id'=>'BR-'.$token,'contact_name'=>'','contact_email'=>'','contact_phone'=>'','status'=>'active','notes'=>''],$this->actor);$this->tenants[]=$id;
        $this->expectException(RuntimeException::class);
        $service->updateBranding($id,['trade_name'=>'Brand','logo_path'=>'javascript:alert(1)','primary_color'=>'#6E00B3','secondary_color'=>''],$this->actor);
    }

    public function testProductEntitlementPersistsActiveAndInactiveStates(): void
    {
        $service=new MasterAdministratorService();$token=bin2hex(random_bytes(5));$id=$service->create(['legal_name'=>'Entitlement '.$token,'trade_name'=>'Entitlement '.$token,'tax_id'=>'ENT-'.$token,'contact_name'=>'','contact_email'=>'','contact_phone'=>'','status'=>'active','notes'=>''],$this->actor);$this->tenants[]=$id;
        $service->setProduct($id,'talk','active',$this->actor);$row=$service->find($id);$products=array_column($row['products'],'status','product_key');self::assertSame('active',$products['talk']);
        $service->setProduct($id,'talk','inactive',$this->actor);$row=$service->find($id);$products=array_column($row['products'],'status','product_key');self::assertSame('inactive',$products['talk']);self::assertGreaterThanOrEqual(2,count(array_filter($row['audit'],static fn(array $event): bool=>$event['event_type']==='mst.product.updated')));
    }

    public function testInvalidBrandingAndProductAreRejected(): void
    {
        $service=new MasterAdministratorService();$this->expectException(RuntimeException::class);$service->setProduct(1,'unknown','active',$this->actor);
    }
}