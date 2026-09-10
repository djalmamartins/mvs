<?php

declare(strict_types=1);

namespace Moves\Core;

use Moves\Models\Setting;

/**
 * Moves | Settings
 *
 * Gerencia as configurações persistidas da aplicação.
 *
 * As configurações de infraestrutura e dados sensíveis devem
 * permanecer no ambiente e serem acessadas através de Config.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class Settings
{
    /**
     * Retorna uma configuração persistida.
     */
    public static function get(
        string $name,
        mixed $default = null
    ): mixed {
        $setting = (new Setting())
            ->find(
                'name = :name',
                ['name' => $name]
            )
            ->fetch();

        return $setting?->value ?? $default;
    }
}
