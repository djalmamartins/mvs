<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Core\Session;

/**
 * Moves | Session Test
 *
 * Valida as operações básicas da sessão.
 *
 * @author Djalma Martins
 */

Session::set('test_name', 'Moves');

echo Session::get('test_name') . PHP_EOL;

echo Session::has('test_name')
    ? 'Session exists' . PHP_EOL
    : 'Session missing' . PHP_EOL;

Session::remove('test_name');

echo Session::get(
        'test_name',
        'Default Value'
    ) . PHP_EOL;
