<?php

declare(strict_types=1);

use Moves\Services\Auth\MfaRecoveryCodeService;
use PHPUnit\Framework\TestCase;

final class MfaRecoveryCodeServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE platform_mfa_recovery_codes (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL,code_hash TEXT NOT NULL,used_at TEXT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    }

    public function testConsumesRecoveryCodeOnlyOnce(): void
    {
        $normalized = 'ABCD2345EFGH';
        $insert = $this->pdo->prepare('INSERT INTO platform_mfa_recovery_codes (user_id,code_hash) VALUES (7,:hash)');
        $insert->execute(['hash' => password_hash($normalized, PASSWORD_DEFAULT)]);

        $service = new MfaRecoveryCodeService($this->pdo);

        self::assertTrue($service->consume(7, 'ABCD-2345-EFGH'));
        self::assertFalse($service->consume(7, 'ABCD-2345-EFGH'));
        self::assertNotFalse($this->pdo->query('SELECT used_at FROM platform_mfa_recovery_codes')->fetchColumn());
    }

    public function testRejectsInvalidOrAnotherUsersCode(): void
    {
        $insert = $this->pdo->prepare('INSERT INTO platform_mfa_recovery_codes (user_id,code_hash) VALUES (7,:hash)');
        $insert->execute(['hash' => password_hash('ABCD2345EFGH', PASSWORD_DEFAULT)]);

        $service = new MfaRecoveryCodeService($this->pdo);

        self::assertFalse($service->consume(8, 'ABCD-2345-EFGH'));
        self::assertFalse($service->consume(7, 'invalid'));
        self::assertFalse($service->consume(7, 'ZZZZ-9999-ZZZZ'));
        self::assertNull($this->pdo->query('SELECT used_at FROM platform_mfa_recovery_codes')->fetchColumn());
    }
}
