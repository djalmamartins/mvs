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

                foreach (['payables', 'receivables', 'billing', 'bank-accounts', 'reconciliation'] as $page) {
                    $router->get(
                        '/erp/' . $page,
                        'PlatformController:erpPage',
                        'platform.erp.' . $page,
                        $middleware
                    );
                }

                $router->get('/erp/condominiums', 'ErpCondominiumController:index', 'platform.erp.condominiums', $middleware);
                $router->get('/erp/condominiums/new', 'ErpCondominiumController:new', 'platform.erp.condominiums.new', $middleware);
                $router->post('/erp/condominiums', 'ErpCondominiumController:create', 'platform.erp.condominiums.create', $middleware);
                $router->get('/erp/condominiums/{condominium_id}/edit', 'ErpCondominiumController:edit', 'platform.erp.condominiums.edit', $middleware);
                $router->post('/erp/condominiums/{condominium_id}/edit', 'ErpCondominiumController:update', 'platform.erp.condominiums.update', $middleware);
                $router->get('/erp/condominiums/{condominium_id}', 'ErpCondominiumController:show', 'platform.erp.condominiums.show', $middleware);

                $router->get('/erp/periods', 'ErpPeriodController:index', 'platform.erp.periods', $middleware);
                $router->get('/erp/periods/new', 'ErpPeriodController:new', 'platform.erp.periods.new', $middleware);
                $router->post('/erp/periods', 'ErpPeriodController:create', 'platform.erp.periods.create', $middleware);
                $router->get('/erp/periods/{period_id}', 'ErpPeriodController:show', 'platform.erp.periods.show', $middleware);

                $router->get('/erp/chart-of-accounts', 'ErpChartOfAccountsController:index', 'platform.erp.chart-of-accounts', $middleware);
                $router->get('/erp/chart-of-accounts/new', 'ErpChartOfAccountsController:newPlan', 'platform.erp.chart-of-accounts.new', $middleware);
                $router->post('/erp/chart-of-accounts', 'ErpChartOfAccountsController:createPlan', 'platform.erp.chart-of-accounts.create', $middleware);
                $router->get('/erp/chart-of-accounts/{plan_id}', 'ErpChartOfAccountsController:show', 'platform.erp.chart-of-accounts.show', $middleware);
                $router->get('/erp/chart-of-accounts/{plan_id}/accounts/new', 'ErpChartOfAccountsController:newAccount', 'platform.erp.chart-of-accounts.accounts.new', $middleware);
                $router->post('/erp/chart-of-accounts/{plan_id}/accounts', 'ErpChartOfAccountsController:createAccount', 'platform.erp.chart-of-accounts.accounts.create', $middleware);
                $router->get('/erp/chart-of-accounts/{plan_id}/accounts/{account_id}/edit', 'ErpChartOfAccountsController:editAccount', 'platform.erp.chart-of-accounts.accounts.edit', $middleware);
                $router->post('/erp/chart-of-accounts/{plan_id}/accounts/{account_id}/edit', 'ErpChartOfAccountsController:updateAccount', 'platform.erp.chart-of-accounts.accounts.update', $middleware);
                $router->get('/erp/chart-of-accounts/{plan_id}/accounts/{account_id}', 'ErpChartOfAccountsController:account', 'platform.erp.chart-of-accounts.accounts.show', $middleware);

                $router->get('/erp/suppliers', 'ErpSupplierController:index', 'platform.erp.suppliers', $middleware);
                $router->get('/erp/suppliers/new', 'ErpSupplierController:new', 'platform.erp.suppliers.new', $middleware);
                $router->post('/erp/suppliers', 'ErpSupplierController:create', 'platform.erp.suppliers.create', $middleware);
                $router->get('/erp/suppliers/{supplier_id}', 'ErpSupplierController:show', 'platform.erp.suppliers.show', $middleware);
                $router->post('/erp/suppliers/{supplier_id}/status', 'ErpSupplierController:updateStatus', 'platform.erp.suppliers.status', $middleware);
                $router->post('/erp/suppliers/{supplier_id}/condominiums', 'ErpSupplierController:addCondominium', 'platform.erp.suppliers.condominiums.create', $middleware);
                $router->post('/erp/suppliers/{supplier_id}/condominiums/{link_id}/close', 'ErpSupplierController:closeCondominium', 'platform.erp.suppliers.condominiums.close', $middleware);

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
