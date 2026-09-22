<?php

declare(strict_types=1);

namespace Moves\Controllers\Api\V1;

use Moves\Boot\Connection;
use Moves\Core\ApiResponse;
use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Response;
use Moves\Modules\Erp\Cadastros\AdministratorRepository;
use Moves\Modules\Erp\Cadastros\CadastroAccessService;
use Moves\Modules\Erp\Cadastros\CondominiumRepository;
use Moves\Modules\Erp\Security\ScopeGrantRepository;
use Moves\Modules\Erp\Security\ScopedAccess;

/**
 * Versioned HTTP boundary for ERP cadastros.
 *
 * Authorization remains delegated to CadastroAccessService so the controller
 * cannot bypass the canonical scoped-access rules.
 */
final class ErpCadastrosController extends Controller
{
    /** @param array<string, mixed> $data */
    public function condominiums(array $data): never
    {
        $user = Auth::user();
        $administratorId = (int) ($data['administrator_id'] ?? 0);

        if ($user === null || $administratorId <= 0) {
            Response::json(ApiResponse::error('invalid_scope', 'Invalid administrator scope.'), 400);
        }

        $pdo = Connection::getInstance();
        $service = new CadastroAccessService(
            new AdministratorRepository($pdo),
            new CondominiumRepository($pdo),
            new ScopedAccess(new ScopeGrantRepository($pdo)),
        );

        Response::json(ApiResponse::data(
            $service->listCondominiums((int) $user->id, $administratorId)
        ));
    }
}
