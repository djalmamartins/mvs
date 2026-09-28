<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\People;

use Moves\Modules\Erp\Security\ScopedAccess;

final readonly class PeopleService
{
    public function __construct(
        private PersonRepository $people,
        private PersonLinkRepository $links,
        private ScopedAccess $access,
    ) {}

    public function createLink(int $userId, int $personId, int $condominiumId, ?int $unitId, string $role, string $startsAt, ?string $endsAt = null): ?int
    {
        if (!$this->access->allows($userId, 'erp.people.write', ['scope_type' => 'condominium', 'scope_id' => $condominiumId])) {
            return null;
        }
        if ($this->people->find($personId) === null) {
            throw new \InvalidArgumentException('Person not found.');
        }
        return $this->links->create($personId, $condominiumId, $unitId, $role, $startsAt, $endsAt);
    }

    /** @return list<array<string, mixed>> */
    public function timeline(int $userId, int $personId, int $condominiumId): array
    {
        if (!$this->access->allows($userId, 'erp.people.read', ['scope_type' => 'condominium', 'scope_id' => $condominiumId])) {
            return [];
        }
        return $this->links->timeline($personId, $condominiumId);
    }
}