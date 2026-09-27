<?php

declare(strict_types=1);

namespace Moves\Services\Platform;

use PDO;

final readonly class PlatformAudit
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string,mixed> $metadata */
    public function record(?int $tenantId, ?int $actorId, string $event, ?string $subjectType = null, ?int $subjectId = null, array $metadata = []): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO platform_audit_events(tenant_id,actor_user_id,event_type,subject_type,subject_id,metadata)
             VALUES(:tenant_id,:actor_id,:event_type,:subject_type,:subject_id,:metadata)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'actor_id' => $actorId,
            'event_type' => $event,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }
}
