<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Core\Request;

/**
 * Moves | Request Test
 *
 * Valida o acesso aos dados da requisição.
 *
 * @author Djalma Martins
 */

$_SERVER['REQUEST_METHOD'] = 'POST';

$_GET['page'] = 'dashboard';
$_POST['name'] = 'Moves';

echo Request::method() . PHP_EOL;

echo Request::isMethod('POST')
    ? 'POST request' . PHP_EOL
    : 'Not POST' . PHP_EOL;

echo Request::get('page', 'none') . PHP_EOL;
echo Request::post('name', 'none') . PHP_EOL;
echo Request::input('name', 'none') . PHP_EOL;

echo Request::has('name')
    ? 'Name exists' . PHP_EOL
    : 'Name missing' . PHP_EOL;

print_r(Request::all());
