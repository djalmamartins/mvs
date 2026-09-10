<?php

declare(strict_types=1);

namespace Moves\Boot;

use Dotenv\Dotenv;

/**
 * Moves | Environment
 *
 * Carrega e disponibiliza as variáveis de ambiente
 * e configurações básicas de execução da aplicação.
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

        $timezone = $_ENV['APP_TIMEZONE']
            ?? 'UTC';

        date_default_timezone_set(
            $timezone
        );
    }
}