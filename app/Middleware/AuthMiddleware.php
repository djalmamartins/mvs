<?php

declare(strict_types=1);

namespace Moves\Middleware;

use Moves\Core\Auth;
use Moves\Core\Response;
use MovesCode\Middleware\MiddlewareInterface;

/**
 * Moves | Auth Middleware
 *
 * Restringe o acesso às rotas que exigem autenticação.
 *
 * @author Djalma Martins
 * @package Moves\Middleware
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(callable $next): mixed
    {
        if (!Auth::check()) {
            Response::to('/login');
        }

        return $next();
    }
}
