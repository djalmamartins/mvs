<?php

declare(strict_types=1);

namespace Moves\Services\Talk;

use Moves\Boot\Connection;
use PDO;

final class TalkMetadataService
{
    public function updatePriority(int $ticketId, int $userId, string $priority): void
    {
        if (!(new TalkService())->canOperateTicket($ticketId,$userId)) { throw new \RuntimeException('Você não pode alterar este atendimento.'); }
        if (!in_array($priority, ['low','normal','high','urgent'], true)) { throw new \RuntimeException('Prioridade inválida.'); }
        $pdo=Connection::getInstance();
        $statement=$pdo->prepare('UPDATE talk_tickets SET priority=:priority,updated_at=NOW() WHERE id=:id');
        $statement->execute(['priority'=>$priority,'id'=>$ticketId]);
        if($statement->rowCount()!==1){throw new \RuntimeException('Atendimento não encontrado.');}
        $this->event($ticketId,$userId,'ticket.priority_changed',['priority'=>$priority]);
        (new TalkNotificationService())->notifyTicketAssignee($ticketId,$userId,'priority_changed','Prioridade do atendimento alterada','A prioridade foi alterada para '.$this->priorityLabel($priority).'.',['priority'=>$priority]);
    }

    public function tags(): array
    {
        return Connection::getInstance()->query('SELECT id,name,slug FROM talk_tags ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ticketTags(int $ticketId): array
    {
        $statement=Connection::getInstance()->prepare('SELECT tg.id,tg.name,tg.slug FROM talk_ticket_tags tt INNER JOIN talk_tags tg ON tg.id=tt.tag_id WHERE tt.ticket_id=:ticket_id ORDER BY tg.name');
        $statement->execute(['ticket_id'=>$ticketId]);return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createTag(string $name): int
    {
        $name=mb_substr(trim($name),0,80);if($name===''){throw new \RuntimeException('Informe o nome da tag.');}
        $slug=$this->slug($name);$pdo=Connection::getInstance();
        $existing=$pdo->prepare('SELECT id FROM talk_tags WHERE slug=:slug LIMIT 1');$existing->execute(['slug'=>$slug]);$id=(int)$existing->fetchColumn();if($id>0){return $id;}
        $insert=$pdo->prepare('INSERT INTO talk_tags(name,slug) VALUES(:name,:slug)');$insert->execute(['name'=>$name,'slug'=>$slug]);return (int)$pdo->lastInsertId();
    }

    public function attachTag(int $ticketId,int $tagId,int $userId): void
    {
        if (!(new TalkService())->canOperateTicket($ticketId,$userId)) { throw new \RuntimeException('Você não pode alterar este atendimento.'); }
        $pdo=Connection::getInstance();$pdo->prepare('INSERT IGNORE INTO talk_ticket_tags(ticket_id,tag_id) VALUES(:ticket_id,:tag_id)')->execute(['ticket_id'=>$ticketId,'tag_id'=>$tagId]);
        $this->event($ticketId,$userId,'ticket.tag_added',['tag_id'=>$tagId]);
        $name=$this->tagName($tagId);(new TalkNotificationService())->notifyTicketAssignee($ticketId,$userId,'tag_added','Tag adicionada ao atendimento',$name!==null?'Tag: '.$name:null,['tag_id'=>$tagId]);
    }

    public function detachTag(int $ticketId,int $tagId,int $userId): void
    {
        if (!(new TalkService())->canOperateTicket($ticketId,$userId)) { throw new \RuntimeException('Você não pode alterar este atendimento.'); }
        $name=$this->tagName($tagId);$pdo=Connection::getInstance();$pdo->prepare('DELETE FROM talk_ticket_tags WHERE ticket_id=:ticket_id AND tag_id=:tag_id')->execute(['ticket_id'=>$ticketId,'tag_id'=>$tagId]);
        $this->event($ticketId,$userId,'ticket.tag_removed',['tag_id'=>$tagId]);
        (new TalkNotificationService())->notifyTicketAssignee($ticketId,$userId,'tag_removed','Tag removida do atendimento',$name!==null?'Tag: '.$name:null,['tag_id'=>$tagId]);
    }

    public function search(array $filters,?int $assignedUserId=null): array
    {
        $pdo=Connection::getInstance();$where=['1=1'];$params=[];$term=mb_substr(trim((string)($filters['q']??'')),0,120);
        if($term!==''){$where[]='(t.protocol LIKE :term OR t.subject LIKE :term OR c.name LIKE :term OR c.phone LIKE :term)';$params['term']='%'.$term.'%';}
        $status=(string)($filters['status']??'');if(in_array($status,['queued','assigned','open','closed'],true)){$where[]='t.status=:status';$params['status']=$status;}
        $priority=(string)($filters['priority']??'');if(in_array($priority,['low','normal','high','urgent'],true)){$where[]='t.priority=:priority';$params['priority']=$priority;}
        $queueId=max(0,(int)($filters['queue_id']??0));if($queueId>0){$where[]='t.queue_id=:queue_id';$params['queue_id']=$queueId;}
        $tagId=max(0,(int)($filters['tag_id']??0));if($tagId>0){$where[]='EXISTS(SELECT 1 FROM talk_ticket_tags ftt WHERE ftt.ticket_id=t.id AND ftt.tag_id=:tag_id)';$params['tag_id']=$tagId;}
        if($assignedUserId!==null){$where[]='t.assigned_user_id=:assigned_user_id';$params['assigned_user_id']=$assignedUserId;}
        $sql="SELECT t.id,t.protocol,t.subject,t.priority,t.status,t.updated_at,t.sla_due_at,t.first_response_at,c.name contact_name,c.phone contact_phone,q.name queue_name,u.name assigned_name FROM talk_tickets t INNER JOIN talk_conversations cv ON cv.id=t.conversation_id INNER JOIN talk_contacts c ON c.id=cv.contact_id LEFT JOIN talk_queues q ON q.id=t.queue_id LEFT JOIN users u ON u.id=t.assigned_user_id WHERE ".implode(' AND ',$where)." ORDER BY FIELD(t.priority,'urgent','high','normal','low'),t.updated_at DESC,t.id DESC LIMIT 100";
        $statement=$pdo->prepare($sql);$statement->execute($params);return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function tagName(int $tagId): ?string
    {
        $s=Connection::getInstance()->prepare('SELECT name FROM talk_tags WHERE id=:id');$s->execute(['id'=>$tagId]);$value=$s->fetchColumn();return $value===false?null:(string)$value;
    }

    private function priorityLabel(string $priority): string
    {
        return ['low'=>'Baixa','normal'=>'Normal','high'=>'Alta','urgent'=>'Urgente'][$priority]??$priority;
    }

    private function event(int $ticketId,int $userId,string $type,array $payload): void
    {
        Connection::getInstance()->prepare("INSERT INTO talk_events(ticket_id,user_id,actor_type,event_type,payload) VALUES(:ticket_id,:user_id,'user',:event_type,:payload)")->execute(['ticket_id'=>$ticketId,'user_id'=>$userId,'event_type'=>$type,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR)]);
    }

    private function slug(string $value): string
    {
        $value=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value)?:$value;$value=strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/','-',$value),'-'));return $value!==''?$value:'tag';
    }
}
