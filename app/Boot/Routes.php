<?php

declare(strict_types=1);

namespace Moves\Boot;

use MovesCode\Router\Router;

/**
 * Moves | Routes
 *
 * Registra as rotas HTTP da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Boot
 */
final class Routes
{
    public static function register(Router $router): void
    {
        $router
            ->namespace('Moves\\Controllers')
            ->group('');

        $router->get(
            '/',
            'Home:index',
            'home'
        );

        $router->get(
            '/form-test',
            'FormTest:index',
            'form.test'
        );

        $router->post(
            '/form-test',
            'FormTest:store',
            'form.test.store'
        );
    }
}