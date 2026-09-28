<?php

declare(strict_types=1);

namespace Moves\Middleware;

use Moves\Core\Flash;
use Moves\Core\Response;
use Moves\Services\Master\TenantProductAccess;
use MovesCode\Middleware\MiddlewareInterface;

final class ProductEntitlementMiddleware implements MiddlewareInterface
{
    public function __construct(private string $productKey){}

    public function handle(callable $next): mixed
    {
        if(!TenantProductAccess::enabled($this->productKey)){
            Flash::set('warning','Este produto não está contratado ou ativo para a administradora selecionada.');
            Response::to('/app');
        }
        return $next();
    }
}
