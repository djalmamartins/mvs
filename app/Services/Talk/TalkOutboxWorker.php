<?php
declare(strict_types=1);
namespace Moves\Services\Talk;
use Moves\Boot\Connection;use Moves\Services\Talk\Transport\WhatsAppTransport;use Moves\Services\Talk\Transport\WhatsAppTransportFactory;use PDO;use RuntimeException;use Throwable;

final class TalkOutboxWorker
{
    public function __construct(private ?WhatsAppTransport $transport=null,private ?int $tenantId=null,private ?string $workerId=null,private int $lockTimeoutSeconds=120,private int $baseBackoffSeconds=15){$this->transport??=WhatsAppTransportFactory::make();$this->workerId??='worker-'.bin2hex(random_bytes(8));}

    /** @return array{id:int,status:string,attempts:int}|null */
    public function processNext():?array
    {
        $item=$this->claim();if($item===null)return null;
        try{
            $dispatch=$this->dispatchData((int)$item['id'],(int)$item['tenant_id']);
            if((string)$dispatch['channel_status']!=='active')throw new RuntimeException('Canal inativo.');
            if((string)$dispatch['connection_status']!=='connected')throw new RuntimeException('Canal WhatsApp desconectado ou reconectando.');
            $payload=json_decode((string)$dispatch['payload'],true,flags:JSON_THROW_ON_ERROR);$recipient=trim((string)($payload['recipient']??''));if($recipient==='')throw new RuntimeException('Destinatário inválido.');
            $result=$this->transport->sendText((string)$dispatch['session_key'],$recipient,(string)$dispatch['body'],(string)$dispatch['idempotency_key']);
            $externalId=mb_substr(trim($result['message_id']),0,190);if($externalId==='')throw new RuntimeException('Bridge não confirmou o identificador da mensagem.');
            $this->markSent($dispatch,$externalId);return['id'=>(int)$item['id'],'status'=>'sent','attempts'=>(int)$item['attempts']];
        }catch(Throwable $exception){return$this->markFailure($item,$exception);}
    }

