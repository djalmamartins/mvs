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
    'Teste do Logger do Moves.',
    [
        'source' => 'test-logger',
    ]
);

echo 'OK: registro de log criado.' . PHP_EOL;