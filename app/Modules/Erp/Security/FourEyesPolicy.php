<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

/**
 * Deny-by-default segregation-of-duties policy for critical ERP operations.
 *
 * A critical action can only be approved by a distinct authenticated user.
 * Authorization for the action itself remains the responsibility of the
 * scoped access layer; this policy only enforces actor/approver separation.
 */
final class FourEyesPolicy
{
    public function allows(int $actorUserId, int $approverUserId): bool
    {
        if ($actorUserId <= 0 || $approverUserId <= 0) {
            return false;
        }

        return $actorUserId !== $approverUserId;
    }

    public function assertAllowed(int $actorUserId, int $approverUserId): void
    {
        if (!$this->allows($actorUserId, $approverUserId)) {
            throw new \DomainException('Critical ERP operation requires approval by a distinct user.');
        }
    }
}
