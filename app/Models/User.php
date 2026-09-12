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
 * @property int|null $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $status
 * @property string|null $role
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
