<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Services\Platform\TenantContext;

final class TenantController extends Controller
{
    public function switch(): void
    {
        $user = Auth::user();
        if ($user === null || !Csrf::validate((string) Request::post('_token', ''))) {
            Flash::set('error', 'Não foi possível trocar de administradora.');
            Response::to('/day');
        }
        (new TenantContext(Connection::getInstance()))->switch((int) $user->id, (int) Request::post('tenant_id', 0));
        Flash::set('success', 'Administradora ativa alterada.');
        Response::to('/day');
    }
}
