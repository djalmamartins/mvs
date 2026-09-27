<?php
declare(strict_types=1);
namespace Moves\Services\Talk;
use Moves\Boot\Connection;use PDO;use RuntimeException;

/** Persiste a intenção outbound sem depender do bridge durante o request HTTP. */
final class TalkOutboundService
{
    public function __construct(private ?int $tenantId=null){}
    private function tenantId():int{return $this->tenantId??=(new TalkTenantContext())->currentTenantId();}

    public function sendText(int $ticketId,int $userId,string $body,?string $idempotencyKey=null):int
    {
        $body=mb_substr(trim($body),0,4000);if($body==='')throw new RuntimeException('Digite uma mensagem antes de enviar.');
        $idempotencyKey=$this->idempotencyKey($idempotencyKey);$pdo=Connection::getInstance();$pdo->beginTransaction();
        try{
            $existing=$pdo->prepare("SELECT o.message_id,m.sender_user_id,o.ticket_id FROM talk_outbox o INNER JOIN talk_messages m ON m.tenant_id=o.tenant_id AND m.id=o.message_id WHERE o.tenant_id=:tenant_id AND o.idempotency_key=:idempotency_key LIMIT 1 FOR UPDATE");$existing->execute(['tenant_id'=>$this->tenantId(),'idempotency_key'=>$idempotencyKey]);$queued=$existing->fetch(PDO::FETCH_ASSOC);
            if(is_array($queued)){if((int)$queued['ticket_id']!==$ticketId||(int)$queued['sender_user_id']!==$userId)throw new RuntimeException('Chave de envio já utilizada.');$pdo->commit();return(int)$queued['message_id'];}
            $statement=$pdo->prepare("SELECT t.id,t.channel_id,t.conversation_id,t.assigned_user_id,t.status,t.source,cv.channel,c.phone,c.external_id contact_external_id,ch.status channel_status FROM talk_tickets t INNER JOIN talk_conversations cv ON cv.tenant_id=t.tenant_id AND cv.id=t.conversation_id INNER JOIN talk_contacts c ON c.tenant_id=t.tenant_id AND c.id=cv.contact_id INNER JOIN talk_channels ch ON ch.tenant_id=t.tenant_id AND ch.id=t.channel_id WHERE t.tenant_id=:tenant_id AND t.id=:id LIMIT 1 FOR UPDATE");$statement->execute(['tenant_id'=>$this->tenantId(),'id'=>$ticketId]);$ticket=$statement->fetch(PDO::FETCH_ASSOC);
            if(!$ticket||(int)($ticket['assigned_user_id']??0)!==$userId||!in_array((string)$ticket['status'],['assigned','open'],true))throw new RuntimeException('Atendimento indisponível para envio.');
            if((string)$ticket['channel_status']!=='active')throw new RuntimeException('O canal deste atendimento está inativo.');
            $channel=(string)($ticket['channel']?:$ticket['source']);if(!in_array($channel,['whatsapp','simulation'],true))throw new RuntimeException('Canal de saída ainda não suportado: '.$channel.'.');
            $recipient=trim((string)($ticket['phone']?:$ticket['contact_external_id']));if($channel==='whatsapp'&&$recipient==='')throw new RuntimeException('Contato sem número de WhatsApp válido.');
            $deliveryStatus=$channel==='simulation'?'sent':'pending';$metadata=['channel'=>$channel,'delivery_status'=>$deliveryStatus];if($channel==='simulation')$metadata['simulation']=true;
            $insert=$pdo->prepare("INSERT INTO talk_messages(tenant_id,channel_id,conversation_id,ticket_id,sender_type,sender_user_id,direction,delivery_status,type,body,metadata,sent_at) VALUES(:tenant_id,:channel_id,:conversation_id,:ticket_id,'user',:user_id,'outbound',:delivery_status,'text',:body,:metadata,NOW())");$insert->execute(['tenant_id'=>$this->tenantId(),'channel_id'=>(int)$ticket['channel_id'],'conversation_id'=>(int)$ticket['conversation_id'],'ticket_id'=>$ticketId,'user_id'=>$userId,'delivery_status'=>$deliveryStatus,'body'=>$body,'metadata'=>json_encode($metadata,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);$messageId=(int)$pdo->lastInsertId();
            if($channel==='whatsapp'){$maxAttempts=max(1,min(20,(int)($_ENV['TALK_OUTBOX_MAX_ATTEMPTS']??5)));$outbox=$pdo->prepare("INSERT INTO talk_outbox(tenant_id,channel_id,ticket_id,message_id,type,status,attempts,max_attempts,available_at,idempotency_key,payload) VALUES(:tenant_id,:channel_id,:ticket_id,:message_id,'text','pending',0,:max_attempts,NOW(),:idempotency_key,:payload)");$outbox->execute(['tenant_id'=>$this->tenantId(),'channel_id'=>(int)$ticket['channel_id'],'ticket_id'=>$ticketId,'message_id'=>$messageId,'max_attempts'=>$maxAttempts,'idempotency_key'=>$idempotencyKey,'payload'=>json_encode(['recipient'=>$recipient],JSON_THROW_ON_ERROR)]);}
            $pdo->prepare("UPDATE talk_tickets SET first_response_at=COALESCE(first_response_at,NOW()),last_activity_at=NOW(),updated_at=NOW() WHERE tenant_id=:tenant_id AND id=:id")->execute(['tenant_id'=>$this->tenantId(),'id'=>$ticketId]);$pdo->prepare("UPDATE talk_conversations SET last_message_at=NOW(),updated_at=NOW() WHERE tenant_id=:tenant_id AND id=:id")->execute(['tenant_id'=>$this->tenantId(),'id'=>(int)$ticket['conversation_id']]);
            $eventType=$channel==='whatsapp'?'message.queued':'message.sent';$pdo->prepare("INSERT INTO talk_events(tenant_id,ticket_id,user_id,actor_type,event_type,payload) VALUES(:tenant_id,:ticket_id,:user_id,'user',:event_type,:payload)")->execute(['tenant_id'=>$this->tenantId(),'ticket_id'=>$ticketId,'user_id'=>$userId,'event_type'=>$eventType,'payload'=>json_encode(['message_id'=>$messageId,'channel'=>$channel,'delivery_status'=>$deliveryStatus],JSON_THROW_ON_ERROR)]);
            $pdo->commit();return$messageId;
        }catch(\Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw$exception;}
    }

    private function idempotencyKey(?string $key):string{$key=trim((string)$key);if($key==='')return bin2hex(random_bytes(24));if(strlen($key)>100||!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{15,99}$/D',$key))throw new RuntimeException('Chave de envio inválida.');return$key;}
}
