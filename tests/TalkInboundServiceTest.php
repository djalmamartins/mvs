<?php

declare(strict_types=1);

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Services\Talk\TalkInboundService;
use PHPUnit\Framework\TestCase;

final class TalkInboundServiceTest extends TestCase
{
    private PDO $pdo;
    private string $prefix;
    private string $channelExternalId = 'whatsapp-default';

    protected function setUp(): void
    {
        Environment::load(dirname(__DIR__));
        $this->pdo = Connection::getInstance();
        $this->prefix = 'phpunit-talk-'.bin2hex(random_bytes(6));
        $this->pdo->prepare("UPDATE talk_channels SET status='active' WHERE external_id=:external_id")
            ->execute(['external_id'=>$this->channelExternalId]);
    }

    protected function tearDown(): void
    {
        $contacts = $this->pdo->prepare('SELECT id FROM talk_contacts WHERE name LIKE :prefix');
        $contacts->execute(['prefix' => $this->prefix.'%']);
        $contactIds = array_map('intval', $contacts->fetchAll(PDO::FETCH_COLUMN));
        if ($contactIds === []) return;

        $marks = implode(',', array_fill(0, count($contactIds), '?'));
        $conversations = $this->pdo->prepare("SELECT id FROM talk_conversations WHERE contact_id IN ({$marks})");
        $conversations->execute($contactIds);
        $conversationIds = array_map('intval', $conversations->fetchAll(PDO::FETCH_COLUMN));
        if ($conversationIds !== []) {
            $conversationMarks = implode(',', array_fill(0, count($conversationIds), '?'));
            $tickets = $this->pdo->prepare("SELECT id FROM talk_tickets WHERE conversation_id IN ({$conversationMarks})");
            $tickets->execute($conversationIds);
            $ticketIds = array_map('intval', $tickets->fetchAll(PDO::FETCH_COLUMN));
            if ($ticketIds !== []) {
                $ticketMarks = implode(',', array_fill(0, count($ticketIds), '?'));
                $this->pdo->prepare("DELETE FROM talk_events WHERE ticket_id IN ({$ticketMarks})")->execute($ticketIds);
                $this->pdo->prepare("DELETE FROM talk_messages WHERE ticket_id IN ({$ticketMarks})")->execute($ticketIds);
                $this->pdo->prepare("DELETE FROM talk_tickets WHERE id IN ({$ticketMarks})")->execute($ticketIds);
            }
            $this->pdo->prepare("DELETE FROM talk_conversations WHERE id IN ({$conversationMarks})")->execute($conversationIds);
        }
        $this->pdo->prepare("DELETE FROM talk_contacts WHERE id IN ({$marks})")->execute($contactIds);
    }

    public function testInboundCreatesContactConversationTicketMessageAndEventOnce(): void
    {
        $externalId = $this->prefix.'-message-1';
        $phone = '55119'.random_int(10000000, 99999999);
        $payload = [
            'external_id' => $externalId,
            'channel_external_id' => $this->channelExternalId,
            'from' => $phone,
            'from_jid' => $phone.'@s.whatsapp.net',
            'push_name' => $this->prefix.' Contato',
            'type' => 'conversation',
            'body' => 'Preciso de atendimento',
            'timestamp' => time() - 2,
        ];

        $service = new TalkInboundService();
        $ticketId = $service->receiveWhatsApp($payload);

        self::assertGreaterThan(0, $ticketId);
        self::assertSame(0, $service->receiveWhatsApp($payload));

        $message = $this->pdo->prepare('SELECT conversation_id,ticket_id,direction,body FROM talk_messages WHERE external_id=:external_id');
        $message->execute(['external_id' => $externalId]);
        $stored = $message->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($stored);
        self::assertSame($ticketId, (int) $stored['ticket_id']);
        self::assertSame('inbound', $stored['direction']);
        self::assertSame('Preciso de atendimento', $stored['body']);

        $ticket = $this->pdo->prepare('SELECT status,source,queue_id FROM talk_tickets WHERE id=:id');
        $ticket->execute(['id' => $ticketId]);
        $storedTicket = $ticket->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($storedTicket);
        self::assertSame('queued', $storedTicket['status']);
        self::assertSame('whatsapp', $storedTicket['source']);
        self::assertGreaterThan(0, (int) $storedTicket['queue_id']);
    }

    public function testLidIdentityIsAcceptedAndClosedConversationCreatesANewTicket(): void
    {
        $service = new TalkInboundService();
        $firstTicket = $service->receiveWhatsApp([
            'external_id' => $this->prefix.'-lid-message-1',
            'channel_external_id' => $this->channelExternalId,
            'from' => '',
            'from_jid' => $this->lid(),
            'push_name' => $this->prefix.' Contato LID',
            'body' => 'Primeira mensagem',
            'timestamp' => time(),
        ]);
        self::assertGreaterThan(0, $firstTicket);

        $this->pdo->prepare("UPDATE talk_tickets SET status='closed',closed_at=NOW() WHERE id=:id")->execute(['id' => $firstTicket]);
        $secondTicket = $service->receiveWhatsApp([
            'external_id' => $this->prefix.'-lid-message-2',
            'channel_external_id' => $this->channelExternalId,
            'from' => '',
            'from_jid' => $this->lid(),
            'push_name' => $this->prefix.' Contato LID',
            'body' => 'Novo atendimento',
            'timestamp' => time(),
        ]);

        self::assertGreaterThan(0, $secondTicket);
        self::assertNotSame($firstTicket, $secondTicket);
        $query = $this->pdo->prepare('SELECT COUNT(DISTINCT conversation_id) conversations,COUNT(*) tickets FROM talk_tickets WHERE id IN (?,?)');
        $query->execute([$firstTicket, $secondTicket]);
        self::assertSame(['conversations' => 1, 'tickets' => 2], array_map('intval', $query->fetch(PDO::FETCH_ASSOC)));
    }

    public function testReadStateAndExternalIdUniquenessAreBackedByTheSchema(): void
    {
        $columns = $this->pdo->query("SHOW COLUMNS FROM talk_messages LIKE 'read_at'")->fetchAll();
        self::assertCount(1, $columns);
        $index = $this->pdo->query("SHOW INDEX FROM talk_messages WHERE Key_name='talk_messages_tenant_external_id'")->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($index);
        self::assertSame(0, (int) $index['Non_unique']);
    }

    private function lid(): string
    {
        return sprintf('%u', crc32($this->prefix)).'987654321@lid';
    }
}
