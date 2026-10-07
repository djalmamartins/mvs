<?php
declare(strict_types=1);
namespace Moves\Services\Day;
use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Services\Platform\TenantContext;
use PDO;
use RuntimeException;

final class DayService
{
    private int $tenantId;
    public function __construct(?int $tenantId=null)
    {
        if($tenantId!==null){$this->tenantId=$tenantId;return;}
        $user=Auth::user();
        if($user===null)throw new RuntimeException('Contexto de administradora indisponível.');
        $this->tenantId=(new TenantContext())->currentId((int)$user->id);
    }

    public static function safeSourceUrl(?string $url): string
    {
        $url=trim((string)$url);
        if (
            $url==='' ||
            !str_starts_with($url,'/') ||
            str_starts_with($url,'//') ||
            str_contains($url,'\\') ||
            preg_match('/[\\x00-\\x1F\\x7F]/',$url)===1
        ) {
            return '/day';
        }
        return $url;
    }

    /** @return array<string,mixed> */
    public function dashboard(int $userId): array
    {
        $tasks=$this->tasks($userId);
        $talk=$this->talk($userId);
        $events=$this->events($userId);
        $timeline=[];
        foreach($tasks as $task){if($task['status']==='done')continue;$timeline[]=['type'=>'task','at'=>$task['due_at']??$task['created_at'],'title'=>$task['title'],'meta'=>$task['priority'],'url'=>self::safeSourceUrl($task['source_url']??null)];}
        foreach($events as $event){$timeline[]=['type'=>'event','at'=>$event['starts_at'],'title'=>$event['title'],'meta'=>$event['source_type'],'url'=>self::safeSourceUrl($event['source_url']??null)];}
        foreach($talk as $ticket){$timeline[]=['type'=>'talk','at'=>$ticket['updated_at'],'title'=>'Talk · '.$ticket['protocol'],'meta'=>$ticket['contact_name']?:'Contato','url'=>'/talk/view/inbox?ticket='.(int)$ticket['id']];}
        usort($timeline,static fn(array $a,array $b):int=>strcmp((string)$a['at'],(string)$b['at']));$timeline=array_slice($timeline,0,30);
        $today=date('Y-m-d');
        $dueToday=array_values(array_filter($tasks,static fn(array $t):bool=>!empty($t['due_at'])&&str_starts_with((string)$t['due_at'],$today)&&$t['status']!=='done'));
        $overdue=array_values(array_filter($tasks,static fn(array $t):bool=>!empty($t['due_at'])&&(string)$t['due_at']<date('Y-m-d H:i:s')&&$t['status']!=='done'));
        return ['tasks'=>$tasks,'talk'=>$talk,'events'=>$events,'timeline'=>$timeline,'summary'=>['pending'=>count(array_filter($tasks,static fn(array $t):bool=>$t['status']!=='done')),'today'=>count($dueToday)+count($events),'overdue'=>count($overdue),'talk'=>count($talk)]];
    }

