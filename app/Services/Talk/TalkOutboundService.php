<?php

declare(strict_types=1);

namespace Moves\Services\Talk;

use Moves\Boot\Connection;
use Moves\Services\Talk\Transport\WhatsAppTransport;
use Moves\Services\Talk\Transport\WhatsAppTransportFactory;
use PDO;
use RuntimeException;

/**
 * Sends outbound Talk messages without coupling the domain to a provider.
 * Simulation remains local; WhatsApp delivery is delegated to the configured transport.
 */
final class TalkOutboundService
{
    public function __construct(private ?WhatsAppTransport $whatsApp = null)
    {
        $this->whatsApp ??= WhatsAppTransportFactory::make();
    }

    public function sendText(int $ticketId, int $userId, string $body): int
    {
        $body = mb_substr(trim($body), 0, 4000);
        if ($body === '') {
            throw new RuntimeException('Digite uma mensagem antes de enviar.');
        }

        $pdo = Connection::getInstance();
        $statement = $pdo->prepare("SELECT t.id,t.conversation_id,t.assigned_user_id,t.status,t.source,cv.channel,c.phone,c.external_id contact_external_id FROM talk_tickets t INNER JOIN talk_conversations cv ON cv.id=t.conversation_id INNER JOIN talk_contacts c ON c.id=cv.contact_id WHERE t.id=:id LIMIT 1");
        $statement->execute(['id' => $ticketId]);
        $ticket = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$ticket || (int)($ticket['assigned_user_id'] ?? 0) !== $userId || !in_array((string)$ticket['status'], ['assigned', 'open'], true)) {
            throw new RuntimeException('Atendimento indisponível para envio.');
        }

        $channel = (string)($ticket['channel'] ?: $ticket['source']);
        $externalId = null;
        $deliveryStatus = 'sent';
        $metadata = ['channel' => $channel, 'delivery_status' => $deliveryStatus];

        if ($channel === 'whatsapp') {
            $recipient = trim((string)($ticket['phone'] ?: $ticket['contact_external_id']));
            if ($recipient === '') {
                throw new RuntimeException('Contato sem número de WhatsApp válido.');
            }
            $result = $this->whatsApp->sendText($recipient, $body);
            $externalId = trim((string)$result['message_id']);
            $deliveryStatus = trim((string)$result['status']) ?: 'sent';
            $metadata['delivery_status'] = $deliveryStatus;
            $metadata['transport'] = 'whatsapp';
        } elseif ($channel !== 'simulation') {
            throw new RuntimeException('Canal de saída ainda não suportado: '.$channel.'.');
        } else {
            $metadata['simulation'] = true;
        }

        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare("INSERT INTO talk_messages(conversation_id,ticket_id,sender_type,sender_user_id,external_id,direction,type,body,metadata,sent_at) VALUES(:conversation_id,:ticket_id,'user',:user_id,:external_id,'outbound','text',:body,:metadata,NOW())");
            $insert->execute([
                'conversation_id' => (int)$ticket['conversation_id'],
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'external_id' => $externalId !== '' ? $externalId : null,
                'body' => $body,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]);
            $messageId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE talk_tickets SET first_response_at=COALESCE(first_response_at,NOW()),last_activity_at=NOW(),updated_at=NOW() WHERE id=:id")->execute(['id' => $ticketId]);
            $pdo->prepare("UPDATE talk_conversations SET last_message_at=NOW(),updated_at=NOW() WHERE id=:id")->execute(['id' => (int)$ticket['conversation_id']]);
            $pdo->prepare("INSERT INTO talk_events(ticket_id,user_id,actor_type,event_type,payload) VALUES(:ticket_id,:user_id,'user','message.sent',:payload)")->execute([
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'payload' => json_encode(['message_id' => $messageId, 'channel' => $channel, 'delivery_status' => $deliveryStatus], JSON_THROW_ON_ERROR),
            ]);
            $pdo->commit();
            return $messageId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return array{status:string,connected:bool,detail:?string} */
    public function whatsAppStatus(): array
    {
        return $this->whatsApp->status();
    }
}
