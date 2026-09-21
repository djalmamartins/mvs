<?php

declare(strict_types=1);

namespace Moves\Modules\Erp;

use Moves\Boot\Modules;
use Moves\Middleware\AuthMiddleware;
use Moves\Middleware\PermissionMiddleware;
use MovesCode\Router\Router;

/**
 * ERP module bootstrap.
 *
 * Keeps ERP routes and permissions outside the global route registry so the
 * condominium domain can evolve without coupling Core or Studio to ERP.
 */
final class ErpModule
{
    public static function register(): void
    {
        Modules::register(
            'erp',
            static function (Router $router): void {
                $router->get(
                    '/api/v1/erp/status',
                    'Api\\V1\\ErpStatusController:index',
                    'api.v1.erp.status',
                    [
                        AuthMiddleware::class,
                        new PermissionMiddleware('erp.access'),
                    ]
                );
            },
            [
                'admin' => ['erp.access'],
            ]
        );
    }
}
