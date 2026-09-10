<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Core\Flash;

/**
 * Moves | Flash Test
 *
 * Valida o armazenamento e consumo das mensagens flash.
 *
 * @author Djalma Martins
 */

Flash::set(
    'success',
    'Operação realizada com sucesso.'
);

Flash::set(
    'error',
    'Ocorreu um erro.'
);

foreach (Flash::all() as $flash) {
    echo '[' . $flash['type'] . '] ';
    echo $flash['message'] . PHP_EOL;
}

echo Flash::has()
    ? 'Flash exists' . PHP_EOL
    : 'Flash consumed' . PHP_EOL;
