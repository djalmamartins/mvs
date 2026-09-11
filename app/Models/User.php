<?php

declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

/**
 * Moves | User Model
 *
 * Gerencia os usuários da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Models
 */
final class User extends Model
{
    public function __construct()
    {
        parent::__construct(
            'users',
            [],
            [
                'name',
                'email',
                'password',
            ]
        );
    }
}