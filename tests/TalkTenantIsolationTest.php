<?php

declare(strict_types=1);

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Services\Talk\TalkAttachmentService;
use Moves\Services\Talk\TalkChannelService;
use Moves\Services\Talk\TalkInboundService;
use Moves\Services\Talk\TalkJackService;
use Moves\Services\Talk\TalkMetadataService;
use Moves\Services\Talk\TalkNotificationService;
use Moves\Services\Talk\TalkOperationsWorker;
use Moves\Services\Talk\TalkOutboundService;
use Moves\Services\Talk\TalkOutboxWorker;
use Moves\Services\Talk\TalkService;
use Moves\Services\Talk\TalkTenantContext;
use Moves\Services\Talk\Transport\WhatsAppTransport;
use PHPUnit\Framework\TestCase;

final class OutboxTestTransport implements WhatsAppTransport
{
    public int $calls=0;
    public bool $offline=false;
    public string $channelKey='';
    /** @var null|callable */ public $duringSend=null;
    public function sendText(string $channelKey,string $to,string $text,?string $idempotencyKey=null):array{$this->calls++;$this->channelKey=$channelKey;if($this->duringSend!==null){$callback=$this->duringSend;$this->duringSend=null;$callback();}if($this->offline)throw new RuntimeException('Bridge indisponível: Connection refused');return['message_id'=>'out-'.$this->calls,'status'=>'sent'];}
    public function sendMedia(string $channelKey,string $to,string $absolutePath,string $mimeType,?string $caption=null):array{return['message_id'=>'media','status'=>'sent'];}
    public function status(string $channelKey):array{return['status'=>'connected','connected'=>true,'detail'=>null];}
    public function connect(string $channelKey,string $externalId):array{return$this->status($channelKey);}
    public function logout(string $channelKey):array{return['ok'=>true];}
}

final class TalkTenantIsolationTest extends TestCase
{
    private PDO $pdo;
    private string $prefix;
    /** @var array<string,int|string> */
    private array $a;
    /** @var array<string,int|string> */
    private array $b;

    protected function setUp(): void
    {
        Environment::load(dirname(__DIR__));
        $this->pdo = Connection::getInstance();
        $this->prefix = 'tenant-'.bin2hex(random_bytes(5));
        $this->a = $this->fixture('a');
        $this->b = $this->fixture('b');
    }

    protected function tearDown(): void
    {
        foreach ([$this->a ?? [], $this->b ?? []] as $fixture) {
            if (isset($fixture['tenant'])) {
                $tenant=(int)$fixture['tenant'];
                foreach (['talk_notifications','talk_attachments','talk_ticket_tags','talk_jack_interactions','talk_events','talk_notes','talk_transfers','talk_outbox','talk_messages','talk_tickets','talk_conversations','talk_contacts','talk_queue_members','talk_channels','talk_queues','talk_departments','talk_settings','talk_presence','talk_user_settings','talk_tenant_users'] as $table) {
                    $this->pdo->prepare("DELETE FROM {$table} WHERE tenant_id=:tenant_id")->execute(['tenant_id'=>$tenant]);
                }
                $this->pdo->prepare('DELETE FROM talk_tenants WHERE id=:id')->execute(['id'=>$fixture['tenant']]);
            }
            if (isset($fixture['user'])) {
                $this->pdo->prepare('DELETE FROM users WHERE id=:id')->execute(['id'=>$fixture['user']]);
            }
        }
    }

    public function testApplicationQueriesAndDirectIdsNeverCrossTenants(): void
    {
        $a = new TalkService((int)$this->a['tenant']);
        $b = new TalkService((int)$this->b['tenant']);

        self::assertSame([(int)$this->a['contact']], array_map('intval', array_column($a->contacts(), 'id')));
        self::assertSame([(int)$this->b['contact']], array_map('intval', array_column($b->contacts(), 'id')));
        self::assertSame([(int)$this->a['conversation']], array_map('intval', array_column($a->conversations(), 'id')));
        self::assertSame([(int)$this->b['conversation']], array_map('intval', array_column($b->conversations(), 'id')));
        self::assertNull($a->ticket((int)$this->b['ticket']));
        self::assertNull($b->ticket((int)$this->a['ticket']));
        self::assertFalse($a->canViewTicket((int)$this->b['ticket'], (int)$this->a['user']));
        self::assertFalse($a->canOperateTicket((int)$this->b['ticket'], (int)$this->a['user']));

        $ownTicket = $a->ticket((int)$this->a['ticket']);
        self::assertIsArray($ownTicket);
        self::assertCount(1, $ownTicket['messages']);
        self::assertSame((int)$this->a['message'], (int)$ownTicket['messages'][0]['id']);
        self::assertSame([(int)$this->a['queue']], array_map('intval', array_column($a->queues(), 'id')));
        self::assertSame([(int)$this->a['department']], array_map('intval', array_column($a->departments(), 'id')));
        self::assertSame([(int)$this->a['channel']], array_map('intval', array_column($a->channels(), 'id')));
        self::assertSame('value-a', $a->settings()['isolation.key']);
        self::assertSame('value-b', $b->settings()['isolation.key']);

        self::assertSame('admin', $a->permissions((int)$this->a['user'])['talk_role']);
        self::assertSame('none', $a->permissions((int)$this->b['user'])['talk_role']);
        self::assertFalse($a->canManage((int)$this->b['user']));
        $this->expectException(RuntimeException::class);
        (new TalkTenantContext())->forUser((int)$this->a['user'], (int)$this->b['tenant']);
    }

