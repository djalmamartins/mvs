<?php

declare(strict_types=1);

use Moves\Boot\Connection;
use Moves\Core\LoginThrottle;

require dirname(__DIR__) . '/vendor/autoload.php';

final class LoginThrottleIntegrationTest extends \PHPUnit\Framework\TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = Connection::getInstance();
        $this->pdo->exec('DELETE FROM login_attempts');
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DELETE FROM login_attempts');
    }

    public function testBlocksAfterFiveFailuresAndClearRestoresAccess(): void
    {
        $email = 'throttle-' . bin2hex(random_bytes(8)) . '@example.invalid';
        $ip = '203.0.113.42';

        self::assertFalse(LoginThrottle::blocked($email, $ip));

        for ($attempt = 1; $attempt <= 4; ++$attempt) {
            LoginThrottle::recordFailure($email, $ip);
            self::assertFalse(LoginThrottle::blocked($email, $ip));
        }

        LoginThrottle::recordFailure($email, $ip);
        self::assertTrue(LoginThrottle::blocked($email, $ip));

        LoginThrottle::clear($email, $ip);
        self::assertFalse(LoginThrottle::blocked($email, $ip));
    }

    public function testThrottleKeySeparatesEmailAndIpCombinations(): void
    {
        $email = 'isolated-' . bin2hex(random_bytes(8)) . '@example.invalid';

        for ($attempt = 0; $attempt < 5; ++$attempt) {
            LoginThrottle::recordFailure($email, '203.0.113.10');
        }

        self::assertTrue(LoginThrottle::blocked($email, '203.0.113.10'));
        self::assertFalse(LoginThrottle::blocked($email, '203.0.113.11'));
        self::assertFalse(LoginThrottle::blocked('other-' . $email, '203.0.113.10'));
    }
}
