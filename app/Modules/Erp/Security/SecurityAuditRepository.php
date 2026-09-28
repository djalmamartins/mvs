<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

use PDO;

/**
 * Append-only audit sink for security-sensitive ERP events.
 * This repository intentionally exposes no update/delete operation.
 */
final readonly class SecurityAuditRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string, scalar|null> $metadata */
    public function append(
        string $eventType,
        ?int $actorUserId,
        ?int $subjectUserId,
        array $metadata = []
    ): void {
        $eventType = trim($eventType);
        if ($eventType === '') {
            throw new \InvalidArgumentException('Security audit event type is required.');
        }

        if ($actorUserId !== null && $actorUserId <= 0) {
            throw new \InvalidArgumentException('Invalid security audit actor.');
        }

        if ($subjectUserId !== null && $subjectUserId <= 0) {
            throw new \InvalidArgumentException('Invalid security audit subject.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO erp_security_audit
                (event_type, actor_user_id, subject_user_id, metadata_json, created_at)
             VALUES
                (:event_type, :actor_user_id, :subject_user_id, :metadata_json, CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'event_type' => $eventType,
            'actor_user_id' => $actorUserId,
            'subject_user_id' => $subjectUserId,
            'metadata_json' => $metadata === []
                ? null
                : json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
