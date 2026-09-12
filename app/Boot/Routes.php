<?php

declare(strict_types=1);

namespace Moves\Boot;

use MovesCode\Router\Router;
use Moves\Middleware\AuthMiddleware;
use Moves\Middleware\GuestMiddleware;
use Moves\Middleware\PermissionMiddleware;

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
            'home',
            AuthMiddleware::class
        );

        $router->get(
            '/profile',
            'UserController:profile',
            'profile',
            [
                AuthMiddleware::class,
                new PermissionMiddleware('profile.view'),
            ]
        );

        $router->get(
            '/admin/users',
            'UserController:index',
            'users.index',
            [
                AuthMiddleware::class,
                new PermissionMiddleware('users.manage'),
            ]
        );

        $router->get(
            '/login',
            'AuthController:login',
            'login',
            GuestMiddleware::class
        );

        $router->post(
            '/login',
            'AuthController:authenticate',
            'login.authenticate',
            GuestMiddleware::class
        );

        $router->post(
            '/logout',
            'AuthController:logout',
            'logout'
        );
    }
}
