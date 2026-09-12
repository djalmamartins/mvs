<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Environment;
use Moves\Core\Diagnostics;

/**
 * Moves | Environment Check
 *
 * Valida os requisitos de uma instalação sem expor segredos.
 *
 * @author Djalma Martins
 */

Environment::load(dirname(__DIR__));
$failed = false;

foreach (Diagnostics::run() as $check) {
    echo ($check['ok'] ? 'OK' : 'FAIL') . ': ' . $check['message'] . PHP_EOL;
    $failed = $failed || !$check['ok'];
}

exit($failed ? 1 : 0);
