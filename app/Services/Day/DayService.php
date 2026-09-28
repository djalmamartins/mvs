<?php
declare(strict_types=1);
namespace Moves\Services\Day;
use Moves\Boot\Connection;
use Moves\Services\Talk\TalkTenantContext;
use PDO;
use RuntimeException;

final class DayService
{
    private int $tenantId;
    public function __construct(?int $tenantId=null){$this->tenantId=$tenantId??(new TalkTenantContext())->currentTenantId();}

    public static function safeSourceUrl(?string $url): string
    {
        $url=trim((string)$url);
        if($url===''||!str_starts_with($url,'/')||str_starts_with($url,'//')||preg_match('/[\\r\\n]/',$url)===1)return '/day';
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
        $s=Connection::getInstance()->prepare("SELECT id,title,description,status,priority,due_at,source_type,source_id,source_url,created_at,updated_at FROM day_tasks WHERE tenant_id=:tenant AND assigned_user_id=:user ORDER BY CASE status WHEN 'done' THEN 1 ELSE 0 END,CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END,due_at IS NULL,due_at,id");
        $s->execute(['tenant'=>$this->tenantId,'user'=>$userId]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function events(int $userId): array
    {
        $s=Connection::getInstance()->prepare("SELECT id,title,description,starts_at,ends_at,status,source_type,source_id,source_url FROM day_events WHERE tenant_id=:tenant AND assigned_user_id=:user AND status='scheduled' AND starts_at>=CURDATE() AND starts_at<DATE_ADD(CURDATE(),INTERVAL 1 DAY) ORDER BY starts_at,id");
        $s->execute(['tenant'=>$this->tenantId,'user'=>$userId]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function talk(int $userId): array
    {
        $s=Connection::getInstance()->prepare("SELECT t.id,t.protocol,t.status,t.priority,t.updated_at,c.name contact_name,cv.last_message_at FROM talk_tickets t INNER JOIN talk_conversations cv ON cv.id=t.conversation_id AND cv.tenant_id=t.tenant_id INNER JOIN talk_contacts c ON c.id=cv.contact_id AND c.tenant_id=t.tenant_id WHERE t.tenant_id=:tenant AND t.assigned_user_id=:user AND t.status IN ('assigned','open') ORDER BY COALESCE(cv.last_message_at,t.updated_at) DESC,t.id DESC LIMIT 20");
        $s->execute(['tenant'=>$this->tenantId,'user'=>$userId]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setTaskStatus(int $taskId,int $userId,string $status): bool
    {
        if(!in_array($status,['pending','in_progress','done'],true))throw new RuntimeException('Status de tarefa inválido.');
        $s=Connection::getInstance()->prepare("UPDATE day_tasks SET status=:status,completed_at=IF(:done='done',NOW(),NULL) WHERE id=:id AND tenant_id=:tenant AND assigned_user_id=:user");
        $s->execute(['status'=>$status,'done'=>$status,'id'=>$taskId,'tenant'=>$this->tenantId,'user'=>$userId]);return $s->rowCount()===1;
    }
}