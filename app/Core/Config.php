<?php

declare(strict_types=1);

namespace Moves\Core;

/**
 * Moves | Configuration
 *
 * Fornece acesso centralizado às configurações
 * e ao ambiente da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class Config
{
    public static function get(
        string $key,
        mixed $default = null
    ): mixed {
        return $_ENV[$key] ?? $default;
    }

    public static function environment(): string
    {
        return (string) self::get(
            'APP_ENV',
            'production'
        );
    }

    public static function isDevelopment(): bool
    {
        return self::environment() === 'development';
    }

    public static function isProduction(): bool
    {
        return self::environment() === 'production';
    }

    public static function debug(): bool
    {
        return filter_var(
            self::get('APP_DEBUG', false),
            FILTER_VALIDATE_BOOL
        );
    }
}
