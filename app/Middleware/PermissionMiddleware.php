<?php

declare(strict_types=1);

namespace Moves\Middleware;

use Moves\Core\Access;
use Moves\Core\Response;
use MovesCode\Middleware\MiddlewareInterface;

/**
 * Moves | Permission Middleware
 *
 * Restringe o acesso às rotas que exigem
 * uma permissão específica.
 *
 * @author Djalma Martins
 * @package Moves\Middleware
 */
final class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private string $permission
    )
    {
    }

    public function handle(callable $next): mixed
    {
        if (!Access::can($this->permission)) {
            Response::to('/');
        }

        return $next();
    }
}