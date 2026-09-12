<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Environment;
use Moves\Core\Logger;

/**
 * Moves | Logger Test
 *
 * Valida a gravação básica de eventos
 * no sistema de logs da aplicação.
 *
 * @author Djalma Martins
 */

Environment::load(dirname(__DIR__));

Logger::info(
    "Teste do Logger do Moves.\nLinha segura.",
    [
        'source' => 'test-logger',
        'password' => 'sensitive-value',
        'nested' => [
            'csrf_token' => 'another-sensitive-value',
        ],
    ]
);

$logFile = dirname(__DIR__) . '/storage/logs/moves.log';
$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$lastLine = is_array($lines) ? end($lines) : false;
$record = is_string($lastLine) ? json_decode($lastLine, true) : null;

if (
    !is_array($record)
    || ($record['context']['password'] ?? null) !== '[REDACTED]'
    || ($record['context']['nested']['csrf_token'] ?? null) !== '[REDACTED]'
    || str_contains($lastLine, 'sensitive-value')
) {
    throw new RuntimeException('FAIL: Logger não removeu dados sensíveis.');
}

echo 'OK: registro JSON com dados sensíveis removidos.' . PHP_EOL;
