<?php

declare(strict_types=1);

namespace Moves\Core;
/**
 * Moves | Configuration
 *
 * Fornece acesso centralizado às configurações da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class Config
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }
}
