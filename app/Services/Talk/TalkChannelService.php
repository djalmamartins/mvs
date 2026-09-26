<?php

declare(strict_types=1);

namespace Moves\Services\Talk;

use Moves\Boot\Connection;
use Moves\Services\Talk\Transport\WhatsAppTransport;
use Moves\Services\Talk\Transport\WhatsAppTransportFactory;
use PDO;
use RuntimeException;

final class TalkChannelService
{
    public function __construct(
        private ?WhatsAppTransport $transport = null,
        private ?int $tenantId = null,
    ) {
        $this->transport ??= WhatsAppTransportFactory::make();
    }

    private function tenantId(): int { return $this->tenantId ??= (new TalkTenantContext())->currentTenantId(); }

    /** @return list<array<string,mixed>> */
    public function channels(): array
    {
        $statement = Connection::getInstance()->prepare("SELECT ch.id,ch.type,ch.name,ch.display_name,ch.external_id,ch.driver,ch.phone_number,ch.connected_jid,ch.status,ch.connection_status,ch.session_key,ch.default_queue_id,ch.last_connected_at,ch.last_disconnected_at,ch.last_error,ch.last_error_at,ch.metadata,q.name default_queue_name FROM talk_channels ch LEFT JOIN talk_queues q ON q.tenant_id=ch.tenant_id AND q.id=ch.default_queue_id WHERE ch.tenant_id=:tenant_id ORDER BY ch.name,ch.id");
        $statement->execute(['tenant_id'=>$this->tenantId()]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array{id:int,name:string}> */
    public function queues(): array
    {
        $statement = Connection::getInstance()->prepare("SELECT id,name FROM talk_queues WHERE tenant_id=:tenant_id AND status='active' ORDER BY name");
        $statement->execute(['tenant_id'=>$this->tenantId()]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string,mixed> $values */
    public function save(array $values, int $userId): int
    {
        $this->assertManager($userId);
        $id = max(0, (int)($values['id'] ?? 0));
        $name = mb_substr(trim((string)($values['name'] ?? '')), 0, 120);
        if ($name === '') throw new RuntimeException('Informe o nome do canal.');
        $status = (string)($values['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $queueId = max(0, (int)($values['default_queue_id'] ?? 0));
        $pdo = Connection::getInstance();
        if ($queueId > 0) {
            $queue = $pdo->prepare("SELECT COUNT(*) FROM talk_queues WHERE tenant_id=:tenant_id AND id=:id AND status='active'");
            $queue->execute(['tenant_id'=>$this->tenantId(),'id'=>$queueId]);
            if ((int)$queue->fetchColumn() !== 1) throw new RuntimeException('Fila padrão inválida para esta empresa.');
        }
        if ($id > 0) {
            $statement = $pdo->prepare('UPDATE talk_channels SET name=:name,display_name=:display_name,default_queue_id=:queue_id,status=:status,updated_at=NOW() WHERE tenant_id=:tenant_id AND id=:id');
            $statement->execute(['tenant_id'=>$this->tenantId(),'id'=>$id,'name'=>$name,'display_name'=>mb_substr(trim((string)($values['display_name'] ?? '')),0,160) ?: null,'queue_id'=>$queueId ?: null,'status'=>$status]);
            if ($statement->rowCount() < 1 && !$this->find($id)) throw new RuntimeException('Canal não encontrado.');
            return $id;
        }
        $externalId = 'wa-'.bin2hex(random_bytes(16));
        $sessionKey = 'ch-'.bin2hex(random_bytes(20));
        $statement = $pdo->prepare("INSERT INTO talk_channels(tenant_id,type,name,display_name,external_id,driver,status,connection_status,session_key,default_queue_id,config,metadata) VALUES(:tenant_id,'whatsapp',:name,:display_name,:external_id,'baileys',:status,'disconnected',:session_key,:queue_id,JSON_OBJECT(),JSON_OBJECT())");
        $statement->execute(['tenant_id'=>$this->tenantId(),'name'=>$name,'display_name'=>mb_substr(trim((string)($values['display_name'] ?? '')),0,160) ?: null,'external_id'=>$externalId,'status'=>$status,'session_key'=>$sessionKey,'queue_id'=>$queueId ?: null]);
        return (int)$pdo->lastInsertId();
    }

    /** @return array<string,mixed> */
    public function status(int $id): array
    {
        $channel = $this->requireChannel($id);
        $status = $this->transport->status((string)$channel['session_key']);
        $this->persistBridgeState($id, $status);
        return $status + ['channel_id'=>$id];
    }

    /** @return array<string,mixed> */
    public function connect(int $id, int $userId): array
    {
        $this->assertManager($userId);
        $channel = $this->requireChannel($id);
        if ((string)$channel['status'] !== 'active') throw new RuntimeException('Ative o canal antes de conectar.');
        $result = $this->transport->connect((string)$channel['session_key'], (string)$channel['external_id']);
        $this->persistBridgeState($id, $result);
        return $result + ['channel_id'=>$id];
    }

    /** @return array<string,mixed> */
    public function logout(int $id, int $userId): array
    {
        $this->assertManager($userId);
        $channel = $this->requireChannel($id);
        $result = $this->transport->logout((string)$channel['session_key']);
        Connection::getInstance()->prepare("UPDATE talk_channels SET connection_status='disconnected',last_disconnected_at=NOW(),connected_jid=NULL,updated_at=NOW() WHERE tenant_id=:tenant_id AND id=:id")
            ->execute(['tenant_id'=>$this->tenantId(),'id'=>$id]);
        return $result + ['channel_id'=>$id];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement = Connection::getInstance()->prepare('SELECT * FROM talk_channels WHERE tenant_id=:tenant_id AND id=:id LIMIT 1');
        $statement->execute(['tenant_id'=>$this->tenantId(),'id'=>$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed> */
    private function requireChannel(int $id): array
    {
        $channel = $this->find($id);
        if (!$channel || (string)$channel['type'] !== 'whatsapp') throw new RuntimeException('Canal WhatsApp não encontrado.');
        return $channel;
    }

    /** @param array<string,mixed> $status */
    private function persistBridgeState(int $id, array $status): void
    {
        $state = in_array((string)($status['status'] ?? ''), ['starting','qr','connected','reconnecting','disconnected','error'], true) ? (string)$status['status'] : 'disconnected';
        $profile = is_array($status['profile'] ?? null) ? $status['profile'] : [];
        $jid = mb_substr(trim((string)($profile['id'] ?? '')),0,190);
        $number = preg_replace('/\D+/', '', explode('@', $jid)[0]) ?: null;
        $name = mb_substr(trim((string)($profile['name'] ?? '')),0,160) ?: null;
        $error = $state === 'error' ? mb_substr(trim((string)($status['detail'] ?? 'Erro na sessão')),0,500) : null;
        $sql = "UPDATE talk_channels SET last_connected_at=IF(:state2='connected' AND connection_status<>'connected',NOW(),last_connected_at),last_disconnected_at=IF(:state3='disconnected' AND connection_status<>'disconnected',NOW(),last_disconnected_at),last_error_at=IF(:state4='error' AND connection_status<>'error',NOW(),last_error_at),connection_status=:state,phone_number=COALESCE(:phone,phone_number),connected_jid=COALESCE(:jid,connected_jid),display_name=COALESCE(:display_name,display_name),last_error=:last_error,metadata=JSON_SET(COALESCE(metadata,JSON_OBJECT()),'$.bridge_updated_at',NOW()),updated_at=NOW() WHERE tenant_id=:tenant_id AND id=:id";
        Connection::getInstance()->prepare($sql)->execute(['state'=>$state,'phone'=>$number,'jid'=>$jid ?: null,'display_name'=>$name,'state2'=>$state,'state3'=>$state,'last_error'=>$error,'state4'=>$state,'tenant_id'=>$this->tenantId(),'id'=>$id]);
    }

    private function assertManager(int $userId): void
    {
        $statement = Connection::getInstance()->prepare("SELECT COUNT(*) FROM talk_tenant_users WHERE tenant_id=:tenant_id AND user_id=:user_id AND status='active' AND role IN ('admin','manager','supervisor')");
        $statement->execute(['tenant_id'=>$this->tenantId(),'user_id'=>$userId]);
        if ((int)$statement->fetchColumn() !== 1) throw new RuntimeException('Somente gestores podem administrar canais.');
    }
}
