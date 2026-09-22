<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

/**
 * Applies segregation-of-duties and records the approval before a caller
 * executes a critical ERP operation.
 */
final readonly class FourEyesApprovalService
{
    public function __construct(
        private FourEyesPolicy $policy,
        private SecurityAuditRepository $audit
    ) {
    }

    public function approve(
        string $operation,
        int $actorUserId,
        int $approverUserId,
        ?int $subjectUserId = null
    ): void {
        $operation = trim($operation);
        if ($operation === '') {
            throw new \InvalidArgumentException('Critical ERP operation is required.');
        }

        if ($subjectUserId !== null && $subjectUserId <= 0) {
            throw new \InvalidArgumentException('Invalid critical ERP operation subject.');
        }

        $this->policy->assertAllowed($actorUserId, $approverUserId);

        $this->audit->append(
            'security.four_eyes.approved',
            $approverUserId,
            $subjectUserId,
            [
                'operation' => $operation,
                'initiator_user_id' => $actorUserId,
            ]
        );
    }
}
