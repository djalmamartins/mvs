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
        $router->get('/pagina/{slug}', 'Home:dynamicPage', 'site.dynamic.page');
        $router->get('/faq', 'Home:faq', 'site.faq');

        // Moves Help Center | Knowledge Base pública
        $router->get('/help', 'HelpController:index', 'help.home');
        $router->get('/help/search', 'HelpController:search', 'help.search');
        $router->get('/help/products/{slug}', 'HelpController:product', 'help.product');
        $router->get('/help/categories/{slug}', 'HelpController:category', 'help.category');
        $router->get('/help/articles/{slug}', 'HelpController:article', 'help.article');
        $router->post('/help/articles/{slug}/feedback', 'HelpController:feedback', 'help.article.feedback');

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

        // Moves Platform products
        $router->get(
            '/day',
            'PlatformController:day',
            'platform.day',
            AuthMiddleware::class
        );

        $router->get(
            '/talk',
            'PlatformController:talk',
            'platform.talk',
            AuthMiddleware::class
        );

        $router->get(
            '/support',
            'SupportWorkspaceController:dashboard',
            'platform.support',
            AuthMiddleware::class
        );

        $router->get('/support/inbox', 'SupportWorkspaceController:inbox', 'support.inbox', AuthMiddleware::class);
        $router->get('/support/my-tickets', 'SupportWorkspaceController:myTickets', 'support.my-tickets', AuthMiddleware::class);
        $router->get('/support/tickets', 'SupportWorkspaceController:tickets', 'support.tickets', AuthMiddleware::class);
        $router->get('/support/sla', 'SupportWorkspaceController:sla', 'support.sla', AuthMiddleware::class);
        $router->get('/support/users', 'SupportWorkspaceController:users', 'support.users', AuthMiddleware::class);
        $router->get('/support/reports', 'SupportWorkspaceController:reports', 'support.reports', AuthMiddleware::class);
        $router->get('/support/settings', 'SupportWorkspaceController:settings', 'support.settings', AuthMiddleware::class);

        // Moves Support | Base de conhecimento
        $router->get(
            '/support/articles',
            'SupportController:articles',
            'support.articles',
            AuthMiddleware::class
        );

        $router->get(
            '/support/articles/create',
            'SupportController:articleForm',
            'support.articles.create',
            AuthMiddleware::class
        );

        $router->get('/support/drafts', 'SupportController:drafts', 'support.drafts', AuthMiddleware::class);
        $router->get('/support/revisions', 'SupportController:revisions', 'support.revisions', AuthMiddleware::class);
        $router->get('/support/trash', 'SupportController:trash', 'support.trash', AuthMiddleware::class);

        $router->get(
            '/support/articles/{slug}/edit',
            'SupportController:articleForm',
            'support.articles.edit',
            AuthMiddleware::class
        );

        $router->post(
            '/support/articles/save',
            'SupportController:articleSave',
            'support.articles.save',
            AuthMiddleware::class
        );

        $router->post('/support/articles/trash', 'SupportController:articleTrash', 'support.articles.trash', AuthMiddleware::class);
        $router->post('/support/articles/restore', 'SupportController:articleRestore', 'support.articles.restore', AuthMiddleware::class);
        $router->post('/support/articles/delete', 'SupportController:articleDelete', 'support.articles.delete', AuthMiddleware::class);

        $router->get(
            '/support/products',
            'SupportKnowledgeController:products',
            'support.products',
            AuthMiddleware::class
        );

        $router->post(
            '/support/products/save',
            'SupportKnowledgeController:productSave',
            'support.products.save',
            AuthMiddleware::class
        );

        $router->post(
            '/support/products/delete',
            'SupportKnowledgeController:productDelete',
            'support.products.delete',
            AuthMiddleware::class
        );

        $router->get(
            '/support/categories',
            'SupportKnowledgeController:categories',
            'support.categories',
            AuthMiddleware::class
        );

        $router->post(
            '/support/categories/save',
            'SupportKnowledgeController:categorySave',
            'support.categories.save',
            AuthMiddleware::class
        );

        $router->post(
            '/support/categories/delete',
            'SupportKnowledgeController:categoryDelete',
            'support.categories.delete',
            AuthMiddleware::class
        );

        $router->get(
            '/support/tags',
            'SupportKnowledgeController:tags',
            'support.tags',
            AuthMiddleware::class
        );

        $router->post(
            '/support/tags/save',
            'SupportKnowledgeController:tagSave',
            'support.tags.save',
            AuthMiddleware::class
        );

        $router->post(
            '/support/tags/delete',
            'SupportKnowledgeController:tagDelete',
            'support.tags.delete',
            AuthMiddleware::class
        );

        $router->get(
            '/erp',
            'PlatformController:erp',
            'platform.erp',
            AuthMiddleware::class
        );

        $router->get(
            '/studio/users',
            'UserController:index',
            'users.index',
            [
                AuthMiddleware::class,
                new PermissionMiddleware('users.manage'),
            ]
        );

        $router->get('/studio/users/create', 'UserController:form', 'users.create', [AuthMiddleware::class, new PermissionMiddleware('users.manage')]);
        $router->get('/studio/users/edit/{id}', 'UserController:form', 'users.edit', [AuthMiddleware::class, new PermissionMiddleware('users.manage')]);
        $router->post('/studio/users/save', 'UserController:save', 'users.save', [AuthMiddleware::class, new PermissionMiddleware('users.manage')]);
        $router->post('/studio/users/action', 'UserController:action', 'users.action', [AuthMiddleware::class, new PermissionMiddleware('users.manage')]);

        $router->get(
            '/studio',
            'StudioController:dashboard',
            'studio.home',
            [
                AuthMiddleware::class,
                new PermissionMiddleware('studio.dashboard'),
            ]
        );
        $router->get('/studio/create', 'StudioController:create', 'studio.create', [AuthMiddleware::class, new PermissionMiddleware('content.manage')]);
        $router->get('/studio/search', 'StudioSearchController:index', 'studio.search', [AuthMiddleware::class, new PermissionMiddleware('studio.search')]);

        $studioTechnicalMiddleware = [
            AuthMiddleware::class,
            new PermissionMiddleware('logs.manage'),
        ];

        $studioSettingsMiddleware = [
            AuthMiddleware::class,
            new PermissionMiddleware('settings.manage'),
        ];

        $router->get(
            '/studio/versions',
            'StudioController:versions',
            'studio.versions',
            $studioSettingsMiddleware
        );
        $router->post('/studio/versions', 'StudioController:versions', 'studio.versions.release', $studioSettingsMiddleware);

        $router->get(
            '/studio/logs',
            'StudioController:logs',
            'studio.logs',
            $studioTechnicalMiddleware
        );
        $router->post('/studio/logs', 'StudioController:logs', 'studio.logs.action', $studioTechnicalMiddleware);

        $studioContentMiddleware = [AuthMiddleware::class, new PermissionMiddleware('content.manage')];
        foreach (['pages', 'projects', 'articles', 'highlights', 'testimonials', 'faq'] as $module) {
            $router->get('/studio/' . $module, 'StudioModulesController:content', 'studio.' . $module, $studioContentMiddleware);
            $router->get('/studio/' . $module . '/create/{id}', 'StudioModulesController:content', 'studio.' . $module . '.create', $studioContentMiddleware);
            $router->get('/studio/' . $module . '/edit/{id}', 'StudioModulesController:content', 'studio.' . $module . '.edit', $studioContentMiddleware);
            $router->post('/studio/' . $module, 'StudioModulesController:content', 'studio.' . $module . '.save', $studioContentMiddleware);
        }
        $router->get('/studio/content/preview/{id}', 'StudioModulesController:preview', 'studio.content.preview', $studioContentMiddleware);
        $router->get('/studio/categories', 'StudioModulesController:categories', 'studio.categories', $studioContentMiddleware);
        $router->post('/studio/categories', 'StudioModulesController:categories', 'studio.categories.save', $studioContentMiddleware);
        $router->get('/studio/tags', 'StudioModulesController:tags', 'studio.tags', $studioContentMiddleware);
        $router->post('/studio/tags', 'StudioModulesController:tags', 'studio.tags.save', $studioContentMiddleware);
        $router->get('/studio/trash', 'StudioModulesController:trash', 'studio.trash', $studioContentMiddleware);
        $router->post('/studio/trash', 'StudioModulesController:trash', 'studio.trash.action', $studioContentMiddleware);
        $router->get('/studio/menus', 'StudioModulesController:menus', 'studio.menus', $studioContentMiddleware);
        $router->post('/studio/menus', 'StudioModulesController:menus', 'studio.menus.save', $studioContentMiddleware);
        $mediaMiddleware = [AuthMiddleware::class, new PermissionMiddleware('media.manage')];
        $proposalMiddleware = [AuthMiddleware::class, new PermissionMiddleware('proposals.manage')];
        $notificationMiddleware = [AuthMiddleware::class, new PermissionMiddleware('notifications.manage')];
        $reportMiddleware = [AuthMiddleware::class, new PermissionMiddleware('reports.view')];
        $router->get('/studio/media', 'StudioModulesController:media', 'studio.media', $mediaMiddleware);
        $router->post('/studio/media', 'StudioModulesController:media', 'studio.media.save', $mediaMiddleware);
        $router->get('/studio/media/library', 'StudioModulesController:mediaLibrary', 'studio.media.library', $studioContentMiddleware);
        $router->get('/studio/media/file/{id}', 'StudioModulesController:mediaFile', 'studio.media.file', $mediaMiddleware);
        $router->get('/studio/proposals', 'StudioOperationsController:proposals', 'studio.proposals', $proposalMiddleware);
        $router->post('/studio/proposals', 'StudioOperationsController:proposals', 'studio.proposals.save', $proposalMiddleware);
        $router->get('/studio/notifications', 'StudioOperationsController:notifications', 'studio.notifications', $notificationMiddleware);
        $router->post('/studio/notifications', 'StudioOperationsController:notifications', 'studio.notifications.read', $notificationMiddleware);
        $router->get('/studio/reports', 'StudioOperationsController:reports', 'studio.reports', $reportMiddleware);

        $router->get(
            '/studio/users/page/{page}',
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
            '/studio/settings',
            'SettingsController:index',
            'settings.index',
            $settingsMiddleware
        );

        $router->post(
            '/studio/settings',
            'SettingsController:update',
            'settings.update',
            $settingsMiddleware
        );

        $router->get(
            '/studio/diagnostics',
            'DiagnosticsController:index',
            'diagnostics.index',
            [
                AuthMiddleware::class,
                new PermissionMiddleware('diagnostics.view'),
            ]
        );

        // Compatibility only: every legacy GET is permanently redirected to
        // the canonical Studio route. Mutations remain exclusive to /studio.
        foreach ([
            '/admin', '/admin/search', '/admin/versions', '/admin/logs',
            '/admin/pages', '/admin/projects', '/admin/articles', '/admin/highlights',
            '/admin/testimonials', '/admin/faq', '/admin/media', '/admin/proposals',
            '/admin/notifications', '/admin/reports', '/admin/settings',
            '/admin/diagnostics', '/admin/users', '/admin/users/create',
        ] as $legacyStudioRoute) {
            $router->get($legacyStudioRoute, 'LegacyStudioController:redirect');
        }
        $router->get('/admin/users/edit/{id}', 'LegacyStudioController:redirect');
        $router->get('/admin/users/page/{page}', 'LegacyStudioController:redirect');
        $router->get('/admin/media/file/{id}', 'LegacyStudioController:redirect');

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
