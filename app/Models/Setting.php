<?php

declare(strict_types=1);

namespace Moves\Models;

use MovesCode\Model\Model;

/**
 * Moves | Setting Model
 *
 * Gerencia as configurações persistidas da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Models
 * @property int|null $id
 * @property string $name
 * @property mixed $value
 */
final class Setting extends Model
{
    public function __construct()
    {
        parent::__construct(
            'settings',
            [],
            ['name']
        );
    }
}
