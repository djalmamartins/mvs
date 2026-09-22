<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

/**
 * Single deny-by-default authorization gate for ERP requests.
 *
 * The required scope must already come from server-controlled route attributes.
 * Persisted grants are resolved by user id; request payload/query data is never
 * accepted here as authorization context.
 */
final readonly class AccessGate
{
    public function __construct(private ScopeGrantRepository $grants)
    {
    }

    public function allows(int $userId, string $capability, ?AccessScope $requiredScope): bool
    {
        if ($userId <= 0 || $requiredScope === null) {
            return false;
        }

        try {
            $grants = $this->grants->activeForUser($userId);
        } catch (\Throwable) {
            return false;
        }

        foreach ($grants as $grant) {
            if ($grant->allows($capability, $requiredScope)) {
                return true;
            }
        }

        return false;
    }
}
