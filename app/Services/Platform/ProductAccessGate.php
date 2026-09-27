<?php

declare(strict_types=1);

namespace Moves\Services\Platform;

use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Core\HttpException;

final class ProductAccessGate
{
    public static function enforceCurrentRequest(): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $product = match (true) {
            $path === '/talk' || str_starts_with($path, '/talk/') => 'talk',
            $path === '/erp' || str_starts_with($path, '/erp/'), str_starts_with($path, '/api/v1/erp') => 'erp',
            $path === '/support' || str_starts_with($path, '/support/') => 'support',
            $path === '/studio' || str_starts_with($path, '/studio/') => 'studio',
            default => null,
        };
        if ($product === null) {
            return;
        }
        $pdo = Connection::getInstance();
        $tenantId = (new TenantContext($pdo))->currentId((int) $user->id);
        if (!(new ProductEntitlement($pdo))->enabled($tenantId, $product)) {
            throw new HttpException(403, 'Produto não habilitado para esta administradora.');
        }
    }
}
