<?php

declare(strict_types=1);

namespace Moves\Boot;

use Dotenv\Dotenv;

final class Environment
{
    public static function load(string $path): void
    {
        $dotenv = Dotenv::createImmutable($path);
        $dotenv->safeLoad();
    }
}
