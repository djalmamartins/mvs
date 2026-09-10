<?php

declare(strict_types=1);

namespace Moves\Core;

use Moves\Boot\Connection as DatabaseConnection;
use MovesCode\Model\Connection as ModelConnection;

/**
 * Moves | Application
 *
 * Inicializa e executa o fluxo principal da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class Application
{
    public function run(): void
    {
        $pdo = DatabaseConnection::getInstance();

        ModelConnection::configure($pdo);

        echo 'Moves';
    }
}