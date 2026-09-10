<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Core\Validator;

/**
 * Moves | Validator Test
 *
 * Valida as regras básicas de validação da aplicação.
 *
 * @author Djalma Martins
 */

$validator = new Validator();

$validator
    ->required(
        'name',
        ''
    )
    ->email(
        'email',
        'email-invalido'
    )
    ->min(
        'password',
        '123',
        6
    );

if ($validator->fails()) {
    print_r(
        $validator->errors()
    );
}