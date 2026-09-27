<?php

declare(strict_types=1);

namespace Moves\Middleware;

use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Core\HttpException;
use Moves\Services\Platform\ProductEntitlement;
use Moves\Services\Platform\TenantContext;
use MovesCode\Middleware\MiddlewareInterface;

final readonly class ProductAccessMiddleware implements MiddlewareInterface
{
    public function __construct(private string $product)
    {
    }

    public function handle(callable $next): mixed
    {
        $user = Auth::user();
        if ($user === null) {
            throw new HttpException(401, 'Autenticação necessária.');
        }
        $pdo = Connection::getInstance();
        $tenantId = (new TenantContext($pdo))->currentId((int) $user->id);
        if (!(new ProductEntitlement($pdo))->enabled($tenantId, $this->product)) {
            throw new HttpException(403, 'Produto não habilitado para esta administradora.');
        }
        return $next();
    }
}
