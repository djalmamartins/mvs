<?php

declare(strict_types=1);

namespace Moves\Core;

use Moves\Boot\Connection;
use Throwable;

/**
 * Moves | Diagnostics
 *
 * Verifica requisitos essenciais sem revelar dados sensíveis.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class Diagnostics
{
    /**
     * @return array<string, array{ok: bool, message: string}>
     */
    public static function run(bool $database = true): array
    {
        $root = dirname(__DIR__, 2);
        $checks = [
            'php' => [
                'ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
                'message' => 'PHP ' . PHP_VERSION,
            ],
            'extensions' => [
                'ok' => extension_loaded('pdo'),
                'message' => 'Extensão PDO',
            ],
            'configuration' => [
                'ok' => self::hasConfiguration(),
                'message' => 'Configuração principal',
            ],
            'storage' => [
                'ok' => is_dir($root . '/storage') && is_writable($root . '/storage'),
                'message' => 'Diretório storage gravável',
            ],
        ];

        if ($database) {
            try {
                Connection::getInstance()->query('SELECT 1');
                $checks['database'] = ['ok' => true, 'message' => 'Banco acessível'];
            } catch (Throwable) {
                $checks['database'] = ['ok' => false, 'message' => 'Banco indisponível'];
            }
        }

        return $checks;
    }

    private static function hasConfiguration(): bool
    {
        foreach (['APP_ENV', 'APP_URL', 'DB_DATABASE', 'DB_USERNAME'] as $key) {
            if (Config::get($key) === null || Config::get($key) === '') {
                return false;
            }
        }

        return true;
    }
}
