<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Environment;
use Moves\Core\Config;

/**
 * Moves | Configuration Test
 *
 * Valida as configurações básicas e o ambiente
 * da aplicação.
 *
 * @author Djalma Martins
 */

Environment::load(dirname(__DIR__));

$checks = 0;

$check = static function (
    bool $condition,
    string $message
) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException(
            'FAIL: ' . $message
        );
    }

    $checks++;

    echo 'PASS: ' . $message . PHP_EOL;
};

$check(
    Config::get('APP_NAME') === 'Moves Application Platform',
    'nome da aplicação carregado'
);

$check(
    Config::environment() === 'development',
    'ambiente development reconhecido'
);

$check(
    Config::isDevelopment(),
    'aplicação identificada como desenvolvimento'
);

$check(
    !Config::isProduction(),
    'aplicação não identificada como produção'
);

$check(
    Config::debug(),
    'modo debug ativado'
);

echo 'OK: ' . $checks . ' verificações.' . PHP_EOL;