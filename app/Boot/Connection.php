<?php

declare(strict_types=1);

namespace Moves\Boot;

use Moves\Core\Config;
use PDO;
use PDOException;

/**
 * Moves | Database Connection
 *
 * Gerencia a conexão PDO com o banco de dados da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Boot
 */
final class Connection
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $host = Config::get('DB_HOST', 'localhost');
        $port = Config::get('DB_PORT', '3306');
        $database = Config::get('DB_DATABASE');
        $username = Config::get('DB_USERNAME');
        $password = Config::get('DB_PASSWORD', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        try {
            self::$instance = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            return self::$instance;
        } catch (PDOException $exception) {
            throw new PDOException(
                'Não foi possível conectar ao banco de dados.',
                (int) $exception->getCode(),
                $exception
            );
        }
    }
}
