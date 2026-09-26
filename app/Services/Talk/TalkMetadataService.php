<?php

declare(strict_types=1);

namespace Moves\Services\Talk;

use Moves\Boot\Connection;
use PDO;

final class TalkMetadataService
{
    public function __construct(private ?int $tenantId = null) {}
    private function tenantId(): int { return $this->tenantId ??= (new TalkTenantContext())->currentTenantId(); }
    public function updatePriority(int $ticketId, int $userId, string $priority): void
    {
        if (!(new TalkService($this->tenantId()))->canOperateTicket($ticketId,$userId)) { throw new \RuntimeException('Você não pode alterar este atendimento.'); }
        if (!in_array($priority, ['low','normal','high','urgent'], true)) { throw new \RuntimeException('Prioridade inválida.'); }
        $pdo=Connection::getInstance();
        $statement=$pdo->prepare('UPDATE talk_tickets SET priority=:priority,updated_at=NOW() WHERE tenant_id=:tenant_id AND id=:id');
        $statement->execute(['tenant_id'=>$this->tenantId(),'priority'=>$priority,'id'=>$ticketId]);
        if($statement->rowCount()!==1){throw new \RuntimeException('Atendimento não encontrado.');}
        $this->event($ticketId,$userId,'ticket.priority_changed',['priority'=>$priority]);
        (new TalkNotificationService($this->tenantId()))->notifyTicketAssignee($ticketId,$userId,'priority_changed','Prioridade do atendimento alterada','A prioridade foi alterada para '.$this->priorityLabel($priority).'.',['priority'=>$priority]);
    }