    /** @return array<int,array<string,mixed>> */
    public function tasks(int $userId): array
    {
        $s=Connection::getInstance()->prepare("SELECT id,title,description,status,priority,due_at,source_type,source_id,source_url,automation_key,created_at,updated_at FROM day_tasks WHERE tenant_id=:tenant AND assigned_user_id=:user AND (status<>'done' OR automation_key IS NULL) ORDER BY CASE WHEN status<>'done' AND due_at IS NOT NULL AND due_at<NOW() THEN 0 ELSE 1 END,CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END,CASE status WHEN 'done' THEN 1 ELSE 0 END,due_at IS NULL,due_at,id");
        $s->execute(['tenant'=>$this->tenantId,'user'=>$userId]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function events(int $userId): array
    {
        $start=(new \DateTimeImmutable('today'))->format('Y-m-d H:i:s');
        $end=(new \DateTimeImmutable('tomorrow'))->format('Y-m-d H:i:s');
        $s=Connection::getInstance()->prepare("SELECT id,title,description,starts_at,ends_at,status,source_type,source_id,source_url FROM day_events WHERE tenant_id=:tenant AND assigned_user_id=:user AND status='scheduled' AND starts_at>=:start AND starts_at<:end ORDER BY starts_at,id");
        $s->execute(['tenant'=>$this->tenantId,'user'=>$userId,'start'=>$start,'end'=>$end]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function talk(int $userId): array
    {
        $s=Connection::getInstance()->prepare("SELECT t.id,t.protocol,t.status,t.priority,t.updated_at,c.name contact_name,cv.last_message_at FROM talk_tickets t INNER JOIN talk_conversations cv ON cv.id=t.conversation_id AND cv.tenant_id=t.tenant_id INNER JOIN talk_contacts c ON c.id=cv.contact_id AND c.tenant_id=t.tenant_id WHERE t.tenant_id=:tenant AND t.assigned_user_id=:user AND t.status IN ('assigned','open') ORDER BY COALESCE(cv.last_message_at,t.updated_at) DESC,t.id DESC LIMIT 20");
        $s->execute(['tenant'=>$this->tenantId,'user'=>$userId]);
        $tickets=$s->fetchAll(PDO::FETCH_ASSOC);
        $seen=array_fill_keys(array_map(static fn(array $ticket):int=>(int)$ticket['id'],$tickets),true);
        $n=Connection::getInstance()->prepare("SELECT n.ticket_id id,t.protocol,t.status,t.priority,n.created_at updated_at,c.name contact_name,cv.last_message_at FROM talk_notifications n INNER JOIN talk_tickets t ON t.id=n.ticket_id AND t.tenant_id=n.tenant_id INNER JOIN talk_conversations cv ON cv.id=t.conversation_id AND cv.tenant_id=t.tenant_id INNER JOIN talk_contacts c ON c.id=cv.contact_id AND c.tenant_id=t.tenant_id WHERE n.tenant_id=:tenant AND n.recipient_id=:user AND n.read_at IS NULL AND n.ticket_id IS NOT NULL AND t.status<>'closed' ORDER BY n.created_at DESC,n.id DESC LIMIT 20");
        $n->execute(['tenant'=>$this->tenantId,'user'=>$userId]);
        foreach($n->fetchAll(PDO::FETCH_ASSOC) as $ticket){
            $id=(int)$ticket['id'];
            if(isset($seen[$id]))continue;
            $tickets[]=$ticket;
            $seen[$id]=true;
        }
        usort($tickets,static fn(array $a,array $b):int=>strcmp((string)$b['updated_at'],(string)$a['updated_at']));
        return array_slice($tickets,0,20);
    }

    public function setTaskStatus(int $taskId,int $userId,string $status): bool
    {
        if(!in_array($status,['pending','in_progress','done'],true))throw new RuntimeException('Status de tarefa inválido.');
        $pdo=Connection::getInstance();
        $find=$pdo->prepare('SELECT status,automation_key FROM day_tasks WHERE id=:id AND tenant_id=:tenant AND assigned_user_id=:user');
        $find->execute(['id'=>$taskId,'tenant'=>$this->tenantId,'user'=>$userId]);$task=$find->fetch(PDO::FETCH_ASSOC);
        if(!is_array($task))return false;
        if(is_string($task['automation_key']??null)&&$task['automation_key']!==''&&$status==='done')return false;
        $s=$pdo->prepare("UPDATE day_tasks SET status=:status,completed_at=IF(:completed='done',NOW(),NULL) WHERE id=:id AND tenant_id=:tenant AND assigned_user_id=:user");
        $s->execute(['status'=>$status,'completed'=>$status,'id'=>$taskId,'tenant'=>$this->tenantId,'user'=>$userId]);
        if($s->rowCount()===1&&is_string($task['automation_key']??null)&&$task['automation_key']!==''){
            (new \Moves\Services\Platform\PlatformAudit($pdo))->record($this->tenantId,$userId,'erp.pending.status_changed','day_task',$taskId,['from'=>$task['status'],'to'=>$status]);
        }
        return $s->rowCount()===1;
    }
}
