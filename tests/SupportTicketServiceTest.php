<?php
declare(strict_types=1);

use Moves\Boot\Environment;
use Moves\Core\Connection;
use Moves\Services\Support\TicketService;
use PHPUnit\Framework\TestCase;

final class SupportTicketServiceTest extends TestCase
{
    private PDO $pdo;
    private array $ids=[];

    protected function setUp(): void
    {
        Environment::load(dirname(__DIR__));
        $this->pdo=Connection::getInstance();
        foreach(['support_ticket_events','support_tickets'] as $table){$this->pdo->exec("DELETE FROM {$table}");}
        $this->seed();
    }

    public function testCreateIsTenantScopedAndAudited(): void
    {
        $service=new TicketService($this->ids['tenantA']);
        $ticket=$service->create($this->ids['userA'],[
            'subject'=>'Portão social',
            'description'=>'Fechadura não está funcionando.',
            'priority'=>'high',
            'requester_user_id'=>$this->ids['userA'],
            'assigned_user_id'=>$this->ids['userA'],
        ]);
        self::assertSame($this->ids['tenantA'],(int)$ticket['tenant_id']);
        self::assertStringStartsWith('SUP-'.date('Ymd').'-',(string)$ticket['protocol']);
        self::assertNull((new TicketService($this->ids['tenantB']))->findForUser((int)$ticket['id'],$this->ids['userB']));
        $stmt=$this->pdo->prepare("SELECT COUNT(*) FROM support_ticket_events WHERE tenant_id=:tenant AND ticket_id=:ticket AND event_type='created'");
        $stmt->execute(['tenant'=>$this->ids['tenantA'],'ticket'=>$ticket['id']]);
        self::assertSame(1,(int)$stmt->fetchColumn());
    }

    public function testCrossTenantAssignmentRollsBack(): void
    {
        $before=(int)$this->pdo->query('SELECT COUNT(*) FROM support_tickets')->fetchColumn();
        try {
            (new TicketService($this->ids['tenantA']))->create($this->ids['userA'],[
                'subject'=>'Teste',
                'description'=>'Tentativa inválida',
                'assigned_user_id'=>$this->ids['userB'],
            ]);
            self::fail('Atribuição cruzada deveria falhar.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('sem acesso',$exception->getMessage());
        }
        self::assertSame($before,(int)$this->pdo->query('SELECT COUNT(*) FROM support_tickets')->fetchColumn());
    }

    public function testValidationRejectsInvalidInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new TicketService($this->ids['tenantA']))->create($this->ids['userA'],['subject'=>'','description'=>'','priority'=>'invalid']);
    }

    protected function tearDown(): void
    {
        foreach(['support_ticket_events','support_tickets'] as $table){$this->pdo->exec("DELETE FROM {$table}");}
        if($this->ids!==[]){
            $in=implode(',',array_map('intval',[$this->ids['tenantA'],$this->ids['tenantB']]));
            $this->pdo->exec("DELETE FROM talk_tenant_users WHERE tenant_id IN ({$in})");
            $this->pdo->exec("DELETE FROM talk_tenants WHERE id IN ({$in})");
            $users=implode(',',array_map('intval',[$this->ids['userA'],$this->ids['userB']]));
            $this->pdo->exec("DELETE FROM users WHERE id IN ({$users})");
        }
    }

    private function seed(): void
    {
        $suffix=bin2hex(random_bytes(4));
        $u=$this->pdo->prepare("INSERT INTO users(name,email,password,status) VALUES(:name,:email,:password,'active')");
        foreach(['A','B'] as $key){$u->execute(['name'=>'Support '.$key,'email'=>'support-'.strtolower($key).'-'.$suffix.'@test.local','password'=>password_hash('test',PASSWORD_DEFAULT)]);$this->ids['user'.$key]=(int)$this->pdo->lastInsertId();}
        $t=$this->pdo->prepare("INSERT INTO talk_tenants(name,slug,status) VALUES(:name,:slug,'active')");
        foreach(['A','B'] as $key){$t->execute(['name'=>'Tenant '.$key,'slug'=>'support-'.strtolower($key).'-'.$suffix]);$this->ids['tenant'.$key]=(int)$this->pdo->lastInsertId();$m=$this->pdo->prepare("INSERT INTO talk_tenant_users(tenant_id,user_id,role,status) VALUES(:tenant,:user,'agent','active')");$m->execute(['tenant'=>$this->ids['tenant'.$key],'user'=>$this->ids['user'.$key]]);}
    }
}
