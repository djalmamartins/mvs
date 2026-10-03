<?php
declare(strict_types=1);

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Services\Day\DayService;
use PHPUnit\Framework\TestCase;

final class DayTenantIsolationTest extends TestCase
{
    private PDO $pdo;
    private string $prefix;
    /** @var array<string,int> */
    private array $a=[];
    /** @var array<string,int> */
    private array $b=[];

    protected function setUp(): void
    {
        Environment::load(dirname(__DIR__));
        $this->pdo=Connection::getInstance();
        $this->prefix='day-'.bin2hex(random_bytes(5));
        $this->a=$this->fixture('a');
        $this->b=$this->fixture('b');
    }

    protected function tearDown(): void
    {
        foreach([$this->a,$this->b] as $fixture){
            if(isset($fixture['tenant'])){
                foreach(['day_events','day_tasks','talk_tenant_users'] as $table){
                    $this->pdo->prepare("DELETE FROM {$table} WHERE tenant_id=:tenant")->execute(['tenant'=>$fixture['tenant']]);
                }
                $this->pdo->prepare('DELETE FROM platform_roles WHERE tenant_id=:tenant')->execute(['tenant'=>$fixture['tenant']]);
                $this->pdo->prepare('DELETE FROM talk_tenants WHERE id=:id')->execute(['id'=>$fixture['tenant']]);
            }
            if(isset($fixture['user']))$this->pdo->prepare('DELETE FROM users WHERE id=:id')->execute(['id'=>$fixture['user']]);
        }
    }

    public function testTasksAndEventsNeverCrossTenantOrAssignee(): void
    {
        $a=new DayService($this->a['tenant']);
        $b=new DayService($this->b['tenant']);
        self::assertSame([$this->a['task']],array_map('intval',array_column($a->tasks($this->a['user']),'id')));
        self::assertSame([$this->b['task']],array_map('intval',array_column($b->tasks($this->b['user']),'id')));
        self::assertSame([], $a->tasks($this->b['user']));
        self::assertSame([$this->a['event']],array_map('intval',array_column($a->events($this->a['user']),'id')));
        self::assertSame([], $a->events($this->b['user']));
    }

    public function testTaskStatusCannotMutateAnotherTenantOrUser(): void
    {
        $a=new DayService($this->a['tenant']);
        self::assertFalse($a->setTaskStatus($this->b['task'],$this->a['user'],'done'));
        self::assertFalse($a->setTaskStatus($this->a['task'],$this->b['user'],'done'));
        self::assertTrue($a->setTaskStatus($this->a['task'],$this->a['user'],'done'));
        $s=$this->pdo->prepare('SELECT status FROM day_tasks WHERE id=:id');
        $s->execute(['id'=>$this->a['task']]);self::assertSame('done',$s->fetchColumn());
        $s->execute(['id'=>$this->b['task']]);self::assertSame('pending',$s->fetchColumn());
    }

    /** @return array<string,int> */
    private function fixture(string $suffix): array
    {
        $slug=$this->prefix.'-'.$suffix;
        $s=$this->pdo->prepare("INSERT INTO users(name,email,password,status,role) VALUES(:name,:email,'test-only','active','admin')");
        $s->execute(['name'=>'Day '.strtoupper($suffix),'email'=>$slug.'@example.test']);$user=(int)$this->pdo->lastInsertId();
        $s=$this->pdo->prepare("INSERT INTO talk_tenants(name,slug,status) VALUES(:name,:slug,'active')");
        $s->execute(['name'=>'Day tenant '.strtoupper($suffix),'slug'=>$slug]);$tenant=(int)$this->pdo->lastInsertId();
        $s=$this->pdo->prepare("INSERT INTO platform_roles(tenant_id,slug,name,is_system) VALUES(:tenant,'administrator','Administrador',1)");
        $s->execute(['tenant'=>$tenant]);$roleId=(int)$this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO talk_tenant_users(tenant_id,user_id,role,role_id,status,is_default) VALUES(:tenant,:user,'admin',:role_id,'active',1)")->execute(['tenant'=>$tenant,'user'=>$user,'role_id'=>$roleId]);
        $s=$this->pdo->prepare("INSERT INTO day_tasks(tenant_id,assigned_user_id,created_by,title,status,priority,due_at) VALUES(:tenant,:user,:creator,:title,'pending','normal',NOW())");
        $s->execute(['tenant'=>$tenant,'user'=>$user,'creator'=>$user,'title'=>'Task '.$suffix]);$task=(int)$this->pdo->lastInsertId();
        $s=$this->pdo->prepare("INSERT INTO day_events(tenant_id,assigned_user_id,created_by,title,starts_at,status) VALUES(:tenant,:user,:creator,:title,:starts_at,'scheduled')");
        $s->execute(['tenant'=>$tenant,'user'=>$user,'creator'=>$user,'title'=>'Event '.$suffix,'starts_at'=>(new DateTimeImmutable('now'))->format('Y-m-d H:i:s')]);$event=(int)$this->pdo->lastInsertId();
        return compact('tenant','user','task','event');
    }
}
