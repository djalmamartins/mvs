<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Modules;

/**
 * Moves | Modules Test
 *
 * Valida o registro mínimo de permissões das aplicações.
 *
 * @author Djalma Martins
 */

Modules::register('example', null, [
    'user' => ['example.view', 'example.view'],
]);

if (Modules::permissions('user') !== ['example.view']) {
    throw new RuntimeException('FAIL: permissões de módulo não foram registradas.');
}

echo 'OK: registro modular mínimo.' . PHP_EOL;