    public function testSearchHistoryReportsNotificationsAttachmentsAndTagsAreIsolated(): void
    {
        $this->pdo->prepare("UPDATE talk_tickets SET status='closed',closed_at=NOW() WHERE id IN (:a,:b)")
            ->execute(['a'=>$this->a['ticket'],'b'=>$this->b['ticket']]);
        $a = new TalkService((int)$this->a['tenant']);
        $metadataA = new TalkMetadataService((int)$this->a['tenant']);

        $search = $metadataA->search(['q'=>$this->prefix]);
        self::assertCount(1, $search);
        self::assertSame((int)$this->a['ticket'], (int)$search[0]['id']);
        self::assertSame([(int)$this->a['ticket']], array_map('intval', array_column($a->history(), 'id')));
        self::assertSame(1, $a->reports()['total']);
        self::assertSame(1, $a->reports()['closed']);
        self::assertCount(1, $a->reports()['by_queue']);

        $notificationsA = new TalkNotificationService((int)$this->a['tenant']);
        self::assertSame(1, $notificationsA->unreadCount((int)$this->a['user']));
        self::assertSame(0, $notificationsA->unreadCount((int)$this->b['user']));
        self::assertCount(1, $notificationsA->unread((int)$this->a['user']));

        $attachmentsA = new TalkAttachmentService((int)$this->a['tenant']);
        self::assertNotNull($attachmentsA->find((int)$this->a['attachment']));
        self::assertNull($attachmentsA->find((int)$this->b['attachment']));
        self::assertCount(1, $attachmentsA->forTicket((int)$this->a['ticket']));
        self::assertSame([(int)$this->a['tag']], array_map('intval', array_column($metadataA->tags(), 'id')));
        self::assertSame([(int)$this->a['tag']], array_map('intval', array_column($metadataA->ticketTags((int)$this->a['ticket']), 'id')));
        self::assertSame([], $metadataA->ticketTags((int)$this->b['ticket']));
    }

    public function testSyncRevisionTracksSameSecondEventsWithoutCrossTenantNoise(): void
    {
        $a = new TalkService((int)$this->a['tenant']);
        $userId = (int)$this->a['user'];
        $initial = $a->syncState($userId)['revision'];
        self::assertIsString($initial);

        $event = $this->pdo->prepare("INSERT INTO talk_events(tenant_id,ticket_id,user_id,actor_type,event_type,payload,created_at) VALUES(:tenant,:ticket,NULL,'system','message.sent','{}',:created_at)");
        $sameSecond = date('Y-m-d H:i:s');
        $event->execute(['tenant'=>$this->b['tenant'],'ticket'=>$this->b['ticket'],'created_at'=>$sameSecond]);
        self::assertSame($initial, $a->syncState($userId)['revision']);

        $event->execute(['tenant'=>$this->a['tenant'],'ticket'=>$this->a['ticket'],'created_at'=>$sameSecond]);
        $first = $a->syncState($userId)['revision'];
        self::assertNotSame($initial, $first);
        $event->execute(['tenant'=>$this->a['tenant'],'ticket'=>$this->a['ticket'],'created_at'=>$sameSecond]);
        $second = $a->syncState($userId)['revision'];
        self::assertNotSame($first, $second);
        $a->markTicketRead((int)$this->a['ticket']);
        $read = $a->syncState($userId)['revision'];
        self::assertNotSame($second, $read);
        $this->pdo->prepare("UPDATE talk_messages SET delivery_status='failed',delivery_error='temporário' WHERE tenant_id=:tenant AND id=:id")
            ->execute(['tenant'=>$this->a['tenant'],'id'=>$this->a['message']]);
        self::assertNotSame($read, $a->syncState($userId)['revision']);
    }

