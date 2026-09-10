<?php

declare(strict_types=1);

namespace Moves\Boot;

use Dotenv\Dotenv;

/**
 * Moves | Environment
 *
 * Carrega e disponibiliza as variáveis de ambiente da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Boot
 */
final class Environment
{
    public static function load(string $path): void
    {
        $dotenv = Dotenv::createImmutable($path);
        $dotenv->safeLoad();
    }
}
