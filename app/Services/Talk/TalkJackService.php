<?php

declare(strict_types=1);

namespace Moves\Services\Talk;

use Moves\Boot\Connection;
use PDO;

final class TalkJackService
{
    public function __construct(private ?int $tenantId = null) {}
    private function tenantId(): int { return $this->tenantId ??= (new TalkTenantContext())->currentTenantId(); }
    public function processEligible(int $limit=10): int
    {
        $pdo=Connection::getInstance();
        $settings=$this->settings();
        if(($settings['jack.enabled']??'0')!=='1'){return 0;}
        $wait=max(0,(int)($settings['jack.wait_seconds']??60));$limit=max(1,min(25,$limit));
        $sql="SELECT t.id,t.channel_id,t.conversation_id,t.protocol,c.name contact_name FROM talk_tickets t INNER JOIN talk_conversations cv ON cv.tenant_id=t.tenant_id AND cv.id=t.conversation_id INNER JOIN talk_contacts c ON c.tenant_id=t.tenant_id AND c.id=cv.contact_id WHERE t.tenant_id=:tenant_id AND t.status='queued' AND TIMESTAMPDIFF(SECOND,COALESCE(t.queued_at,t.created_at),NOW())>=:wait AND NOT EXISTS(SELECT 1 FROM talk_jack_interactions ji WHERE ji.tenant_id=t.tenant_id AND ji.ticket_id=t.id AND ji.action='jack.reply') ORDER BY COALESCE(t.queued_at,t.created_at),t.id LIMIT {$limit}";
        $s=$pdo->prepare($sql);$s->execute(['tenant_id'=>$this->tenantId(),'wait'=>$wait]);$tickets=$s->fetchAll(PDO::FETCH_ASSOC);$done=0;
        foreach($tickets as $ticket){if($this->reply($ticket)){$done++;}}
        return $done;
    }

    private function reply(array $ticket): bool
    {
        $pdo=Connection::getInstance();$messages=$pdo->prepare("SELECT sender_type,direction,body,sent_at FROM talk_messages WHERE tenant_id=:tenant_id AND conversation_id=:conversation_id AND type='text' ORDER BY sent_at,id");
        $messages->execute(['tenant_id'=>$this->tenantId(),'conversation_id'=>$ticket['conversation_id']]);$history=$messages->fetchAll(PDO::FETCH_ASSOC);
        $parts=[];$lastInbound='';
        foreach($history as $row){$body=trim((string)($row['body']??''));if($body==='')continue;$parts[]=(($row['direction']??'')==='inbound'?'Cliente':'Atendimento').': '.$body;if(($row['direction']??'')==='inbound')$lastInbound=$body;}
        if($lastInbound===''){return false;}
        $context=mb_substr(implode("\n",$parts),0,6000);
        $excerpt=mb_substr(preg_replace('/\s+/u',' ',trim($lastInbound))?:trim($lastInbound),0,180);
        $name=trim((string)($ticket['contact_name']??''));$first=$name!==''?preg_split('/\s+/',$name)[0]:'';
        $body=($first!==''?$first.', ':'').'recebi sua mensagem sobre "'.$excerpt.'". Já organizei o contexto deste atendimento para que ele siga sem você precisar repetir as informações.';
        $pdo->beginTransaction();
        try{
            $insert=$pdo->prepare("INSERT INTO talk_messages(tenant_id,channel_id,conversation_id,ticket_id,sender_type,sender_user_id,direction,type,body,metadata,sent_at) VALUES(:tenant_id,:channel_id,:conversation_id,:ticket_id,'jack',NULL,'outbound','text',:body,:metadata,NOW())");
            $insert->execute(['tenant_id'=>$this->tenantId(),'channel_id'=>$ticket['channel_id'],'conversation_id'=>$ticket['conversation_id'],'ticket_id'=>$ticket['id'],'body'=>$body,'metadata'=>json_encode(['engine'=>'jack-v1','context_read'=>true],JSON_THROW_ON_ERROR)]);
            $messageId=(int)$pdo->lastInsertId();
            $summary=mb_substr('Contexto lido integralmente. Última solicitação: '.$excerpt,0,1000);
            $ji=$pdo->prepare("INSERT INTO talk_jack_interactions(tenant_id,ticket_id,message_id,action,summary,payload) VALUES(:tenant_id,:ticket_id,:message_id,'jack.reply',:summary,:payload)");
            $ji->execute(['tenant_id'=>$this->tenantId(),'ticket_id'=>$ticket['id'],'message_id'=>$messageId,'summary'=>$summary,'payload'=>json_encode(['context'=>$context,'mode'=>'contextual_acknowledgement'],JSON_THROW_ON_ERROR)]);
            $pdo->prepare('UPDATE talk_tickets SET last_activity_at=NOW(),updated_at=NOW() WHERE tenant_id=:tenant_id AND id=:id')->execute(['tenant_id'=>$this->tenantId(),'id'=>$ticket['id']]);
            $pdo->prepare('UPDATE talk_conversations SET last_message_at=NOW(),updated_at=NOW() WHERE tenant_id=:tenant_id AND id=:id')->execute(['tenant_id'=>$this->tenantId(),'id'=>$ticket['conversation_id']]);
            $pdo->prepare("INSERT INTO talk_events(tenant_id,ticket_id,user_id,actor_type,event_type,payload) VALUES(:tenant_id,:ticket_id,NULL,'jack','jack.replied',:payload)")->execute(['tenant_id'=>$this->tenantId(),'ticket_id'=>$ticket['id'],'payload'=>json_encode(['message_id'=>$messageId],JSON_THROW_ON_ERROR)]);
            $pdo->commit();return true;
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private function settings(): array
    {
        $s=Connection::getInstance()->prepare("SELECT setting_key,setting_value FROM talk_settings WHERE tenant_id=:tenant_id AND setting_key IN ('jack.enabled','jack.wait_seconds')");$s->execute(['tenant_id'=>$this->tenantId()]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);$out=[];foreach($rows as $row){$out[$row['setting_key']]=$row['setting_value'];}return $out;
    }
}
