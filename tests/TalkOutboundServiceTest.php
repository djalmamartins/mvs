<?php

declare(strict_types=1);

use Moves\Boot\Connection;
use Moves\Services\Talk\TalkOutboundService;
use Moves\Services\Talk\Transport\WhatsAppTransport;
use PHPUnit\Framework\TestCase;

final class TalkOutboundServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Connection::setInstance(null);
    }

    public function testSimulationPersistsSentMessageAndMetadata(): void
    {
        $pdo = $this->database('simulation', 7);
        Connection::setInstance($pdo);

        $id = (new TalkOutboundService(new FakeWhatsAppTransport()))->sendText(1, 7, 'Olá');

        self::assertSame(1, $id);
        $message = $pdo->query('SELECT external_id, body, metadata FROM talk_messages')->fetch(PDO::FETCH_ASSOC);
        self::assertNull($message['external_id']);
        self::assertSame('Olá', $message['body']);
        self::assertTrue((bool) json_decode($message['metadata'], true, 512, JSON_THROW_ON_ERROR)['simulation']);
    }

    public function testWhatsAppPersistsProviderMessageIdAndDeliveryStatus(): void
    {
        $pdo = $this->database('whatsapp', 7);
        Connection::setInstance($pdo);

        (new TalkOutboundService(new FakeWhatsAppTransport()))->sendText(1, 7, 'Teste');

        $message = $pdo->query('SELECT external_id, metadata FROM talk_messages')->fetch(PDO::FETCH_ASSOC);
        $metadata = json_decode($message['metadata'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('provider-123', $message['external_id']);
        self::assertSame('sent', $metadata['delivery_status']);
        self::assertSame('whatsapp', $metadata['transport']);
    }

    public function testUnauthorizedUserCannotSend(): void
    {
        Connection::setInstance($this->database('simulation', 7));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Atendimento indisponível para envio.');
        (new TalkOutboundService(new FakeWhatsAppTransport()))->sendText(1, 99, 'Não pode');
    }

    public function testTransportFailureDoesNotPersistFalsePositive(): void
    {
        $pdo = $this->database('whatsapp', 7);
        Connection::setInstance($pdo);

        try {
            (new TalkOutboundService(new FailingWhatsAppTransport()))->sendText(1, 7, 'Falha');
            self::fail('Era esperado erro do transporte.');
        } catch (RuntimeException $e) {
            self::assertSame('WhatsApp desconectado.', $e->getMessage());
        }

        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM talk_messages')->fetchColumn());
    }

    private function database(string $channel, int $assignedUser): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => '2026-09-22 00:00:00');
        $pdo->exec('CREATE TABLE talk_contacts (id INTEGER PRIMARY KEY, phone TEXT, external_id TEXT)');
        $pdo->exec('CREATE TABLE talk_conversations (id INTEGER PRIMARY KEY, contact_id INTEGER, channel TEXT, last_message_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE talk_tickets (id INTEGER PRIMARY KEY, conversation_id INTEGER, assigned_user_id INTEGER, status TEXT, source TEXT, first_response_at TEXT, last_activity_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE talk_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, conversation_id INTEGER, ticket_id INTEGER, sender_type TEXT, sender_user_id INTEGER, external_id TEXT, direction TEXT, type TEXT, body TEXT, metadata TEXT, sent_at TEXT)');
        $pdo->exec('CREATE TABLE talk_events (id INTEGER PRIMARY KEY AUTOINCREMENT, ticket_id INTEGER, user_id INTEGER, actor_type TEXT, event_type TEXT, payload TEXT)');
        $pdo->exec("INSERT INTO talk_contacts(id,phone,external_id) VALUES(1,'5511999999999','contact-1')");
        $pdo->exec("INSERT INTO talk_conversations(id,contact_id,channel) VALUES(1,1,".$pdo->quote($channel).")");
        $pdo->exec("INSERT INTO talk_tickets(id,conversation_id,assigned_user_id,status,source) VALUES(1,1,$assignedUser,'assigned',".$pdo->quote($channel).")");
        return $pdo;
    }
}

final class FakeWhatsAppTransport implements WhatsAppTransport
{
    public function sendText(string $to, string $text): array { return ['message_id' => 'provider-123', 'status' => 'sent']; }
    public function sendMedia(string $to, string $absolutePath, string $mimeType, ?string $caption = null): array { return ['message_id' => 'media-123', 'status' => 'sent']; }
    public function status(): array { return ['status' => 'connected', 'connected' => true, 'detail' => null]; }
}

final class FailingWhatsAppTransport implements WhatsAppTransport
{
    public function sendText(string $to, string $text): array { throw new RuntimeException('WhatsApp desconectado.'); }
    public function sendMedia(string $to, string $absolutePath, string $mimeType, ?string $caption = null): array { throw new RuntimeException('WhatsApp desconectado.'); }
    public function status(): array { return ['status' => 'offline', 'connected' => false, 'detail' => 'desconectado']; }
}
