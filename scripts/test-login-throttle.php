<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Environment;
use Moves\Core\LoginThrottle;

/**
 * Moves | Login Throttle Test
 *
 * Valida o bloqueio temporário de tentativas de autenticação.
 *
 * @author Djalma Martins
 */

Environment::load(dirname(__DIR__));
$email = 'throttle-' . bin2hex(random_bytes(8)) . '@example.invalid';
$ip = '127.0.0.2';

try {
    LoginThrottle::clear($email, $ip);

    for ($attempt = 1; $attempt <= 5; ++$attempt) {
        LoginThrottle::recordFailure($email, $ip);
    }

    if (!LoginThrottle::blocked($email, $ip)) {
        throw new RuntimeException('FAIL: limite de login não foi aplicado.');
    }

    LoginThrottle::clear($email, $ip);

    if (LoginThrottle::blocked($email, $ip)) {
        throw new RuntimeException('FAIL: limite de login não foi limpo.');
    }

    echo 'OK: limite temporário de login.' . PHP_EOL;
} finally {
    LoginThrottle::clear($email, $ip);
}
