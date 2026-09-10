<?php

declare(strict_types=1);

namespace Moves\Core;

final class Config
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }
}
