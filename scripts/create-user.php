<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Connection as DatabaseConnection;
use Moves\Boot\Environment;
use Moves\Models\User;
use MovesCode\Model\Connection as ModelConnection;

/**
 * Moves | Create User
 *
 * Cria o primeiro usuário local utilizado
 * nos testes de autenticação.
 *
 * @author Djalma Martins
 */

Environment::load(dirname(__DIR__));

$pdo = DatabaseConnection::getInstance();

ModelConnection::configure($pdo);

$email = 'djalma.martins@moves.com.br';

$existing = (new User())
    ->find(
        'email = :email',
        [
            'email' => $email,
        ]
    )
    ->fetch();

if ($existing) {
    echo 'User already exists' . PHP_EOL;
    exit;
}

$user = new User();

$user->name = 'Djalma Martins';
$user->email = $email;
$user->password = password_hash(
    'Moves#1310',
    PASSWORD_DEFAULT
);
$user->status = 'active';

if (!$user->save()) {
    echo 'Could not create user' . PHP_EOL;
    exit;
}

echo 'User created successfully' . PHP_EOL;
