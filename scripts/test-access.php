<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Connection as DatabaseConnection;
use Moves\Boot\Environment;
use Moves\Core\Access;
use Moves\Core\Auth;
use Moves\Core\Session;
use MovesCode\Model\Connection as ModelConnection;

/**
 * Moves | Access Test
 *
 * Valida as regras básicas de autorização.
 *
 * @author Djalma Martins
 */

Environment::load(dirname(__DIR__));

$pdo = DatabaseConnection::getInstance();

ModelConnection::configure($pdo);

Session::start();

Session::set(
    'auth_user',
    1
);

$checks = [
    'profile.view' => true,
    'users.manage' => true,
    'settings.manage' => true,
    'unknown.permission' => false,
];

$passed = 0;

foreach ($checks as $permission => $expected) {
    $result = Access::can($permission);

    if ($result === $expected) {
        echo 'PASS: ' . $permission . PHP_EOL;
        $passed++;
        continue;
    }

    echo 'FAIL: ' . $permission . PHP_EOL;
}

echo PHP_EOL;
echo 'OK: ' . $passed . ' verificações.' . PHP_EOL;