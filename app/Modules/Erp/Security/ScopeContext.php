<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

/**
 * Resolves ERP scope only from server-controlled route attributes.
 *
 * Request payload/query values must never be passed here as authorization
 * context. Missing or malformed attributes are denied by returning null.
 */
final class ScopeContext
{
    /** @param array<string, mixed> $routeAttributes */
    public static function fromRoute(array $routeAttributes): ?AccessScope
    {
        $type = $routeAttributes['scope_type'] ?? null;
        $id = $routeAttributes['scope_id'] ?? null;

        if (!is_string($type) || (!is_int($id) && !(is_string($id) && ctype_digit($id)))) {
            return null;
        }

        $scopeId = (int) $id;
        if ($scopeId <= 0) {
            return null;
        }

        try {
            return new AccessScope($type, $scopeId);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
