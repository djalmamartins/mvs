<?php

declare(strict_types=1);

namespace Moves\Services\Talk;

use Moves\Boot\Connection;
use PDO;

final class TalkService
{
    public function dashboard(): array
    {
        $pdo = Connection::getInstance();
        $counts = [
            'queued' => $this->count($pdo, "SELECT COUNT(*) FROM talk_tickets WHERE status = 'queued'"),
            'active' => $this->count($pdo, "SELECT COUNT(*) FROM talk_tickets WHERE status IN ('assigned','open')"),
            'closed_today' => $this->count($pdo, "SELECT COUNT(*) FROM talk_tickets WHERE status = 'closed' AND DATE(closed_at) = CURRENT_DATE"),
            'contacts' => $this->count($pdo, 'SELECT COUNT(*) FROM talk_contacts'),
        ];
        return ['counts' => $counts, 'queue' => $this->queue()];
    }

    public function queue(): array
    {
        $statement = Connection::getInstance()->query(
            "SELECT t.id, t.protocol, t.subject, t.priority, t.status, t.queued_at, t.created_at,
                    c.name AS contact_name, c.phone AS contact_phone,
                    q.name AS queue_name, u.name AS assigned_name,
                    cv.channel
               FROM talk_tickets t
               INNER JOIN talk_conversations cv ON cv.id = t.conversation_id
               INNER JOIN talk_contacts c ON c.id = cv.contact_id
               LEFT JOIN talk_queues q ON q.id = t.queue_id
               LEFT JOIN users u ON u.id = t.assigned_user_id
              WHERE t.status = 'queued'
              ORDER BY FIELD(t.priority, 'urgent','high','normal','low'), COALESCE(t.queued_at,t.created_at) ASC, t.id ASC
              LIMIT 100"
        );
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function heartbeat(int $userId): void
    {
        Connection::getInstance()->prepare("INSERT INTO talk_presence(user_id,status,last_seen_at) VALUES(:user_id,'online',NOW()) ON DUPLICATE KEY UPDATE status='online',last_seen_at=NOW()")->execute(['user_id'=>$userId]);
    }

    public function permissions(int $userId): array
    {
        $s=Connection::getInstance()->prepare("SELECT COALESCE(tus.talk_role,IF(u.role='admin','admin','agent')) talk_role,COALESCE(tus.max_active_tickets,5) max_active_tickets FROM users u LEFT JOIN talk_user_settings tus ON tus.user_id=u.id WHERE u.id=:id");
        $s->execute(['id'=>$userId]); return $s->fetch(PDO::FETCH_ASSOC) ?: ['talk_role'=>'agent','max_active_tickets'=>5];
    }

    public function claim(int $ticketId, int $userId): bool
    {
        $pdo = Connection::getInstance();
        $pdo->beginTransaction();
        try {
            $permissions=$this->permissions($userId);
            $active=$pdo->prepare("SELECT COUNT(*) FROM talk_tickets WHERE assigned_user_id=:user_id AND status IN ('assigned','open')");
            $active->execute(['user_id'=>$userId]);
            if((int)$active->fetchColumn()>=(int)$permissions['max_active_tickets']){$pdo->rollBack();return false;}
            $statement = $pdo->prepare("SELECT id, queue_id, status FROM talk_tickets WHERE id = :id FOR UPDATE");
            $statement->execute(['id' => $ticketId]);
            $ticket = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$ticket || $ticket['status'] !== 'queued') {
                $pdo->rollBack();
                return false;
            }

            if ($ticket['queue_id'] !== null) {
                $eligible = $pdo->prepare(
                    "SELECT COUNT(*) FROM talk_queue_members
                     WHERE queue_id = :queue_id AND user_id = :user_id AND status = 'active'"
                );
                $eligible->execute(['queue_id' => $ticket['queue_id'], 'user_id' => $userId]);
                if ((int) $eligible->fetchColumn() === 0) {
                    $pdo->rollBack();
                    return false;
                }
            }

            $update = $pdo->prepare(
                "UPDATE talk_tickets SET assigned_user_id = :user_id, status = 'assigned',
                        assigned_at = NOW(), last_activity_at=NOW(), updated_at = NOW()
                 WHERE id = :id AND status = 'queued'"
            );
            $update->execute(['user_id' => $userId, 'id' => $ticketId]);
            if ($update->rowCount() !== 1) {
                $pdo->rollBack();
                return false;
            }

            $event = $pdo->prepare(
                "INSERT INTO talk_events (ticket_id, user_id, actor_type, event_type, payload)
                 VALUES (:ticket_id, :user_id, 'user', 'ticket.claimed', :payload)"
            );
            $event->execute([
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'payload' => json_encode(['assigned_user_id' => $userId], JSON_THROW_ON_ERROR),
            ]);
            $pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function seedSimulation(int $actorId): int
    {
        $pdo = Connection::getInstance();
        $pdo->beginTransaction();
        try {
            $pdo->exec("INSERT IGNORE INTO talk_departments (name,slug,status) VALUES ('Atendimento','atendimento','active')");
            $departmentId = (int) $pdo->query("SELECT id FROM talk_departments WHERE slug='atendimento'")->fetchColumn();
            $queue = $pdo->prepare("INSERT IGNORE INTO talk_queues (department_id,name,slug,status,auto_assign_after_seconds) VALUES (:department_id,'Atendimento geral','atendimento-geral','active',30)");
            $queue->execute(['department_id' => $departmentId]);
            $queueId = (int) $pdo->query("SELECT id FROM talk_queues WHERE slug='atendimento-geral'")->fetchColumn();
            $member = $pdo->prepare("INSERT INTO talk_queue_members(queue_id,user_id,role,capacity,status) VALUES(:queue_id,:user_id,'agent',5,'active') ON DUPLICATE KEY UPDATE status='active'");
            $member->execute(['queue_id' => $queueId, 'user_id' => $actorId]);

            $external = 'sim:' . bin2hex(random_bytes(6));
            $contact = $pdo->prepare("INSERT INTO talk_contacts(name,phone,external_id,channel,metadata) VALUES('Cliente de simulação','SIM-0001',:external_id,'simulation',:metadata)");
            $contact->execute(['external_id' => $external, 'metadata' => json_encode(['simulation' => true], JSON_THROW_ON_ERROR)]);
            $contactId = (int) $pdo->lastInsertId();

            $conversationExternal = $external . ':conversation';
            $conversation = $pdo->prepare("INSERT INTO talk_conversations(contact_id,channel,external_id,status,last_message_at) VALUES(:contact_id,'simulation',:external_id,'open',NOW())");
            $conversation->execute(['contact_id' => $contactId, 'external_id' => $conversationExternal]);
            $conversationId = (int) $pdo->lastInsertId();

            $protocol = 'SIM-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $ticket = $pdo->prepare("INSERT INTO talk_tickets(protocol,conversation_id,queue_id,status,priority,subject,source,queued_at) VALUES(:protocol,:conversation_id,:queue_id,'queued','normal','Atendimento de simulação','simulation',NOW())");
            $ticket->execute(['protocol' => $protocol, 'conversation_id' => $conversationId, 'queue_id' => $queueId]);
            $ticketId = (int) $pdo->lastInsertId();

            $message = $pdo->prepare("INSERT INTO talk_messages(conversation_id,ticket_id,sender_type,direction,type,body,sent_at,metadata) VALUES(:conversation_id,:ticket_id,'contact','inbound','text','Olá, preciso de ajuda com meu atendimento.',NOW(),:metadata)");
            $message->execute(['conversation_id' => $conversationId, 'ticket_id' => $ticketId, 'metadata' => json_encode(['simulation' => true], JSON_THROW_ON_ERROR)]);

            $event = $pdo->prepare("INSERT INTO talk_events(ticket_id,user_id,actor_type,event_type,payload) VALUES(:ticket_id,:user_id,'system','ticket.created',:payload)");
            $event->execute(['ticket_id' => $ticketId, 'user_id' => $actorId, 'payload' => json_encode(['source' => 'simulation'], JSON_THROW_ON_ERROR)]);
            $pdo->commit();
            return $ticketId;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    public function ticket(int $ticketId): ?array
    {
        $pdo = Connection::getInstance();
        $statement = $pdo->prepare(
            "SELECT t.*, c.name AS contact_name, c.phone AS contact_phone, cv.channel, q.name AS queue_name, u.name AS assigned_name
               FROM talk_tickets t
               INNER JOIN talk_conversations cv ON cv.id=t.conversation_id
               INNER JOIN talk_contacts c ON c.id=cv.contact_id
               LEFT JOIN talk_queues q ON q.id=t.queue_id
               LEFT JOIN users u ON u.id=t.assigned_user_id
              WHERE t.id=:id LIMIT 1"
        );
        $statement->execute(['id' => $ticketId]);
        $ticket = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) { return null; }
        $messages = $pdo->prepare("SELECT m.*, u.name AS sender_name FROM talk_messages m LEFT JOIN users u ON u.id=m.sender_user_id WHERE m.ticket_id=:id ORDER BY m.sent_at ASC,m.id ASC");
        $messages->execute(['id' => $ticketId]);
        $events = $pdo->prepare("SELECT e.*, u.name AS user_name FROM talk_events e LEFT JOIN users u ON u.id=e.user_id WHERE e.ticket_id=:id ORDER BY e.created_at DESC,e.id DESC LIMIT 50");
        $events->execute(['id' => $ticketId]);
        $ticket['messages'] = $messages->fetchAll(PDO::FETCH_ASSOC);
        $ticket['events'] = $events->fetchAll(PDO::FETCH_ASSOC);
        $ticket['notes'] = $this->notes($ticketId);
        $ticket['queues'] = $this->queues();
        $ticket['eligible_users'] = $this->eligibleUsers($ticket['queue_id'] !== null ? (int)$ticket['queue_id'] : null);
        return $ticket;
    }

    public function sendSimulationMessage(int $ticketId, int $userId, string $body): void
    {
        $body = trim($body);
        if ($body === '') { return; }
        $ticket = $this->ticket($ticketId);
        if ($ticket === null || $ticket['source'] !== 'simulation' || (int)($ticket['assigned_user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException('Atendimento de simulação indisponível para este usuário.');
        }
        $pdo=Connection::getInstance();
        $pdo->prepare("INSERT INTO talk_messages(conversation_id,ticket_id,sender_type,sender_user_id,direction,type,body,sent_at,metadata) VALUES(:conversation_id,:ticket_id,'user',:user_id,'outbound','text',:body,NOW(),:metadata)")
            ->execute(['conversation_id'=>$ticket['conversation_id'],'ticket_id'=>$ticketId,'user_id'=>$userId,'body'=>$body,'metadata'=>json_encode(['simulation'=>true], JSON_THROW_ON_ERROR)]);
        $pdo->prepare("UPDATE talk_tickets SET first_response_at=COALESCE(first_response_at,NOW()),last_activity_at=NOW(),updated_at=NOW() WHERE id=:id")->execute(['id'=>$ticketId]);
        $pdo->prepare("UPDATE talk_conversations SET last_message_at=NOW(),updated_at=NOW() WHERE id=:id")->execute(['id'=>$ticket['conversation_id']]);
    }

    public function myTickets(int $userId): array
    {
        $s = Connection::getInstance()->prepare("SELECT t.id,t.protocol,t.subject,t.priority,t.status,t.updated_at,c.name contact_name,c.phone contact_phone,q.name queue_name FROM talk_tickets t INNER JOIN talk_conversations cv ON cv.id=t.conversation_id INNER JOIN talk_contacts c ON c.id=cv.contact_id LEFT JOIN talk_queues q ON q.id=t.queue_id WHERE t.assigned_user_id=:user_id AND t.status IN ('assigned','open') ORDER BY t.updated_at DESC LIMIT 100");
        $s->execute(['user_id'=>$userId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function history(): array
    {
        return Connection::getInstance()->query("SELECT t.id,t.protocol,t.subject,t.priority,t.status,t.closed_at,t.updated_at,c.name contact_name,q.name queue_name,u.name assigned_name FROM talk_tickets t INNER JOIN talk_conversations cv ON cv.id=t.conversation_id INNER JOIN talk_contacts c ON c.id=cv.contact_id LEFT JOIN talk_queues q ON q.id=t.queue_id LEFT JOIN users u ON u.id=t.assigned_user_id WHERE t.status='closed' ORDER BY t.closed_at DESC,t.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function transfers(): array
    {
        return Connection::getInstance()->query("SELECT tr.*,t.protocol,fu.name from_user,tu.name to_user,fq.name from_queue,tq.name to_queue,cu.name created_by_name FROM talk_transfers tr INNER JOIN talk_tickets t ON t.id=tr.ticket_id LEFT JOIN users fu ON fu.id=tr.from_user_id LEFT JOIN users tu ON tu.id=tr.to_user_id LEFT JOIN talk_queues fq ON fq.id=tr.from_queue_id LEFT JOIN talk_queues tq ON tq.id=tr.to_queue_id LEFT JOIN users cu ON cu.id=tr.created_by ORDER BY tr.created_at DESC,tr.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function queues(): array
    {
        return Connection::getInstance()->query("SELECT q.id,q.name,q.slug,q.status,q.auto_assign_after_seconds,d.name department_name,COUNT(qm.user_id) members FROM talk_queues q LEFT JOIN talk_departments d ON d.id=q.department_id LEFT JOIN talk_queue_members qm ON qm.queue_id=q.id AND qm.status='active' GROUP BY q.id,d.name ORDER BY q.name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function eligibleUsers(?int $queueId = null): array
    {
        if ($queueId === null) {
            return Connection::getInstance()->query("SELECT id,name,email,role,status FROM users WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        }
        $s=Connection::getInstance()->prepare("SELECT u.id,u.name,u.email,qm.role,qm.capacity FROM talk_queue_members qm INNER JOIN users u ON u.id=qm.user_id WHERE qm.queue_id=:queue_id AND qm.status='active' AND u.status='active' ORDER BY u.name");
        $s->execute(['queue_id'=>$queueId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addNote(int $ticketId,int $userId,string $body): void
    {
        $body=trim($body); if($body===''){return;}
        $pdo=Connection::getInstance();
        $pdo->prepare("INSERT INTO talk_notes(ticket_id,user_id,body) VALUES(:ticket_id,:user_id,:body)")->execute(['ticket_id'=>$ticketId,'user_id'=>$userId,'body'=>$body]);
        $this->event($ticketId,$userId,'ticket.note_added',['body'=>$body]);
    }

    public function notes(int $ticketId): array
    {
        $s=Connection::getInstance()->prepare("SELECT n.*,u.name user_name FROM talk_notes n LEFT JOIN users u ON u.id=n.user_id WHERE n.ticket_id=:id ORDER BY n.created_at DESC,n.id DESC");
        $s->execute(['id'=>$ticketId]); return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function returnToQueue(int $ticketId,int $userId): void
    {
        $pdo=Connection::getInstance();
        $s=$pdo->prepare("UPDATE talk_tickets SET assigned_user_id=NULL,status='queued',queued_at=NOW(),assigned_at=NULL,updated_at=NOW() WHERE id=:id AND status IN ('assigned','open')");
        $s->execute(['id'=>$ticketId]);
        if($s->rowCount()!==1){throw new \RuntimeException('Atendimento não pode retornar para a fila.');}
        $this->event($ticketId,$userId,'ticket.returned_to_queue',[]);
    }

    public function close(int $ticketId,int $userId): void
    {
        $pdo=Connection::getInstance();
        $s=$pdo->prepare("UPDATE talk_tickets SET status='closed',closed_at=NOW(),closed_by=:user_id,updated_at=NOW() WHERE id=:id AND status<>'closed'");
        $s->execute(['user_id'=>$userId,'id'=>$ticketId]);
        if($s->rowCount()!==1){throw new \RuntimeException('Atendimento já finalizado ou indisponível.');}
        $this->event($ticketId,$userId,'ticket.closed',[]);
    }

    public function reopen(int $ticketId,int $userId): void
    {
        $pdo=Connection::getInstance();
        $s=$pdo->prepare("UPDATE talk_tickets SET status='queued',assigned_user_id=NULL,queued_at=NOW(),assigned_at=NULL,closed_at=NULL,closed_by=NULL,updated_at=NOW() WHERE id=:id AND status='closed'");
        $s->execute(['id'=>$ticketId]);
        if($s->rowCount()!==1){throw new \RuntimeException('Atendimento não pode ser reaberto.');}
        $this->event($ticketId,$userId,'ticket.reopened',[]);
    }

    public function transfer(int $ticketId,int $userId,?int $toUserId,?int $toQueueId,string $reason): void
    {
        if($toUserId===null && $toQueueId===null){throw new \RuntimeException('Selecione um atendente ou uma fila.');}
        $pdo=Connection::getInstance(); $pdo->beginTransaction();
        try{
            $s=$pdo->prepare("SELECT assigned_user_id,queue_id,status FROM talk_tickets WHERE id=:id FOR UPDATE"); $s->execute(['id'=>$ticketId]); $t=$s->fetch(PDO::FETCH_ASSOC);
            if(!$t || !in_array($t['status'],['assigned','open'],true)){throw new \RuntimeException('Atendimento indisponível para transferência.');}
            if($toUserId!==null){
                $eligible=$pdo->prepare("SELECT COUNT(*) FROM talk_queue_members WHERE queue_id=:queue_id AND user_id=:user_id AND status='active'");
                $targetQueue=$toQueueId ?? (int)$t['queue_id'];
                $eligible->execute(['queue_id'=>$targetQueue,'user_id'=>$toUserId]);
                if((int)$eligible->fetchColumn()===0){throw new \RuntimeException('Atendente não pertence à fila selecionada.');}
            }
            $newQueue=$toQueueId ?? (int)$t['queue_id'];
            $newStatus=$toUserId!==null?'assigned':'queued';
            $u=$pdo->prepare("UPDATE talk_tickets SET queue_id=:queue_id,assigned_user_id=:assigned,status=:status,queued_at=IF(:status='queued',NOW(),queued_at),assigned_at=IF(:status='assigned',NOW(),NULL),updated_at=NOW() WHERE id=:id");
            $u->execute(['queue_id'=>$newQueue,'assigned'=>$toUserId,'status'=>$newStatus,'id'=>$ticketId]);
            $tr=$pdo->prepare("INSERT INTO talk_transfers(ticket_id,from_user_id,from_queue_id,to_user_id,to_queue_id,reason,status,created_by) VALUES(:ticket_id,:from_user,:from_queue,:to_user,:to_queue,:reason,'completed',:created_by)");
            $tr->execute(['ticket_id'=>$ticketId,'from_user'=>$t['assigned_user_id'],'from_queue'=>$t['queue_id'],'to_user'=>$toUserId,'to_queue'=>$newQueue,'reason'=>trim($reason),'created_by'=>$userId]);
            $pdo->commit(); $this->event($ticketId,$userId,'ticket.transferred',['to_user_id'=>$toUserId,'to_queue_id'=>$newQueue]);
        }catch(\Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}throw $e;}
    }

    public function autoAssign(): int
    {
        $settings=$this->settings();
        if(($settings['auto_assign.enabled']??'1')!=='1'){return 0;}
        $pdo=Connection::getInstance();
        $tickets=$pdo->query("SELECT id,queue_id FROM talk_tickets WHERE status='queued' AND queue_id IS NOT NULL AND TIMESTAMPDIFF(SECOND,COALESCE(queued_at,created_at),NOW()) >= COALESCE((SELECT auto_assign_after_seconds FROM talk_queues q WHERE q.id=talk_tickets.queue_id),30) ORDER BY COALESCE(queued_at,created_at) ASC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
        $assigned=0;
        foreach($tickets as $ticket){
            $s=$pdo->prepare("SELECT qm.user_id FROM talk_queue_members qm INNER JOIN users u ON u.id=qm.user_id LEFT JOIN talk_presence p ON p.user_id=u.id LEFT JOIN talk_user_settings tus ON tus.user_id=u.id LEFT JOIN (SELECT assigned_user_id,COUNT(*) active FROM talk_tickets WHERE status IN ('assigned','open') GROUP BY assigned_user_id) a ON a.assigned_user_id=u.id WHERE qm.queue_id=:queue_id AND qm.status='active' AND u.status='active' AND p.status='online' AND p.last_seen_at>=DATE_SUB(NOW(),INTERVAL 5 MINUTE) AND COALESCE(a.active,0)<LEAST(qm.capacity,COALESCE(tus.max_active_tickets,qm.capacity)) ORDER BY COALESCE(a.active,0) ASC,p.last_seen_at DESC,qm.user_id ASC LIMIT 1");
            $s->execute(['queue_id'=>$ticket['queue_id']]); $uid=(int)$s->fetchColumn();
            if($uid>0 && $this->claim((int)$ticket['id'],$uid)){$assigned++;}
        }
        return $assigned;
    }

    public function updatePresence(int $userId,string $status): void
    {
        if(!in_array($status,['online','away','offline'],true)){$status='offline';}
        Connection::getInstance()->prepare("INSERT INTO talk_presence(user_id,status,last_seen_at) VALUES(:user_id,:status,NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status),last_seen_at=NOW()")->execute(['user_id'=>$userId,'status'=>$status]);
    }

    public function usersWithPresence(): array
    {
        return Connection::getInstance()->query("SELECT u.id,u.name,u.email,u.role,u.status,COALESCE(tus.talk_role,IF(u.role='admin','admin','agent')) talk_role,COALESCE(tus.max_active_tickets,5) max_active_tickets,COALESCE(p.status,'offline') presence,p.last_seen_at,(SELECT COUNT(*) FROM talk_tickets t WHERE t.assigned_user_id=u.id AND t.status IN ('assigned','open')) active_tickets FROM users u LEFT JOIN talk_user_settings tus ON tus.user_id=u.id LEFT JOIN talk_presence p ON p.user_id=u.id WHERE u.status='active' ORDER BY u.name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function reports(): array
    {
        $pdo=Connection::getInstance();
        return [
            'total'=>$this->count($pdo,"SELECT COUNT(*) FROM talk_tickets"),
            'queued'=>$this->count($pdo,"SELECT COUNT(*) FROM talk_tickets WHERE status='queued'"),
            'active'=>$this->count($pdo,"SELECT COUNT(*) FROM talk_tickets WHERE status IN ('assigned','open')"),
            'closed'=>$this->count($pdo,"SELECT COUNT(*) FROM talk_tickets WHERE status='closed'"),
            'by_queue'=>$pdo->query("SELECT COALESCE(q.name,'Sem fila') label,COUNT(t.id) total FROM talk_tickets t LEFT JOIN talk_queues q ON q.id=t.queue_id GROUP BY q.id,q.name ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function settings(): array
    {
        $rows=Connection::getInstance()->query("SELECT setting_key,setting_value FROM talk_settings ORDER BY setting_key")->fetchAll(PDO::FETCH_ASSOC);
        $out=[]; foreach($rows as $row){$out[$row['setting_key']]=$row['setting_value'];} return $out;
    }

    public function saveSettings(array $values,int $userId): void
    {
        $allowed=['jack.enabled','jack.wait_seconds','jack.transfer_summary','auto_assign.enabled','auto_assign.default_seconds'];
        $s=Connection::getInstance()->prepare("INSERT INTO talk_settings(setting_key,setting_value,updated_by) VALUES(:key,:value,:user_id) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)");
        foreach($allowed as $key){if(array_key_exists($key,$values)){$s->execute(['key'=>$key,'value'=>(string)$values[$key],'user_id'=>$userId]);}}
    }

    public function jackInteractions(): array
    {
        return Connection::getInstance()->query("SELECT ji.*,t.protocol,c.name contact_name FROM talk_jack_interactions ji INNER JOIN talk_tickets t ON t.id=ji.ticket_id INNER JOIN talk_conversations cv ON cv.id=t.conversation_id INNER JOIN talk_contacts c ON c.id=cv.contact_id ORDER BY ji.created_at DESC,ji.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function channels(): array
    {
        return Connection::getInstance()->query("SELECT id,type,name,status,last_connected_at,created_at,updated_at FROM talk_channels ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function event(int $ticketId,?int $userId,string $type,array $payload): void
    {
        $s=Connection::getInstance()->prepare("INSERT INTO talk_events(ticket_id,user_id,actor_type,event_type,payload) VALUES(:ticket_id,:user_id,:actor_type,:event_type,:payload)");
        $s->execute(['ticket_id'=>$ticketId,'user_id'=>$userId,'actor_type'=>$userId===null?'system':'user','event_type'=>$type,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR)]);
    }

    public function conversations(): array
    {
        return Connection::getInstance()->query(
            "SELECT cv.id, cv.channel, cv.status, cv.last_message_at, c.name AS contact_name, c.phone,
                    t.protocol, t.status AS ticket_status, q.name AS queue_name, u.name AS assigned_name
               FROM talk_conversations cv
               INNER JOIN talk_contacts c ON c.id = cv.contact_id
               LEFT JOIN talk_tickets t ON t.id = (
                   SELECT tt.id FROM talk_tickets tt WHERE tt.conversation_id = cv.id ORDER BY tt.id DESC LIMIT 1
               )
               LEFT JOIN talk_queues q ON q.id = t.queue_id
               LEFT JOIN users u ON u.id = t.assigned_user_id
              ORDER BY COALESCE(cv.last_message_at, cv.created_at) DESC LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contacts(): array
    {
        return Connection::getInstance()->query(
            'SELECT id, name, phone, email, channel, created_at, updated_at FROM talk_contacts ORDER BY updated_at DESC, id DESC LIMIT 100'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    private function count(PDO $pdo, string $sql): int
    {
        return (int) $pdo->query($sql)->fetchColumn();
    }
}
