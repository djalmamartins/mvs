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

        if (!$setting instanceof Setting) {
            return $default;
        }

        return $setting->value ?? $default;
    }

    /**
     * Cria ou atualiza uma configuração persistida.
     */
    public static function set(
        string $name,
        mixed $value
    ): bool {
        $setting = (new Setting())
            ->find(
                'name = :name',
                ['name' => $name]
            )
            ->fetch();

        if (!$setting instanceof Setting) {
            $setting = new Setting();
            $setting->name = $name;
        }

        $setting->value = is_scalar($value) || $value === null
            ? $value
            : json_encode(
                $value,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            );

        return $setting->save();
    }
}
