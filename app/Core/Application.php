<?php

declare(strict_types=1);

namespace Moves\Core;

use Moves\Boot\Connection as DatabaseConnection;
use Moves\Boot\Routes;
use Moves\Controllers\ErrorController;
use MovesCode\Model\Connection as ModelConnection;
use MovesCode\Router\Router;
use Throwable;
use Moves\Core\HttpException;
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
        $router = new Router(
            (string) Config::get('APP_URL')
        );

        try {
            $pdo = DatabaseConnection::getInstance();

            ModelConnection::configure($pdo);

            Routes::register($router);

            if (!$router->dispatch()) {
                $exception = $router->exception();

                $error = $exception instanceof HttpException
                    ? $exception->statusCode()
                    : (int) ($router->error() ?? 500);

                if (
                    $exception !== null
                    && !$exception instanceof HttpException
                ) {
                    Logger::exception($exception);
                }

                $controller = new ErrorController($router);

                $controller->show(
                    $error,
                    $exception
                );
            }
        } catch (Throwable $exception) {
            Logger::exception($exception);
            $controller = new ErrorController($router);

            $controller->show(
                500,
                $exception
            );
        }
    }
}