    public function testClaimWaitsForAttendantLockAndRejectsCapacityOverflow(): void
    {
        $tenant=(int)$this->a['tenant'];
        $user=(int)$this->a['user'];
        $first=(int)$this->a['ticket'];
        $this->pdo->prepare("UPDATE talk_tickets SET status='queued',assigned_user_id=NULL WHERE id=:id")
            ->execute(['id'=>$first]);
        $this->pdo->prepare("INSERT INTO talk_user_settings(tenant_id,user_id,talk_role,max_active_tickets) VALUES(:tenant,:user,'agent',1)")
            ->execute(['tenant'=>$tenant,'user'=>$user]);
        $this->pdo->prepare("INSERT INTO talk_tickets(tenant_id,channel_id,protocol,conversation_id,queue_id,status,source,queued_at) VALUES(:tenant,:channel,:protocol,:conversation,:queue,'queued','whatsapp',NOW())")
            ->execute(['tenant'=>$tenant,'channel'=>$this->a['channel'],'protocol'=>strtoupper($this->prefix.'-second'),'conversation'=>$this->a['conversation'],'queue'=>$this->a['queue']]);
        $second=(int)$this->pdo->lastInsertId();

        $process=null;
        $pipes=[];
        $this->pdo->beginTransaction();
        try {
            $lock=$this->pdo->prepare("SELECT user_id FROM talk_tenant_users WHERE tenant_id=:tenant AND user_id=:user FOR UPDATE");
            $lock->execute(['tenant'=>$tenant,'user'=>$user]);
            self::assertSame($user,(int)$lock->fetchColumn());
            $command=[PHP_BINARY,'-d','variables_order=EGPCS',__DIR__.'/fixtures/talk-claim-child.php',(string)$tenant,(string)$second,(string)$user];
            $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$_ENV);
            self::assertIsResource($process);
            fclose($pipes[0]);
            stream_set_timeout($pipes[1],5);
            self::assertSame("ready\n",fgets($pipes[1]));
            usleep(200000);
            self::assertTrue(proc_get_status($process)['running'],'Claim concorrente deve aguardar o lock do atendente.');

            $this->pdo->prepare("UPDATE talk_tickets SET status='assigned',assigned_user_id=:user WHERE id=:id")
                ->execute(['user'=>$user,'id'=>$first]);
            $this->pdo->prepare("INSERT INTO talk_events(tenant_id,ticket_id,user_id,actor_type,event_type,payload) VALUES(:tenant,:ticket,:user,'user','ticket.claimed','{}')")
                ->execute(['tenant'=>$tenant,'ticket'=>$first,'user'=>$user]);
            $this->pdo->commit();

            self::assertSame('0',trim((string)stream_get_contents($pipes[1])));
            self::assertSame('',trim((string)stream_get_contents($pipes[2])));
            fclose($pipes[1]);fclose($pipes[2]);
            proc_close($process);$process=null;
            $row=$this->pdo->query("SELECT status,assigned_user_id FROM talk_tickets WHERE id={$second}")->fetch(PDO::FETCH_ASSOC);
            self::assertSame('queued',$row['status']);
            self::assertNull($row['assigned_user_id']);
            self::assertSame(1,$this->countWhere('talk_events',$tenant,"event_type='ticket.claimed'"));
        } finally {
            if(is_resource($process)){proc_terminate($process);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($process);}
            if($this->pdo->inTransaction())$this->pdo->rollBack();
        }
    }

    public function testClaimInAnotherTenantDoesNotWaitForAttendantLock(): void
    {
        $this->pdo->prepare("UPDATE talk_tickets SET status='queued',assigned_user_id=NULL WHERE id=:id")
            ->execute(['id'=>$this->b['ticket']]);
        $process=null;
        $pipes=[];
        $this->pdo->beginTransaction();
        try {
            $lock=$this->pdo->prepare("SELECT user_id FROM talk_tenant_users WHERE tenant_id=:tenant AND user_id=:user FOR UPDATE");
            $lock->execute(['tenant'=>$this->a['tenant'],'user'=>$this->a['user']]);
            self::assertNotFalse($lock->fetchColumn());
            $command=[PHP_BINARY,'-d','variables_order=EGPCS',__DIR__.'/fixtures/talk-claim-child.php',(string)$this->b['tenant'],(string)$this->b['ticket'],(string)$this->b['user']];
            $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),$_ENV);
            self::assertIsResource($process);
            fclose($pipes[0]);
            stream_set_timeout($pipes[1],5);
            self::assertSame("ready\n",fgets($pipes[1]));
            self::assertSame("1\n",fgets($pipes[1]));
            self::assertFalse(stream_get_meta_data($pipes[1])['timed_out']);
            self::assertSame('',trim((string)stream_get_contents($pipes[2])));
            fclose($pipes[1]);fclose($pipes[2]);
            proc_close($process);$process=null;
            self::assertSame(1,$this->countWhere('talk_events',(int)$this->b['tenant'],"event_type='ticket.claimed'"));
        } finally {
            if(is_resource($process)){proc_terminate($process);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($process);}
            if($this->pdo->inTransaction())$this->pdo->rollBack();
        }
    }

    public function testManualClaimHonorsQueueMemberCapacity(): void
    {
        $this->pdo->prepare("UPDATE talk_queue_members SET capacity=1 WHERE tenant_id=:tenant AND queue_id=:queue AND user_id=:user")
            ->execute(['tenant'=>$this->a['tenant'],'queue'=>$this->a['queue'],'user'=>$this->a['user']]);
        $this->pdo->prepare("INSERT INTO talk_tickets(tenant_id,channel_id,protocol,conversation_id,queue_id,status,source,queued_at) VALUES(:tenant,:channel,:protocol,:conversation,:queue,'queued','whatsapp',NOW())")
            ->execute(['tenant'=>$this->a['tenant'],'channel'=>$this->a['channel'],'protocol'=>strtoupper($this->prefix.'-capacity'),'conversation'=>$this->a['conversation'],'queue'=>$this->a['queue']]);
        $ticketId=(int)$this->pdo->lastInsertId();
        $service=new TalkService((int)$this->a['tenant']);
        self::assertFalse($service->claim($ticketId,(int)$this->a['user']));
        self::assertSame('queued',$this->pdo->query("SELECT status FROM talk_tickets WHERE id={$ticketId}")->fetchColumn());
        self::assertSame(0,$this->countWhere('talk_events',(int)$this->a['tenant'],"ticket_id={$ticketId}"));
    }

    public function testJackAndOutboundRemainInsideTheSelectedTenant(): void
    {
        $this->pdo->prepare("UPDATE talk_tickets SET status='queued',assigned_user_id=NULL,queued_at=DATE_SUB(NOW(),INTERVAL 5 MINUTE) WHERE id IN (:a,:b)")
            ->execute(['a'=>$this->a['ticket'],'b'=>$this->b['ticket']]);
        self::assertSame(1, (new TalkJackService((int)$this->a['tenant']))->processEligible());
        self::assertSame(1, $this->countTenantRows('talk_jack_interactions', (int)$this->a['tenant']));
        self::assertSame(0, $this->countTenantRows('talk_jack_interactions', (int)$this->b['tenant']));

        $this->pdo->prepare("UPDATE talk_tickets SET status='assigned',assigned_user_id=CASE id WHEN :a THEN :ua ELSE :ub END WHERE id IN (:a2,:b)")
            ->execute(['a'=>$this->a['ticket'],'ua'=>$this->a['user'],'ub'=>$this->b['user'],'a2'=>$this->a['ticket'],'b'=>$this->b['ticket']]);
        $transport = new class implements WhatsAppTransport {
            public int $calls = 0;
            public string $channelKey = '';
            public function sendText(string $channelKey,string $to,string $text,?string $idempotencyKey=null): array { $this->calls++;$this->channelKey=$channelKey;return ['message_id'=>'out-'.$this->calls,'status'=>'sent']; }
            public function sendMedia(string $channelKey,string $to,string $absolutePath,string $mimeType,?string $caption=null): array { return ['message_id'=>'media','status'=>'sent']; }
            public function status(string $channelKey): array { return ['status'=>'connected','connected'=>true,'detail'=>null]; }
            public function connect(string $channelKey,string $externalId): array { return $this->status($channelKey); }
            public function logout(string $channelKey): array { return ['ok'=>true]; }
        };
        $outbound = new TalkOutboundService((int)$this->a['tenant']);
        try {
            $outbound->sendText((int)$this->b['ticket'], (int)$this->a['user'], 'não enviar');
            self::fail('Ticket de outro tenant deveria ser bloqueado.');
        } catch (RuntimeException) {
            self::assertSame(0, $transport->calls);
        }
        self::assertGreaterThan(0, $outbound->sendText((int)$this->a['ticket'], (int)$this->a['user'], 'mensagem autorizada', 'tenant-test-intent-0001'));
        self::assertSame('sent', (new TalkOutboxWorker($transport,(int)$this->a['tenant'],'tenant-worker'))->processNext()['status']);
        self::assertSame(1, $transport->calls);
        self::assertSame($this->prefix.'-a-session', $transport->channelKey);
        self::assertSame(0, $this->countWhere('talk_messages', (int)$this->b['tenant'], "external_id='out-1'"));
    }

    public function testOperationsWorkerRunsJackForEachTenantOnlyOnce(): void
    {
        $this->pdo->prepare("UPDATE talk_tickets SET status='queued',assigned_user_id=NULL,queued_at=DATE_SUB(NOW(),INTERVAL 5 MINUTE) WHERE id IN (:a,:b)")
            ->execute(['a'=>$this->a['ticket'],'b'=>$this->b['ticket']]);
        $this->pdo->prepare("INSERT INTO talk_settings(tenant_id,setting_key,setting_value) VALUES(:tenant,'auto_assign.enabled','0')")
            ->execute(['tenant'=>$this->a['tenant']]);
        $this->pdo->prepare("INSERT INTO talk_settings(tenant_id,setting_key,setting_value) VALUES(:tenant,'auto_assign.enabled','0')")
            ->execute(['tenant'=>$this->b['tenant']]);

        $worker = new TalkOperationsWorker();
        self::assertSame(['assigned'=>0,'jack'=>2], $worker->processDue());
        self::assertSame(['assigned'=>0,'jack'=>0], $worker->processDue());
        self::assertSame(1, $this->countTenantRows('talk_jack_interactions', (int)$this->a['tenant']));
        self::assertSame(1, $this->countTenantRows('talk_jack_interactions', (int)$this->b['tenant']));
    }

    public function testInboundResolvesTenantFromGloballyUniqueChannel(): void
    {
        $service = new TalkInboundService();
        $externalMessage = $this->prefix.'-same-message';
        $ticketA = $service->receiveWhatsApp($this->inboundPayload($this->a, $externalMessage, '5511999990001'));
        $ticketB = $service->receiveWhatsApp($this->inboundPayload($this->b, $externalMessage, '5511999990002'));

        self::assertGreaterThan(0, $ticketA);
        self::assertGreaterThan(0, $ticketB);
        self::assertNotSame($ticketA, $ticketB);
        self::assertSame((int)$this->a['tenant'], $this->tenantOf('talk_tickets', $ticketA));
        self::assertSame((int)$this->b['tenant'], $this->tenantOf('talk_tickets', $ticketB));
        self::assertSame(1, $this->countWhere('talk_messages', (int)$this->a['tenant'], 'external_id='.$this->pdo->quote($externalMessage)));
        self::assertSame(1, $this->countWhere('talk_messages', (int)$this->b['tenant'], 'external_id='.$this->pdo->quote($externalMessage)));
    }

    public function testMultipleChannelsKeepQueueInboundBridgeAndManagementIsolated(): void
    {
        $external = $this->prefix.'-a-finance';
        $session = $this->prefix.'-a-finance-session';
        $insert = $this->pdo->prepare("INSERT INTO talk_channels(tenant_id,type,name,external_id,driver,status,connection_status,session_key,default_queue_id) VALUES(:tenant,'whatsapp','Financeiro',:external,'baileys','active','connected',:session,:queue)");
        $insert->execute(['tenant'=>$this->a['tenant'],'external'=>$external,'session'=>$session,'queue'=>$this->a['queue']]);
        $channel2 = (int)$this->pdo->lastInsertId();

        $phone = '5511987654321';
        $inbound = new TalkInboundService();
        $ticket1 = $inbound->receiveWhatsApp($this->inboundPayload($this->a,$this->prefix.'-multi-1',$phone));
        $ticket2 = $inbound->receiveWhatsApp(['external_id'=>$this->prefix.'-multi-2','channel_external_id'=>$external,'from'=>$phone,'from_jid'=>$phone.'@s.whatsapp.net','push_name'=>'Mesmo morador','body'=>'Financeiro','timestamp'=>time()]);
        self::assertNotSame($ticket1,$ticket2);
        $query=$this->pdo->prepare('SELECT channel_id,queue_id,conversation_id FROM talk_tickets WHERE id IN (?,?) ORDER BY id');$query->execute([$ticket1,$ticket2]);$tickets=$query->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(2,$tickets);
        self::assertSame([(int)$this->a['channel'],$channel2],array_map('intval',array_column($tickets,'channel_id')));
        self::assertSame([(int)$this->a['queue'],(int)$this->a['queue']],array_map('intval',array_column($tickets,'queue_id')));
        self::assertCount(2,array_unique(array_column($tickets,'conversation_id')));

        $transport = new class implements WhatsAppTransport {
            public array $keys=[];
            public function sendText(string $channelKey,string $to,string $text,?string $idempotencyKey=null):array{return ['message_id'=>'x','status'=>'sent'];}
            public function sendMedia(string $channelKey,string $to,string $absolutePath,string $mimeType,?string $caption=null):array{return ['message_id'=>'x','status'=>'sent'];}
            public function status(string $channelKey):array{$this->keys[]=$channelKey;return ['status'=>'connected','connected'=>true,'profile'=>['id'=>'5511000000000@s.whatsapp.net','name'=>'Conta real']];}
            public function connect(string $channelKey,string $externalId):array{$this->keys[]=$channelKey;return ['status'=>'qr','connected'=>false,'qr'=>'data:test'];}
            public function logout(string $channelKey):array{$this->keys[]=$channelKey;return ['ok'=>true,'status'=>'disconnected'];}
        };
        $service = new TalkChannelService($transport,(int)$this->a['tenant']);
        self::assertSame('connected',$service->status($channel2)['status']);
        self::assertSame($session,$transport->keys[0]);
        self::assertNull($service->find((int)$this->b['channel']));
        try{$service->status((int)$this->b['channel']);self::fail('Canal estrangeiro deveria ser bloqueado.');}catch(RuntimeException){self::assertCount(1,$transport->keys);}
        try{$service->save(['name'=>'Inválido','default_queue_id'=>$this->b['queue']],(int)$this->a['user']);self::fail('Fila estrangeira deveria ser bloqueada.');}catch(RuntimeException $e){self::assertStringContainsString('Fila padrão inválida',$e->getMessage());}
    }

    public function testCompositeForeignKeysRejectCrossTenantAssociations(): void
    {
        $statement = $this->pdo->prepare("INSERT INTO talk_conversations(tenant_id,channel_id,contact_id,channel,external_id,status) VALUES(:tenant_id,:channel_id,:contact_id,'whatsapp',:external_id,'open')");
        $this->assertConstraintViolation($statement, [
            'tenant_id'=>$this->a['tenant'],
            'channel_id'=>$this->a['channel'],
            'contact_id'=>$this->b['contact'],
            'external_id'=>$this->prefix.'-invalid-cross-tenant',
        ]);

        $statement = $this->pdo->prepare("INSERT INTO talk_tickets(tenant_id,channel_id,protocol,conversation_id,status,source) VALUES(:tenant_id,:channel_id,:protocol,:conversation_id,'queued','whatsapp')");
        $this->assertConstraintViolation($statement, [
            'tenant_id'=>$this->a['tenant'],
            'channel_id'=>$this->b['channel'],
            'protocol'=>strtoupper($this->prefix).'-INVALID',
            'conversation_id'=>$this->a['conversation'],
        ]);

        $statement=$this->pdo->prepare("UPDATE talk_tickets SET assigned_user_id=:foreign_user WHERE tenant_id=:tenant_id AND id=:id");
        $this->assertConstraintViolation($statement,['foreign_user'=>$this->b['user'],'tenant_id'=>$this->a['tenant'],'id'=>$this->a['ticket']]);

        $constraints=$this->pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME LIKE '%_tenant_fk'")->fetchAll(PDO::FETCH_COLUMN);
        foreach (['talk_contacts_channel_tenant_fk','talk_conversations_contact_tenant_fk','talk_tickets_conversation_tenant_fk','talk_tickets_channel_tenant_fk','talk_tickets_assignee_tenant_fk','talk_messages_ticket_tenant_fk','talk_ticket_tags_tag_tenant_fk','talk_attachments_message_tenant_fk','talk_notifications_user_tenant_fk','talk_channels_default_queue_tenant_fk'] as $required) {
            self::assertContains($required,$constraints);
        }
        $channelIndex=$this->pdo->query("SHOW INDEX FROM talk_channels WHERE Key_name='talk_channels_external_id'")->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($channelIndex);
        self::assertSame(0,(int)$channelIndex['Non_unique']);
        $sessionIndex=$this->pdo->query("SHOW INDEX FROM talk_channels WHERE Key_name='talk_channels_session_key'")->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($sessionIndex);
        self::assertSame(0,(int)$sessionIndex['Non_unique']);
    }

    public function testOutboundPersistsMessageAndOutboxAtomicallyWithSafeIdempotency():void
    {
        $service=new TalkOutboundService((int)$this->a['tenant']);
        $key='outbox-atomic-intent-0001';
        $first=$service->sendText((int)$this->a['ticket'],(int)$this->a['user'],'mesma mensagem',$key);
        self::assertSame($first,$service->sendText((int)$this->a['ticket'],(int)$this->a['user'],'mesma mensagem',$key));
        self::assertSame(1,$this->countWhere('talk_outbox',(int)$this->a['tenant'],"idempotency_key='outbox-atomic-intent-0001'"));
        self::assertSame('pending',(string)$this->pdo->query('SELECT delivery_status FROM talk_messages WHERE id='.(int)$first)->fetchColumn());
        $second=$service->sendText((int)$this->a['ticket'],(int)$this->a['user'],'mesma mensagem','outbox-atomic-intent-0002');
        self::assertNotSame($first,$second);

        $trigger='test_outbox_atomic_'.bin2hex(random_bytes(3));
        $this->pdo->exec("CREATE TRIGGER {$trigger} BEFORE INSERT ON talk_outbox FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='atomic test'");
        try{
            $before=$this->countTenantRows('talk_messages',(int)$this->a['tenant']);
            try{$service->sendText((int)$this->a['ticket'],(int)$this->a['user'],'deve reverter','outbox-atomic-intent-0003');self::fail('A outbox deveria falhar.');}catch(PDOException){self::assertSame($before,$this->countTenantRows('talk_messages',(int)$this->a['tenant']));}
        }finally{$this->pdo->exec("DROP TRIGGER IF EXISTS {$trigger}");}
    }

    public function testWorkerUsesExactTenantChannelAndPersistsExternalId():void
    {
        $message=(new TalkOutboundService((int)$this->a['tenant']))->sendText((int)$this->a['ticket'],(int)$this->a['user'],'enviar','outbox-worker-intent-0001');
        (new TalkOutboundService((int)$this->b['tenant']))->sendText((int)$this->b['ticket'],(int)$this->b['user'],'não misturar','outbox-worker-intent-0002');
        $transport=new OutboxTestTransport();
        $result=(new TalkOutboxWorker($transport,(int)$this->a['tenant'],'worker-a'))->processNext();
        self::assertSame('sent',$result['status']);
        self::assertSame(1,$transport->calls);
        self::assertSame($this->prefix.'-a-session',$transport->channelKey);
        $query=$this->pdo->query('SELECT delivery_status,external_id FROM talk_messages WHERE id='.(int)$message)->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['delivery_status'=>'sent','external_id'=>'out-1'],$query);
        self::assertSame(1,$this->countWhere('talk_outbox',(int)$this->b['tenant'],"status='pending'"));
    }

    public function testOfflineRetryBackoffMaxAttemptsAndManualRequeue():void
    {
        $message=(new TalkOutboundService((int)$this->a['tenant']))->sendText((int)$this->a['ticket'],(int)$this->a['user'],'recuperar','outbox-retry-intent-0001');
        $transport=new OutboxTestTransport();$transport->offline=true;
        $worker=new TalkOutboxWorker($transport,(int)$this->a['tenant'],'retry-worker',1,2);
        $first=$worker->processNext();self::assertSame('pending',$first['status']);self::assertSame(1,$first['attempts']);
        $row=$this->pdo->query('SELECT id,status,attempts,available_at,last_error FROM talk_outbox WHERE message_id='.(int)$message)->fetch(PDO::FETCH_ASSOC);
        self::assertSame('pending',$row['status']);self::assertGreaterThan(time(),strtotime((string)$row['available_at']));self::assertStringNotContainsString('token',(string)$row['last_error']);
        $this->pdo->exec('UPDATE talk_outbox SET attempts=max_attempts-1,available_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id='.(int)$row['id']);
        self::assertSame('failed',$worker->processNext()['status']);
        self::assertSame('failed',(string)$this->pdo->query('SELECT delivery_status FROM talk_messages WHERE id='.(int)$message)->fetchColumn());
        $worker->requeue((int)$row['id'],(int)$this->a['user'],(int)$this->a['tenant']);
        self::assertSame('pending',(string)$this->pdo->query('SELECT status FROM talk_outbox WHERE id='.(int)$row['id'])->fetchColumn());
        $transport->offline=false;
        self::assertSame('sent',$worker->processNext()['status']);
    }

    public function testExpiredLockRecoversAndTwoWorkersDoNotSendSameItem():void
    {
        (new TalkOutboundService((int)$this->a['tenant']))->sendText((int)$this->a['ticket'],(int)$this->a['user'],'lock','outbox-lock-intent-0001');
        $this->pdo->exec("UPDATE talk_outbox SET status='processing',attempts=1,locked_by='dead-worker',locked_at=DATE_SUB(NOW(),INTERVAL 10 SECOND) WHERE tenant_id=".(int)$this->a['tenant']." AND status='pending'");
        $transport=new OutboxTestTransport();
        $secondResult='not-called';
        $transport->duringSend=function()use(&$secondResult):void{$secondResult=(new TalkOutboxWorker(new OutboxTestTransport(),(int)$this->a['tenant'],'worker-b',1,1))->processNext();};
        $result=(new TalkOutboxWorker($transport,(int)$this->a['tenant'],'worker-a',1,1))->processNext();
        self::assertSame('sent',$result['status']);
        self::assertNull($secondResult);
        self::assertSame(1,$transport->calls);
        self::assertSame(2,(int)$this->pdo->query("SELECT attempts FROM talk_outbox WHERE tenant_id=".(int)$this->a['tenant']." AND idempotency_key='outbox-lock-intent-0001'")->fetchColumn());
    }

    public function testOutboxCompositeConstraintsRejectCrossTenantAssociations():void
    {
        $message=(new TalkOutboundService((int)$this->a['tenant']))->sendText((int)$this->a['ticket'],(int)$this->a['user'],'fk','outbox-fk-intent-0001');
        $statement=$this->pdo->prepare("INSERT INTO talk_outbox(tenant_id,channel_id,ticket_id,message_id,status,available_at,idempotency_key,payload) VALUES(:tenant,:channel,:ticket,:message,'pending',NOW(),'outbox-fk-cross-0001',JSON_OBJECT())");
        $this->assertConstraintViolation($statement,['tenant'=>$this->b['tenant'],'channel'=>$this->b['channel'],'ticket'=>$this->b['ticket'],'message'=>$message]);
        $constraints=array_column($this->pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='talk_outbox'")->fetchAll(PDO::FETCH_ASSOC),'CONSTRAINT_NAME');
        foreach(['talk_outbox_channel_tenant_fk','talk_outbox_ticket_tenant_fk','talk_outbox_message_tenant_fk'] as $required)self::assertContains($required,$constraints);
    }

    public function testSyncRevisionDetectsDeliveryChangesAndEndpointDoesNotRunJobs():void
    {
        $service=new TalkService((int)$this->a['tenant']);
        $before=$service->syncState((int)$this->a['user']);
        $this->pdo->prepare("UPDATE talk_messages SET delivery_status='failed',delivery_error='temporário',delivery_updated_at=NOW() WHERE tenant_id=:tenant AND id=:id")->execute(['tenant'=>$this->a['tenant'],'id'=>$this->a['message']]);
        $after=$service->syncState((int)$this->a['user']);
        self::assertNotSame($before['revision'],$after['revision']);
        self::assertIsString($after['revision']);
        self::assertSame(64,strlen($after['revision']));
        $controller=(string)file_get_contents(dirname(__DIR__).'/app/Controllers/TalkController.php');
        preg_match('/public function sync\(\):never\{(.+?)\}\npublic function conversations/s',$controller,$match);
        self::assertArrayHasKey(1,$match);
        self::assertStringNotContainsString('autoAssign',$match[1]);
        self::assertStringNotContainsString('processEligible',$match[1]);
    }

    /** @return array<string,int|string> */
    private function fixture(string $suffix): array
    {
        $pdo=$this->pdo;$slug=$this->prefix.'-'.$suffix;
        $s=$pdo->prepare("INSERT INTO users(name,email,password,status,role) VALUES(:name,:email,'test-only','active','admin')");$s->execute(['name'=>'Tenant '.strtoupper($suffix),'email'=>$slug.'@example.test']);$user=(int)$pdo->lastInsertId();
        $s=$pdo->prepare("INSERT INTO talk_tenants(name,slug,status) VALUES(:name,:slug,'active')");$s->execute(['name'=>'Empresa '.strtoupper($suffix),'slug'=>$slug]);$tenant=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO talk_tenant_users(tenant_id,user_id,role,status,is_default) VALUES(:tenant,:user,'admin','active',1)")->execute(['tenant'=>$tenant,'user'=>$user]);
        $s=$pdo->prepare("INSERT INTO talk_channels(tenant_id,type,name,external_id,driver,status,connection_status,session_key) VALUES(:tenant,'whatsapp',:name,:external,'baileys','active','connected',:session_key)");$s->execute(['tenant'=>$tenant,'name'=>'WhatsApp '.$suffix,'external'=>$slug.'-channel','session_key'=>$slug.'-session']);$channel=(int)$pdo->lastInsertId();
        $s=$pdo->prepare("INSERT INTO talk_departments(tenant_id,name,slug,status) VALUES(:tenant,:name,:slug,'active')");$s->execute(['tenant'=>$tenant,'name'=>'Departamento '.$suffix,'slug'=>$slug.'-department']);$department=(int)$pdo->lastInsertId();
        $s=$pdo->prepare("INSERT INTO talk_queues(tenant_id,department_id,name,slug,status,auto_assign_after_seconds) VALUES(:tenant,:department,:name,:slug,'active',5)");$s->execute(['tenant'=>$tenant,'department'=>$department,'name'=>'Fila '.$suffix,'slug'=>$slug.'-queue']);$queue=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO talk_queue_members(tenant_id,queue_id,user_id,role,capacity,status) VALUES(:tenant,:queue,:user,'supervisor',10,'active')")->execute(['tenant'=>$tenant,'queue'=>$queue,'user'=>$user]);
        $s=$pdo->prepare("INSERT INTO talk_contacts(tenant_id,channel_id,name,phone,external_id,channel) VALUES(:tenant,:channel,:name,:phone,:external,'whatsapp')");$s->execute(['tenant'=>$tenant,'channel'=>$channel,'name'=>$this->prefix.' contact '.$suffix,'phone'=>'55119000000'.($suffix==='a'?'1':'2'),'external'=>'shared-contact@s.whatsapp.net']);$contact=(int)$pdo->lastInsertId();
        $s=$pdo->prepare("INSERT INTO talk_conversations(tenant_id,channel_id,contact_id,channel,external_id,status,last_message_at) VALUES(:tenant,:channel,:contact,'whatsapp',:external,'open',NOW())");$s->execute(['tenant'=>$tenant,'channel'=>$channel,'contact'=>$contact,'external'=>'shared-conversation']);$conversation=(int)$pdo->lastInsertId();
        $s=$pdo->prepare("INSERT INTO talk_tickets(tenant_id,channel_id,protocol,conversation_id,queue_id,assigned_user_id,status,priority,subject,source,queued_at,last_activity_at) VALUES(:tenant,:channel,:protocol,:conversation,:queue,:user,'assigned','normal',:subject,'whatsapp',DATE_SUB(NOW(),INTERVAL 5 MINUTE),NOW())");$s->execute(['tenant'=>$tenant,'channel'=>$channel,'protocol'=>strtoupper($slug), 'conversation'=>$conversation,'queue'=>$queue,'user'=>$user,'subject'=>$this->prefix.' subject '.$suffix]);$ticket=(int)$pdo->lastInsertId();
        $s=$pdo->prepare("INSERT INTO talk_messages(tenant_id,channel_id,conversation_id,ticket_id,sender_type,external_id,direction,type,body,sent_at) VALUES(:tenant,:channel,:conversation,:ticket,'contact',:external,'inbound','text',:body,NOW())");$s->execute(['tenant'=>$tenant,'channel'=>$channel,'conversation'=>$conversation,'ticket'=>$ticket,'external'=>'shared-message-id','body'=>'mensagem '.$suffix]);$message=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO talk_settings(tenant_id,setting_key,setting_value,updated_by) VALUES(:tenant,'isolation.key',:value,:user),(:tenant2,'jack.enabled','1',:user2),(:tenant3,'jack.wait_seconds','0',:user3)")->execute(['tenant'=>$tenant,'value'=>'value-'.$suffix,'user'=>$user,'tenant2'=>$tenant,'user2'=>$user,'tenant3'=>$tenant,'user3'=>$user]);
        $s=$pdo->prepare("INSERT INTO talk_tags(tenant_id,name,slug) VALUES(:tenant,:name,'shared-tag')");$s->execute(['tenant'=>$tenant,'name'=>'Tag '.$suffix]);$tag=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO talk_ticket_tags(tenant_id,ticket_id,tag_id) VALUES(:tenant,:ticket,:tag)")->execute(['tenant'=>$tenant,'ticket'=>$ticket,'tag'=>$tag]);
        $s=$pdo->prepare("INSERT INTO talk_notifications(tenant_id,recipient_id,ticket_id,type,title) VALUES(:tenant,:user,:ticket,'test','Tenant test')");$s->execute(['tenant'=>$tenant,'user'=>$user,'ticket'=>$ticket]);
        $s=$pdo->prepare("INSERT INTO talk_attachments(tenant_id,ticket_id,message_id,uploaded_by,original_name,stored_name,mime_type,file_size,size_bytes,storage_path) VALUES(:tenant,:ticket,:message,:user,'test.txt',:stored,'text/plain',4,4,:path)");$s->execute(['tenant'=>$tenant,'ticket'=>$ticket,'message'=>$message,'user'=>$user,'stored'=>$slug.'.txt','path'=>'storage/talk/'.$tenant.'/'.$slug.'.txt']);$attachment=(int)$pdo->lastInsertId();
        return compact('tenant','user','channel','department','queue','contact','conversation','ticket','message','tag','attachment')+['channel_external'=>$slug.'-channel'];
    }

    /** @param array<string,int|string> $fixture */
    private function inboundPayload(array $fixture,string $externalId,string $phone): array
    {
        return ['external_id'=>$externalId,'channel_external_id'=>$fixture['channel_external'],'from'=>$phone,'from_jid'=>$phone.'@s.whatsapp.net','push_name'=>$this->prefix.' inbound','type'=>'conversation','body'=>'tenant inbound','timestamp'=>time()];
    }

    private function countTenantRows(string $table,int $tenant): int { return $this->countWhere($table,$tenant,'1=1'); }
    private function countWhere(string $table,int $tenant,string $where): int { $s=$this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE tenant_id=:tenant_id AND {$where}");$s->execute(['tenant_id'=>$tenant]);return (int)$s->fetchColumn(); }
    private function tenantOf(string $table,int $id): int { $s=$this->pdo->prepare("SELECT tenant_id FROM {$table} WHERE id=:id");$s->execute(['id'=>$id]);return (int)$s->fetchColumn(); }
    /** @param array<string,int|string> $params */
    private function assertConstraintViolation(PDOStatement $statement,array $params): void
    {
        try { $statement->execute($params);self::fail('A associação cruzada deveria ser rejeitada pelo banco.'); }
        catch (PDOException $exception) { self::assertSame('23000',(string)$exception->getCode()); }
    }
}