    public function requeue(int $outboxId,int $userId,int $tenantId):void
    {
        $pdo=Connection::getInstance();$manager=$pdo->prepare("SELECT COUNT(*) FROM talk_tenant_users WHERE tenant_id=:tenant_id AND user_id=:user_id AND status='active' AND role IN ('admin','manager','supervisor')");$manager->execute(['tenant_id'=>$tenantId,'user_id'=>$userId]);if((int)$manager->fetchColumn()!==1)throw new RuntimeException('Somente gestores podem reenfileirar mensagens.');
        $pdo->beginTransaction();try{$find=$pdo->prepare("SELECT id,ticket_id,message_id FROM talk_outbox WHERE tenant_id=:tenant_id AND id=:id AND status='failed' LIMIT 1 FOR UPDATE");$find->execute(['tenant_id'=>$tenantId,'id'=>$outboxId]);$item=$find->fetch(PDO::FETCH_ASSOC);if(!is_array($item))throw new RuntimeException('Mensagem falha não encontrada.');$pdo->prepare("UPDATE talk_outbox SET status='pending',attempts=0,available_at=NOW(),locked_at=NULL,locked_by=NULL,processing_started_at=NULL,failed_at=NULL,last_error=NULL,updated_at=NOW() WHERE tenant_id=:tenant_id AND id=:id")->execute(['tenant_id'=>$tenantId,'id'=>$outboxId]);$pdo->prepare("UPDATE talk_messages SET delivery_status='pending',delivery_error=NULL,delivery_updated_at=NOW() WHERE tenant_id=:tenant_id AND id=:id")->execute(['tenant_id'=>$tenantId,'id'=>$item['message_id']]);$this->event($pdo,$tenantId,(int)$item['ticket_id'],$userId,'message.requeued',['outbox_id'=>$outboxId,'message_id'=>(int)$item['message_id']]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    /** @return array<string,mixed>|null */
    private function claim():?array
    {
        $pdo=Connection::getInstance();
        $claimLock='moves-talk-outbox-claim';
        $lock=$pdo->prepare('SELECT GET_LOCK(:name,2)');
        $lock->execute(['name'=>$claimLock]);
        if((int)$lock->fetchColumn()!==1)return null;
        try{
            $pdo->beginTransaction();
            $lockTimeout=max(1,$this->lockTimeoutSeconds);
            $sql="SELECT id,tenant_id,ticket_id,message_id,attempts,max_attempts FROM talk_outbox WHERE ((status='pending' AND available_at<=NOW()) OR (status='processing' AND locked_at<DATE_SUB(NOW(),INTERVAL {$lockTimeout} SECOND)))";
            $params=[];
            if($this->tenantId!==null){$sql.=' AND tenant_id=:tenant_id';$params['tenant_id']=$this->tenantId;}
            $sql.=' ORDER BY available_at,id LIMIT 1 FOR UPDATE';
            $select=$pdo->prepare($sql);$select->execute($params);$item=$select->fetch(PDO::FETCH_ASSOC);
            if(!is_array($item)){$pdo->commit();return null;}
            $attempts=(int)$item['attempts']+1;
            $update=$pdo->prepare("UPDATE talk_outbox SET status='processing',attempts=:attempts,locked_at=NOW(),locked_by=:worker_id,processing_started_at=NOW(),last_error=NULL,updated_at=NOW() WHERE id=:id AND tenant_id=:tenant_id");
            $update->execute(['attempts'=>$attempts,'worker_id'=>$this->workerId,'id'=>$item['id'],'tenant_id'=>$item['tenant_id']]);
            $this->event($pdo,(int)$item['tenant_id'],(int)$item['ticket_id'],null,'message.processing',['outbox_id'=>(int)$item['id'],'message_id'=>(int)$item['message_id'],'attempt'=>$attempts]);
            $pdo->commit();$item['attempts']=$attempts;return$item;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
        finally{$release=$pdo->prepare('SELECT RELEASE_LOCK(:name)');$release->execute(['name'=>$claimLock]);}
    }

    /** @return array<string,mixed> */
    private function dispatchData(int $id,int $tenantId):array
    {
        $sql="SELECT o.*,m.body,m.delivery_status,t.channel_id ticket_channel_id,ch.session_key,ch.status channel_status,ch.connection_status FROM talk_outbox o INNER JOIN talk_messages m ON m.tenant_id=o.tenant_id AND m.id=o.message_id INNER JOIN talk_tickets t ON t.tenant_id=o.tenant_id AND t.id=o.ticket_id AND t.channel_id=o.channel_id INNER JOIN talk_channels ch ON ch.tenant_id=o.tenant_id AND ch.id=o.channel_id WHERE o.tenant_id=:tenant_id AND o.id=:id AND o.status='processing' AND o.locked_by=:worker_id LIMIT 1";$statement=Connection::getInstance()->prepare($sql);$statement->execute(['tenant_id'=>$tenantId,'id'=>$id,'worker_id'=>$this->workerId]);$row=$statement->fetch(PDO::FETCH_ASSOC);if(!is_array($row))throw new RuntimeException('Item da outbox perdeu o vínculo estrutural.');return$row;
    }

    /** @param array<string,mixed> $item */
    private function markSent(array $item,string $externalId):void
    {
        $pdo=Connection::getInstance();$pdo->beginTransaction();try{$update=$pdo->prepare("UPDATE talk_outbox SET status='sent',sent_at=NOW(),external_message_id=:external_id,locked_at=NULL,locked_by=NULL,last_error=NULL,updated_at=NOW() WHERE tenant_id=:tenant_id AND id=:id AND status='processing' AND locked_by=:worker_id");$update->execute(['external_id'=>$externalId,'tenant_id'=>$item['tenant_id'],'id'=>$item['id'],'worker_id'=>$this->workerId]);if($update->rowCount()!==1)throw new RuntimeException('Lock da outbox não pertence a este worker.');$pdo->prepare("UPDATE talk_messages SET delivery_status='sent',delivery_error=NULL,delivery_updated_at=NOW(),external_id=:external_id,metadata=JSON_SET(COALESCE(metadata,JSON_OBJECT()),'$.delivery_status','sent') WHERE tenant_id=:tenant_id AND id=:id")->execute(['external_id'=>$externalId,'tenant_id'=>$item['tenant_id'],'id'=>$item['message_id']]);$this->event($pdo,(int)$item['tenant_id'],(int)$item['ticket_id'],null,'message.sent',['outbox_id'=>(int)$item['id'],'message_id'=>(int)$item['message_id'],'attempt'=>(int)$item['attempts'],'external_message_id'=>$externalId]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    /** @param array<string,mixed> $item @return array{id:int,status:string,attempts:int} */
    private function markFailure(array $item,Throwable $exception):array
    {
        $attempts=(int)$item['attempts'];
        $failed=$attempts>=(int)$item['max_attempts']||!$this->recoverable($exception);
        $status=$failed?'failed':'pending';
        $error=mb_substr(trim($exception->getMessage())?:'Falha no envio outbound.',0,500);
        $delay=min(3600,$this->baseBackoffSeconds*(2**max(0,$attempts-1)));
        $available=(new \DateTimeImmutable())->modify('+'.$delay.' seconds')->format('Y-m-d H:i:s');
        $pdo=Connection::getInstance();
        $pdo->beginTransaction();
        try{
            $sql="UPDATE talk_outbox SET status=:status,available_at=:available_at,locked_at=NULL,locked_by=NULL,failed_at=".($failed?'NOW()':'NULL').",last_error=:last_error,updated_at=NOW() WHERE tenant_id=:tenant_id AND id=:id AND status='processing' AND locked_by=:worker_id";
            $update=$pdo->prepare($sql);
            $update->execute(['status'=>$status,'available_at'=>$available,'last_error'=>$error,'tenant_id'=>$item['tenant_id'],'id'=>$item['id'],'worker_id'=>$this->workerId]);
            if($update->rowCount()!==1)throw new RuntimeException('Lock da outbox não pertence a este worker.');
            $pdo->prepare("UPDATE talk_messages SET delivery_status=:status,delivery_error=:error,delivery_updated_at=NOW(),metadata=JSON_SET(COALESCE(metadata,JSON_OBJECT()),'$.delivery_status',:metadata_status) WHERE tenant_id=:tenant_id AND id=:id")->execute(['status'=>$failed?'failed':'pending','error'=>$error,'metadata_status'=>$failed?'failed':'pending','tenant_id'=>$item['tenant_id'],'id'=>$item['message_id']]);
            $event=$failed?'message.failed':'message.retry_scheduled';
            $this->event($pdo,(int)$item['tenant_id'],(int)$item['ticket_id'],null,$event,['outbox_id'=>(int)$item['id'],'message_id'=>(int)$item['message_id'],'attempt'=>$attempts,'next_attempt_at'=>$failed?null:$available,'error'=>$error]);
            $pdo->commit();
            return['id'=>(int)$item['id'],'status'=>$status,'attempts'=>$attempts];
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    private function recoverable(Throwable $error):bool{$message=mb_strtolower($error->getMessage());foreach(['destinatário inválido','mensagem vazia','identificador de sessão inválido','canal inativo','vínculo estrutural']as$permanent)if(str_contains($message,$permanent))return false;return true;}
    /** @param array<string,mixed> $payload */private function event(PDO $pdo,int $tenantId,int $ticketId,?int $userId,string $type,array $payload):void{$pdo->prepare("INSERT INTO talk_events(tenant_id,ticket_id,user_id,actor_type,event_type,payload) VALUES(:tenant_id,:ticket_id,:user_id,:actor_type,:event_type,:payload)")->execute(['tenant_id'=>$tenantId,'ticket_id'=>$ticketId,'user_id'=>$userId,'actor_type'=>$userId===null?'system':'user','event_type'=>$type,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);}
}
