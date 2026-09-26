<?php

declare(strict_types=1);

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Services\Talk\TalkAttachmentService;
use Moves\Services\Talk\TalkInboundService;
use Moves\Services\Talk\TalkJackService;
use Moves\Services\Talk\TalkMetadataService;
use Moves\Services\Talk\TalkNotificationService;
use Moves\Services\Talk\TalkOutboundService;
use Moves\Services\Talk\TalkService;
use Moves\Services\Talk\TalkTenantContext;
use Moves\Services\Talk\Transport\WhatsAppTransport;
use PHPUnit\Framework\TestCase;

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
                foreach (['talk_notifications','talk_attachments','talk_ticket_tags','talk_jack_interactions','talk_events','talk_notes','talk_transfers','talk_messages','talk_tickets','talk_conversations','talk_contacts','talk_queue_members','talk_queues','talk_departments','talk_settings','talk_presence','talk_user_settings','talk_channels','talk_tenant_users'] as $table) {
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
            public function sendText(string $to,string $text): array { $this->calls++;return ['message_id'=>'out-'.$this->calls,'status'=>'sent']; }
            public function sendMedia(string $to,string $absolutePath,string $mimeType,?string $caption=null): array { return ['message_id'=>'media','status'=>'sent']; }
            public function status(): array { return ['status'=>'connected','connected'=>true,'detail'=>null]; }
        };
        $outbound = new TalkOutboundService($transport, (int)$this->a['tenant']);
        try {
            $outbound->sendText((int)$this->b['ticket'], (int)$this->a['user'], 'não enviar');
            self::fail('Ticket de outro tenant deveria ser bloqueado.');
        } catch (RuntimeException) {
            self::assertSame(0, $transport->calls);
        }
        self::assertGreaterThan(0, $outbound->sendText((int)$this->a['ticket'], (int)$this->a['user'], 'mensagem autorizada'));
        self::assertSame(1, $transport->calls);
        self::assertSame(0, $this->countWhere('talk_messages', (int)$this->b['tenant'], "external_id='out-1'"));
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
        foreach (['talk_contacts_channel_tenant_fk','talk_conversations_contact_tenant_fk','talk_tickets_conversation_tenant_fk','talk_tickets_channel_tenant_fk','talk_tickets_assignee_tenant_fk','talk_messages_ticket_tenant_fk','talk_ticket_tags_tag_tenant_fk','talk_attachments_message_tenant_fk','talk_notifications_user_tenant_fk'] as $required) {
            self::assertContains($required,$constraints);
        }
        $channelIndex=$this->pdo->query("SHOW INDEX FROM talk_channels WHERE Key_name='talk_channels_external_id'")->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($channelIndex);
        self::assertSame(0,(int)$channelIndex['Non_unique']);
    }

    /** @return array<string,int|string> */
    private function fixture(string $suffix): array
    {
        $pdo=$this->pdo;$slug=$this->prefix.'-'.$suffix;
        $s=$pdo->prepare("INSERT INTO users(name,email,password,status,role) VALUES(:name,:email,'test-only','active','admin')");$s->execute(['name'=>'Tenant '.strtoupper($suffix),'email'=>$slug.'@example.test']);$user=(int)$pdo->lastInsertId();
        $s=$pdo->prepare("INSERT INTO talk_tenants(name,slug,status) VALUES(:name,:slug,'active')");$s->execute(['name'=>'Empresa '.strtoupper($suffix),'slug'=>$slug]);$tenant=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO talk_tenant_users(tenant_id,user_id,role,status,is_default) VALUES(:tenant,:user,'admin','active',1)")->execute(['tenant'=>$tenant,'user'=>$user]);
        $s=$pdo->prepare("INSERT INTO talk_channels(tenant_id,type,name,external_id,driver,status,connection_status) VALUES(:tenant,'whatsapp',:name,:external,'baileys','active','connected')");$s->execute(['tenant'=>$tenant,'name'=>'WhatsApp '.$suffix,'external'=>$slug.'-channel']);$channel=(int)$pdo->lastInsertId();
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
