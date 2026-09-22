<?php
declare(strict_types=1);

namespace Moves\Services\Talk;

use Moves\Boot\Connection;
use PDO;
use RuntimeException;

/** Persists inbound provider messages in the tenant/channel that received them. */
final class TalkInboundService
{
    /** @param array<string,mixed> $message */
    public function receiveWhatsApp(int $channelId,array $message): int
    {
        $pdo=Connection::getInstance();
        $channelStmt=$pdo->prepare("SELECT id,tenant_id,queue_id,status FROM talk_channels WHERE id=:id AND type='whatsapp' LIMIT 1");
        $channelStmt->execute(['id'=>$channelId]);$channel=$channelStmt->fetch(PDO::FETCH_ASSOC);
        if(!$channel||$channel['status']!=='active')throw new RuntimeException('Canal WhatsApp inexistente ou inativo.');
        $from=preg_replace('/\D+/','',(string)($message['from']??''))??'';$externalId=trim((string)($message['id']??''));
        if($from===''||$externalId==='')throw new RuntimeException('Mensagem recebida sem remetente ou identificador.');
        $type=trim((string)($message['type']??'text'))?:'text';$body=mb_substr(trim((string)($message['body']??'')),0,4000);
        $tenantId=(int)$channel['tenant_id'];$pdo->beginTransaction();
        try{
            $duplicate=$pdo->prepare("SELECT id FROM talk_messages WHERE external_id=:external_id LIMIT 1");$duplicate->execute(['external_id'=>$externalId]);$existing=(int)$duplicate->fetchColumn();if($existing>0){$pdo->commit();return $existing;}
            $contact=$pdo->prepare("SELECT id,condominium_id FROM talk_contacts WHERE tenant_id=:tenant_id AND channel='whatsapp' AND external_id=:external_id LIMIT 1");$contact->execute(['tenant_id'=>$tenantId,'external_id'=>$from]);$contactRow=$contact->fetch(PDO::FETCH_ASSOC);$contactId=(int)($contactRow['id']??0);$condominiumId=(int)($contactRow['condominium_id']??0);
            if($contactId<=0){$s=$pdo->prepare("INSERT INTO talk_contacts(tenant_id,name,phone,external_id,channel,metadata) VALUES(:tenant_id,:name,:phone,:external_id,'whatsapp',:metadata)");$s->execute(['tenant_id'=>$tenantId,'name'=>$from,'phone'=>$from,'external_id'=>$from,'metadata'=>json_encode(['channel_id'=>$channelId],JSON_THROW_ON_ERROR)]);$contactId=(int)$pdo->lastInsertId();}
            $conversation=$pdo->prepare("SELECT id FROM talk_conversations WHERE tenant_id=:tenant_id AND channel_id=:channel_id AND contact_id=:contact_id AND status='open' ORDER BY id DESC LIMIT 1");$conversation->execute(['tenant_id'=>$tenantId,'channel_id'=>$channelId,'contact_id'=>$contactId]);$conversationId=(int)$conversation->fetchColumn();
            if($conversationId<=0){$s=$pdo->prepare("INSERT INTO talk_conversations(tenant_id,channel_id,contact_id,channel,external_id,status,last_message_at) VALUES(:tenant_id,:channel_id,:contact_id,'whatsapp',:external_id,'open',NOW())");$s->execute(['tenant_id'=>$tenantId,'channel_id'=>$channelId,'contact_id'=>$contactId,'external_id'=>$from.':'.$channelId]);$conversationId=(int)$pdo->lastInsertId();}
            $ticket=$pdo->prepare("SELECT id FROM talk_tickets WHERE tenant_id=:tenant_id AND conversation_id=:conversation_id AND status<>'closed' ORDER BY id DESC LIMIT 1");$ticket->execute(['tenant_id'=>$tenantId,'conversation_id'=>$conversationId]);$ticketId=(int)$ticket->fetchColumn();
            if($ticketId<=0){$protocol=(new TalkProtocolService())->next($tenantId,$condominiumId>0?$condominiumId:null);$s=$pdo->prepare("INSERT INTO talk_tickets(tenant_id,condominium_id,protocol,conversation_id,queue_id,status,priority,subject,source,queued_at) VALUES(:tenant_id,:condominium_id,:protocol,:conversation_id,:queue_id,'queued','normal','WhatsApp','whatsapp',NOW())");$s->execute(['tenant_id'=>$tenantId,'condominium_id'=>$condominiumId>0?$condominiumId:null,'protocol'=>$protocol,'conversation_id'=>$conversationId,'queue_id'=>$channel['queue_id']]);$ticketId=(int)$pdo->lastInsertId();}
            $s=$pdo->prepare("INSERT INTO talk_messages(conversation_id,ticket_id,sender_type,external_id,direction,type,body,metadata,sent_at) VALUES(:conversation_id,:ticket_id,'contact',:external_id,'inbound',:type,:body,:metadata,NOW())");$s->execute(['conversation_id'=>$conversationId,'ticket_id'=>$ticketId,'external_id'=>$externalId,'type'=>$type,'body'=>$body,'metadata'=>json_encode(['channel_id'=>$channelId,'provider'=>'whatsapp'],JSON_THROW_ON_ERROR)]);$messageId=(int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE talk_conversations SET last_message_at=NOW(),updated_at=NOW() WHERE id=:id")->execute(['id'=>$conversationId]);$pdo->prepare("UPDATE talk_tickets SET last_activity_at=NOW(),updated_at=NOW() WHERE id=:id")->execute(['id'=>$ticketId]);$pdo->commit();return $messageId;
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
