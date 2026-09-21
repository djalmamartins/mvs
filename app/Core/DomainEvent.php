<?php

declare(strict_types=1);

namespace Moves\Core;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Immutable domain-event envelope shared by ERP application services.
 */
final readonly class DomainEvent
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $name,
        public string $aggregateType,
        public string $aggregateId,
        public array $payload,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable('now', new DateTimeZone('UTC')),
    ) {
        if ($name === '' || $aggregateType === '' || $aggregateId === '') {
            throw new InvalidArgumentException('Domain event identity cannot be empty.');
        }
    }

    /** @return array{name: string, aggregate_type: string, aggregate_id: string, payload: array<string, mixed>, occurred_at: string} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'aggregate_type' => $this->aggregateType,
            'aggregate_id' => $this->aggregateId,
            'payload' => $this->payload,
            'occurred_at' => $this->occurredAt->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
        ];
    }
}
