<?php

declare(strict_types=1);

namespace Moves\Core;

/**
 * Moves | Theme
 *
 * Gerencia o tema visual ativo da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class Theme
{
    public static function active(): string
    {
        return (string) Config::get('APP_THEME', 'default');
    }

    public static function path(): string
    {
        return dirname(__DIR__, 2)
            . '/resources/themes/'
            . self::active();
    }

    public static function asset(string $path): string
    {
        return '/themes/'
            . self::active()
            . '/'
            . ltrim($path, '/');
    }
}
