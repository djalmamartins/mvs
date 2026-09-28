<?php

declare(strict_types=1);

use Moves\Contracts\RecoveryMailer;
use Moves\Services\Auth\PasswordRecoveryService;
use PHPUnit\Framework\TestCase;

final class PasswordRecoveryServiceTest extends TestCase
{
    private PDO $pdo;
    private RecoveryMailer $mailer;
    /** @var array<int,array<string,mixed>> */
    private array $messages = [];

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, password TEXT, status TEXT)');
        $this->pdo->exec('CREATE TABLE password_recovery_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NULL, email_hash TEXT, code_hash TEXT, requested_ip_hash TEXT, attempts INTEGER DEFAULT 0, expires_at TEXT, used_at TEXT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
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
        $this->pdo->exec("INSERT INTO users(id,name,email,password,status) VALUES(1,'Ana','ana@example.com','hash','active')");
        $service = new PasswordRecoveryService($this->pdo, $this->mailer);

        $service->request('ANA@example.com', '127.0.0.1');
        $service->request('unknown@example.com', '127.0.0.1');

        self::assertCount(1, $this->messages);
        self::assertSame('ana@example.com', $this->messages[0]['email']);
        self::assertMatchesRegularExpression('/^\d{6}$/', (string) $this->messages[0]['code']);
        $rows = $this->pdo->query('SELECT * FROM password_recovery_requests ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(2, $rows);
        self::assertNotSame($this->messages[0]['code'], $rows[0]['code_hash']);
        self::assertTrue(password_verify((string) $this->messages[0]['code'], (string) $rows[0]['code_hash']));
        self::assertNull($rows[1]['user_id']);
    }

    public function testNewRequestInvalidatesPriorCodeAndRateLimitsSilently(): void
    {
        $this->pdo->exec("INSERT INTO users(id,name,email,password,status) VALUES(1,'Ana','ana@example.com','hash','active')");
        $service = new PasswordRecoveryService($this->pdo, $this->mailer);
        for ($i = 0; $i < 4; $i++) {
            $service->request('ana@example.com', '127.0.0.1');
        }

        self::assertCount(3, $this->messages);
        self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM password_recovery_requests')->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM password_recovery_requests WHERE used_at IS NOT NULL')->fetchColumn());
    }
}
