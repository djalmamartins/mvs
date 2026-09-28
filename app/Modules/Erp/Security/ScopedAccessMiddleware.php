<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

use Moves\Core\HttpException;
use MovesCode\Middleware\MiddlewareInterface;

/**
 * ERP-only authorization boundary for HTTP routes.
 *
 * Authentication and route attributes are supplied by server-controlled
 * resolvers so request payload/query data cannot become authorization scope.
 */
final readonly class ScopedAccessMiddleware implements MiddlewareInterface
{
    /**
     * @param \Closure(): ?int $authenticatedUserId
     * @param \Closure(): array<string, mixed> $routeAttributes
     */
    public function __construct(
        private ScopedAccess $access,
        private string $capability,
        private \Closure $authenticatedUserId,
        private \Closure $routeAttributes
    ) {
    }

    public function handle(callable $next): mixed
    {
        $userId = ($this->authenticatedUserId)();
        $attributes = ($this->routeAttributes)();

        if (!is_int($userId) || !$this->access->allows($userId, $this->capability, $attributes)) {
            throw new HttpException(403, 'Acesso negado ao escopo solicitado.');
        }

        return $next();
    }
}
