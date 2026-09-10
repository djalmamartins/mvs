<?php

declare(strict_types=1);

namespace Moves\Core;

use Moves\Boot\Connection as DatabaseConnection;
use Moves\Boot\Routes;
use MovesCode\Model\Connection as ModelConnection;
use MovesCode\Router\Router;

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

        $router = new Router(
            (string) Config::get('APP_URL')
        );

        Routes::register($router);

        if (!$router->dispatch()) {
            echo 'Router error: ' . $router->error() . PHP_EOL;
        }
    }
}