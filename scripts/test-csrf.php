<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Core\Csrf;
use Moves\Core\Session;

/**
 * Moves | CSRF Test
 *
 * Valida a geração e verificação dos tokens CSRF.
 *
 * @author Djalma Martins
 */

Session::clear();

$token = Csrf::token();

echo 'Token generated: '
    . ($token !== '' ? 'yes' : 'no')
    . PHP_EOL;

echo 'Valid token: '
    . (Csrf::validate($token) ? 'yes' : 'no')
    . PHP_EOL;

echo 'Invalid token: '
    . (Csrf::validate('invalid-token') ? 'yes' : 'no')
    . PHP_EOL;

echo Csrf::field()
    . PHP_EOL;