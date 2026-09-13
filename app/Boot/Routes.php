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
            '/servicos',
            'Home:services',
            'site.services'
        );

        $router->get(
            '/projetos',
            'Home:projects',
            'site.projects'
        );

        $router->get(
            '/sobre',
            'Home:about',
            'site.about'
        );

        $router->get(
            '/conteudo',
            'Home:content',
            'site.content'
        );

        $router->get('/conteudo/{slug}', 'Home:article', 'site.article');
        $router->get('/media/{id}', 'StudioModulesController:mediaFile', 'site.media.file');

        $router->get(
            '/contato',
            'Home:contact',
            'site.contact'
        );
        $router->post('/contato', 'Home:contactSubmit', 'site.contact.submit');

        $router->get(
            '/app',
            'Home:app',
            'app.home',
            AuthMiddleware::class
        );

        $router->get(
            '/app/status',
            'Home:appStatus',
            'app.status',
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
        $router->get('/admin/users/create', 'UserController:form', 'users.create', [AuthMiddleware::class, new PermissionMiddleware('users.manage')]);
        $router->get('/admin/users/edit/{id}', 'UserController:form', 'users.edit', [AuthMiddleware::class, new PermissionMiddleware('users.manage')]);
        $router->post('/admin/users/save', 'UserController:save', 'users.save', [AuthMiddleware::class, new PermissionMiddleware('users.manage')]);
        $router->post('/admin/users/action', 'UserController:action', 'users.action', [AuthMiddleware::class, new PermissionMiddleware('users.manage')]);

        $router->get(
            '/admin',
            'StudioController:dashboard',
            'admin.home',
            [
                AuthMiddleware::class,
                new PermissionMiddleware('users.manage'),
            ]
        );

        $studioTechnicalMiddleware = [
            AuthMiddleware::class,
            new PermissionMiddleware('users.manage'),
        ];

        $studioSettingsMiddleware = [
            AuthMiddleware::class,
            new PermissionMiddleware('settings.manage'),
        ];

        $router->get(
            '/admin/versions',
            'StudioController:versions',
            'admin.versions',
            $studioSettingsMiddleware
        );
        $router->post('/admin/versions', 'StudioController:versions', 'admin.versions.release', $studioSettingsMiddleware);

        $router->get(
            '/admin/logs',
            'StudioController:logs',
            'admin.logs',
            $studioTechnicalMiddleware
        );
        $router->post('/admin/logs', 'StudioController:logs', 'admin.logs.action', $studioTechnicalMiddleware);

        foreach (['pages', 'articles', 'highlights', 'testimonials', 'faq'] as $module) {
            $router->get('/admin/' . $module, 'StudioModulesController:content', 'admin.' . $module, $studioTechnicalMiddleware);
            $router->post('/admin/' . $module, 'StudioModulesController:content', 'admin.' . $module . '.save', $studioTechnicalMiddleware);
        }
        $router->get('/admin/media', 'StudioModulesController:media', 'admin.media', $studioTechnicalMiddleware);
        $router->post('/admin/media', 'StudioModulesController:media', 'admin.media.save', $studioTechnicalMiddleware);
        $router->get('/admin/media/file/{id}', 'StudioModulesController:mediaFile', 'admin.media.file', $studioTechnicalMiddleware);
        $router->get('/admin/proposals', 'StudioModulesController:proposals', 'admin.proposals', $studioTechnicalMiddleware);
        $router->post('/admin/proposals', 'StudioModulesController:proposals', 'admin.proposals.save', $studioTechnicalMiddleware);
        $router->get('/admin/notifications', 'StudioModulesController:notifications', 'admin.notifications', $studioTechnicalMiddleware);
        $router->post('/admin/notifications', 'StudioModulesController:notifications', 'admin.notifications.read', $studioTechnicalMiddleware);
        $router->get('/admin/reports', 'StudioModulesController:reports', 'admin.reports', $studioTechnicalMiddleware);

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
