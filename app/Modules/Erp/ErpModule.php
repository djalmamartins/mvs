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
                $middleware = [
                    AuthMiddleware::class,
                    new PermissionMiddleware('erp.access'),
                ];

                foreach (['payables', 'receivables', 'billing', 'condominiums', 'bank-accounts', 'reconciliation'] as $page) {
                    $router->get(
                        '/erp/' . $page,
                        'PlatformController:erpPage',
                        'platform.erp.' . $page,
                        $middleware
                    );
                }

                $router->get('/erp/people', 'ErpPeopleController:index', 'platform.erp.people', $middleware);
                $router->get('/erp/people/new', 'ErpPeopleController:new', 'platform.erp.people.new', $middleware);
                $router->post('/erp/people', 'ErpPeopleController:create', 'platform.erp.people.create', $middleware);
                $router->get('/erp/people/{person_id}', 'ErpPeopleController:show', 'platform.erp.people.show', $middleware);
                $router->post('/erp/people/{person_id}/links', 'ErpPeopleController:addLink', 'platform.erp.people.links.create', $middleware);
                $router->post('/erp/people/{person_id}/links/{link_id}/close', 'ErpPeopleController:closeLink', 'platform.erp.people.links.close', $middleware);
                $router->get('/erp/units', 'ErpPeopleController:units', 'platform.erp.units', $middleware);
                $router->get('/erp/units/new', 'ErpPeopleController:newUnit', 'platform.erp.units.new', $middleware);
                $router->post('/erp/units', 'ErpPeopleController:createUnit', 'platform.erp.units.create', $middleware);
                $router->get('/erp/units/{unit_id}', 'ErpPeopleController:showUnit', 'platform.erp.units.show', $middleware);

                $router->get(
                    '/api/v1/erp/status',
                    'Api\\V1\\ErpStatusController:index',
                    'api.v1.erp.status',
                    $middleware
                );

                $router->get(
                    '/api/v1/erp/administrators/{administrator_id}/condominiums',
                    'Api\\V1\\ErpCadastrosController:condominiums',
                    'api.v1.erp.condominiums.index',
                    $middleware
                );
            },
            [
                'admin' => ['erp.access'],
            ]
        );
    }
}
