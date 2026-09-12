<?php

declare(strict_types=1);

namespace Moves\Middleware;

use Moves\Core\Auth;
use Moves\Core\Response;
use MovesCode\Middleware\MiddlewareInterface;

/**
 * Moves | Guest Middleware
 *
 * Restringe determinadas rotas a usuários
 * que ainda não estejam autenticados.
 *
 * @author Djalma Martins
 * @package Moves\Middleware
 */
final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(callable $next): mixed
    {
        if (Auth::check()) {
            Response::to('/app');
        }

        return $next();
    }
}