    public function tags(): array
    {
        $s=Connection::getInstance()->prepare('SELECT id,name,slug FROM talk_tags WHERE tenant_id=:tenant_id ORDER BY name');$s->execute(['tenant_id'=>$this->tenantId()]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ticketTags(int $ticketId): array
    {
        $statement=Connection::getInstance()->prepare('SELECT tg.id,tg.name,tg.slug FROM talk_ticket_tags tt INNER JOIN talk_tags tg ON tg.tenant_id=tt.tenant_id AND tg.id=tt.tag_id WHERE tt.tenant_id=:tenant_id AND tt.ticket_id=:ticket_id ORDER BY tg.name');
        $statement->execute(['tenant_id'=>$this->tenantId(),'ticket_id'=>$ticketId]);return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createTag(string $name): int
    {
        $name=mb_substr(trim($name),0,80);if($name===''){throw new \RuntimeException('Informe o nome da tag.');}
        $slug=$this->slug($name);$pdo=Connection::getInstance();
        $existing=$pdo->prepare('SELECT id FROM talk_tags WHERE tenant_id=:tenant_id AND slug=:slug LIMIT 1');$existing->execute(['tenant_id'=>$this->tenantId(),'slug'=>$slug]);$id=(int)$existing->fetchColumn();if($id>0){return $id;}
        $insert=$pdo->prepare('INSERT INTO talk_tags(tenant_id,name,slug) VALUES(:tenant_id,:name,:slug)');$insert->execute(['tenant_id'=>$this->tenantId(),'name'=>$name,'slug'=>$slug]);return (int)$pdo->lastInsertId();
    }

    public function attachTag(int $ticketId,int $tagId,int $userId): void
    {
        if (!(new TalkService($this->tenantId()))->canOperateTicket($ticketId,$userId)) { throw new \RuntimeException('Você não pode alterar este atendimento.'); }
        if($this->tagName($tagId)===null){throw new \RuntimeException('Tag não encontrada.');}
        $pdo=Connection::getInstance();$pdo->prepare('INSERT IGNORE INTO talk_ticket_tags(tenant_id,ticket_id,tag_id) VALUES(:tenant_id,:ticket_id,:tag_id)')->execute(['tenant_id'=>$this->tenantId(),'ticket_id'=>$ticketId,'tag_id'=>$tagId]);
        $this->event($ticketId,$userId,'ticket.tag_added',['tag_id'=>$tagId]);
        $name=$this->tagName($tagId);(new TalkNotificationService($this->tenantId()))->notifyTicketAssignee($ticketId,$userId,'tag_added','Tag adicionada ao atendimento',$name!==null?'Tag: '.$name:null,['tag_id'=>$tagId]);
    }

    public function detachTag(int $ticketId,int $tagId,int $userId): void
    {
        if (!(new TalkService($this->tenantId()))->canOperateTicket($ticketId,$userId)) { throw new \RuntimeException('Você não pode alterar este atendimento.'); }
        $name=$this->tagName($tagId);$pdo=Connection::getInstance();$pdo->prepare('DELETE FROM talk_ticket_tags WHERE tenant_id=:tenant_id AND ticket_id=:ticket_id AND tag_id=:tag_id')->execute(['tenant_id'=>$this->tenantId(),'ticket_id'=>$ticketId,'tag_id'=>$tagId]);
        $this->event($ticketId,$userId,'ticket.tag_removed',['tag_id'=>$tagId]);
        (new TalkNotificationService($this->tenantId()))->notifyTicketAssignee($ticketId,$userId,'tag_removed','Tag removida do atendimento',$name!==null?'Tag: '.$name:null,['tag_id'=>$tagId]);
    }

    public function search(array $filters,?int $assignedUserId=null): array
    {
        $pdo=Connection::getInstance();$where=['t.tenant_id=:tenant_id'];$params=['tenant_id'=>$this->tenantId()];$term=mb_substr(trim((string)($filters['q']??'')),0,120);
        if($term!==''){$where[]='(t.protocol LIKE :term_protocol OR t.subject LIKE :term_subject OR c.name LIKE :term_name OR c.phone LIKE :term_phone)';$like='%'.$term.'%';$params['term_protocol']=$like;$params['term_subject']=$like;$params['term_name']=$like;$params['term_phone']=$like;}
        $status=(string)($filters['status']??'');if(in_array($status,['queued','assigned','open','closed'],true)){$where[]='t.status=:status';$params['status']=$status;}
        $priority=(string)($filters['priority']??'');if(in_array($priority,['low','normal','high','urgent'],true)){$where[]='t.priority=:priority';$params['priority']=$priority;}
        $queueId=max(0,(int)($filters['queue_id']??0));if($queueId>0){$where[]='t.queue_id=:queue_id';$params['queue_id']=$queueId;}
        $tagId=max(0,(int)($filters['tag_id']??0));if($tagId>0){$where[]='EXISTS(SELECT 1 FROM talk_ticket_tags ftt WHERE ftt.tenant_id=t.tenant_id AND ftt.ticket_id=t.id AND ftt.tag_id=:tag_id)';$params['tag_id']=$tagId;}
        if($assignedUserId!==null){$where[]='t.assigned_user_id=:assigned_user_id';$params['assigned_user_id']=$assignedUserId;}
        $sql="SELECT t.id,t.protocol,t.subject,t.priority,t.status,t.updated_at,t.sla_due_at,t.first_response_at,c.name contact_name,c.phone contact_phone,q.name queue_name,u.name assigned_name FROM talk_tickets t INNER JOIN talk_conversations cv ON cv.tenant_id=t.tenant_id AND cv.id=t.conversation_id INNER JOIN talk_contacts c ON c.tenant_id=t.tenant_id AND c.id=cv.contact_id LEFT JOIN talk_queues q ON q.tenant_id=t.tenant_id AND q.id=t.queue_id LEFT JOIN users u ON u.id=t.assigned_user_id WHERE ".implode(' AND ',$where)." ORDER BY FIELD(t.priority,'urgent','high','normal','low'),t.updated_at DESC,t.id DESC LIMIT 100";
        $statement=$pdo->prepare($sql);$statement->execute($params);return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function tagName(int $tagId): ?string
    {
        $s=Connection::getInstance()->prepare('SELECT name FROM talk_tags WHERE tenant_id=:tenant_id AND id=:id');$s->execute(['tenant_id'=>$this->tenantId(),'id'=>$tagId]);$value=$s->fetchColumn();return $value===false?null:(string)$value;
    }

    private function priorityLabel(string $priority): string
    {
        return ['low'=>'Baixa','normal'=>'Normal','high'=>'Alta','urgent'=>'Urgente'][$priority]??$priority;
    }

    private function event(int $ticketId,int $userId,string $type,array $payload): void
    {
        Connection::getInstance()->prepare("INSERT INTO talk_events(tenant_id,ticket_id,user_id,actor_type,event_type,payload) VALUES(:tenant_id,:ticket_id,:user_id,'user',:event_type,:payload)")->execute(['tenant_id'=>$this->tenantId(),'ticket_id'=>$ticketId,'user_id'=>$userId,'event_type'=>$type,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR)]);
    }

    private function slug(string $value): string
    {
        $value=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value)?:$value;$value=strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/','-',$value),'-'));return $value!==''?$value:'tag';
    }
}
