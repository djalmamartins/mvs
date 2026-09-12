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
            'home'
        );

        $router->get(
            '/app',
            'Home:app',
            'app.home',
            AuthMiddleware::class
        );

        $router->get(
            '/profile',
            'UserController:legacyProfile',
            'profile.legacy',
            AuthMiddleware::class
        );

        $router->get(
            '/app/profile',
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
            '/admin/users/page/{page}',
            'UserController:index',
            'users.page',
            [
                AuthMiddleware::class,
                new PermissionMiddleware('users.manage'),
            ]
        );

        $settingsMiddleware = [
            AuthMiddleware::class,
            new PermissionMiddleware('settings.manage'),
        ];

        $router->get(
            '/admin/settings',
            'SettingsController:index',
            'settings.index',
            $settingsMiddleware
        );

        $router->post(
            '/admin/settings',
            'SettingsController:update',
            'settings.update',
            $settingsMiddleware
        );

        $router->get(
            '/admin/diagnostics',
            'DiagnosticsController:index',
            'diagnostics.index',
            [
                AuthMiddleware::class,
                new PermissionMiddleware('diagnostics.view'),
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
            'logout',
            AuthMiddleware::class
        );

        Modules::boot($router);
    }
}
