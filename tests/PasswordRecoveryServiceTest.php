<?php

declare(strict_types=1);

use Moves\Contracts\RecoveryMailer;
use Moves\Services\Auth\PasswordRecoveryService;
use PHPUnit\Framework\TestCase;

final class PasswordRecoveryServiceTest extends TestCase
{
    private PDO $pdo;
    private RecoveryMailer $mailer;
    private DateTimeImmutable $now;
    /** @var array<int,array<string,mixed>> */
    private array $messages = [];

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, password TEXT, status TEXT)');
        $this->pdo->exec('CREATE TABLE password_recovery_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NULL, email_hash TEXT, code_hash TEXT, requested_ip_hash TEXT, attempts INTEGER DEFAULT 0, expires_at TEXT, verified_at TEXT NULL, used_at TEXT NULL, created_at TEXT)');
        $this->now = new DateTimeImmutable('2026-09-28 10:00:00');
        $messages = &$this->messages;
        $this->mailer = new class($messages) implements RecoveryMailer {
            /** @param array<int,array<string,mixed>> $messages */
            public function __construct(private array &$messages) {}

            public function sendRecoveryCode(string $email, string $name, string $code, int $expiresInMinutes): void
            {
                $this->messages[] = compact('email', 'name', 'code', 'expiresInMinutes');
            }
        };
    }

    public function testIssuesHashedExpiringCodeOnlyForActiveKnownUser(): void
    {
        $this->createUser();
        $service = $this->service();

        $known = $service->request('ANA@example.com', '127.0.0.1');
        $unknown = $service->request('unknown@example.com', '127.0.0.1');

        self::assertCount(1, $this->messages);
        self::assertSame('ana@example.com', $this->messages[0]['email']);
        self::assertMatchesRegularExpression('/^\d{6}$/', (string) $this->messages[0]['code']);
        self::assertSame('an•••@example.com', $known['masked_email']);
        self::assertSame('un•••••@example.com', $unknown['masked_email']);
        $rows = $this->pdo->query('SELECT * FROM password_recovery_requests ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(2, $rows);
        self::assertNotSame($this->messages[0]['code'], $rows[0]['code_hash']);
        self::assertTrue(password_verify((string) $this->messages[0]['code'], (string) $rows[0]['code_hash']));
        self::assertNull($rows[1]['user_id']);
    }

    public function testResendCooldownAndWindowRateLimitDoNotSendExtraCodes(): void
    {
        $this->createUser();
        $service = $this->service();

        $first = $service->request('ana@example.com', '127.0.0.1');
        $blockedByCooldown = $service->request('ana@example.com', '127.0.0.1');
        self::assertTrue($blockedByCooldown['rate_limited']);
        self::assertSame($first['request_id'], $blockedByCooldown['request_id']);
        self::assertSame(60, $blockedByCooldown['retry_after']);

        $this->now = $this->now->modify('+61 seconds');
        $second = $service->request('ana@example.com', '127.0.0.1');
        $this->now = $this->now->modify('+61 seconds');
        $third = $service->request('ana@example.com', '127.0.0.1');
        $this->now = $this->now->modify('+61 seconds');
        $blockedByWindow = $service->request('ana@example.com', '127.0.0.1');

        self::assertNotSame($first['request_id'], $second['request_id']);
        self::assertNotSame($second['request_id'], $third['request_id']);
        self::assertTrue($blockedByWindow['rate_limited']);
        self::assertGreaterThan(600, $blockedByWindow['retry_after']);
        self::assertCount(3, $this->messages);
        self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM password_recovery_requests')->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM password_recovery_requests WHERE used_at IS NOT NULL')->fetchColumn());
    }

    public function testValidCodeIsVerifiedAndCannotBeReplayedAsAnotherAttempt(): void
    {
        $this->createUser();
        $service = $this->service();
        $request = $service->request('ana@example.com', '127.0.0.1');
        $code = (string) $this->messages[0]['code'];

        $result = $service->verify($request['request_id'], 'ana@example.com', $code);
        $replay = $service->verify($request['request_id'], 'ana@example.com', $code);

        self::assertSame('verified', $result['status']);
        self::assertSame('verified', $replay['status']);
        $row = $this->pdo->query('SELECT attempts,verified_at,used_at FROM password_recovery_requests')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(1, (int) $row['attempts']);
        self::assertNotNull($row['verified_at']);
        self::assertNull($row['used_at']);
    }

    public function testInvalidCodeLocksRequestAfterFiveAttempts(): void
    {
        $this->createUser();
        $service = $this->service();
        $request = $service->request('ana@example.com', '127.0.0.1');

        for ($attempt = 1; $attempt <= PasswordRecoveryService::MAX_ATTEMPTS; $attempt++) {
            $result = $service->verify($request['request_id'], 'ana@example.com', '000000');
        }

        self::assertSame('locked', $result['status']);
        self::assertSame(0, $result['remaining_attempts']);
        $validAfterLock = $service->verify(
            $request['request_id'],
            'ana@example.com',
            (string) $this->messages[0]['code']
        );
        self::assertSame('locked', $validAfterLock['status']);
    }

    public function testExpiredAndSupersededCodesAreRejected(): void
    {
        $this->createUser();
        $service = $this->service();
        $expired = $service->request('ana@example.com', '127.0.0.1');
        $expiredCode = (string) $this->messages[0]['code'];
        $this->now = $this->now->modify('+16 minutes');

        self::assertSame(
            'expired',
            $service->verify($expired['request_id'], 'ana@example.com', $expiredCode)['status']
        );

        $current = $service->request('ana@example.com', '127.0.0.2');
        $this->now = $this->now->modify('+61 seconds');
        $replacement = $service->request('ana@example.com', '127.0.0.2');

        self::assertNotSame($current['request_id'], $replacement['request_id']);
        self::assertSame(
            'locked',
            $service->verify($current['request_id'], 'ana@example.com', (string) $this->messages[1]['code'])['status']
        );
    }

    public function testFailedDeliveryDoesNotLeaveAnUnusableActiveRequest(): void
    {
        $this->createUser();
        $mailer = new class implements RecoveryMailer {
            public function sendRecoveryCode(string $email, string $name, string $code, int $expiresInMinutes): void
            {
                throw new RuntimeException('SMTP indisponível no teste.');
            }
        };
        $service = new PasswordRecoveryService(
            $this->pdo,
            $mailer,
            fn (): DateTimeImmutable => $this->now
        );

        try {
            $service->request('ana@example.com', '127.0.0.1');
            self::fail('A falha de entrega deveria ser propagada.');
        } catch (RuntimeException $exception) {
            self::assertSame('SMTP indisponível no teste.', $exception->getMessage());
        }

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM password_recovery_requests')->fetchColumn());
    }

    private function createUser(): void
    {
        $this->pdo->exec("INSERT INTO users(id,name,email,password,status) VALUES(1,'Ana','ana@example.com','hash','active')");
    }

    private function service(): PasswordRecoveryService
    {
        return new PasswordRecoveryService(
            $this->pdo,
            $this->mailer,
            fn (): DateTimeImmutable => $this->now
        );
    }
}
