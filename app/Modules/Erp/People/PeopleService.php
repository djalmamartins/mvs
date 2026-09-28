<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\People;

use Moves\Modules\Erp\Security\ScopedAccess;
use Moves\Modules\Erp\Security\SecurityAuditRepository;

final readonly class PeopleService
{
    public function __construct(
        private PersonRepository $people,
        private PersonLinkRepository $links,
        private ScopedAccess $access,
        private ?SecurityAuditRepository $audit = null,
    ) {}

    public function createPerson(int $userId, int $condominiumId, string $fullName, ?string $documentType = null, ?string $documentNumber = null, ?string $email = null, ?string $phone = null): ?int
    {
        if (!$this->access->allows($userId, 'erp.people.write', ['scope_type' => 'condominium', 'scope_id' => $condominiumId])) {
            return null;
        }
        if (trim($fullName) === '') {
            throw new \InvalidArgumentException('Person full name is required.');
        }
        $personId = $this->people->create($fullName, $documentType, $documentNumber, $email, $phone);
        $this->audit?->append('erp.people.person.created_or_reused', $userId, null, [
            'person_id' => $personId,
            'condominium_id' => $condominiumId,
        ]);
        return $personId;
    }

    public function createLink(int $userId, int $personId, int $condominiumId, ?int $unitId, string $role, string $startsAt, ?string $endsAt = null): ?int
    {
        if (!$this->access->allows($userId, 'erp.people.write', ['scope_type' => 'condominium', 'scope_id' => $condominiumId])) {
            return null;
        }
        if ($this->people->find($personId) === null) {
            throw new \InvalidArgumentException('Person not found.');
        }

        $linkId = $this->links->create($personId, $condominiumId, $unitId, $role, $startsAt, $endsAt);
        $this->audit?->append('erp.people.link.created', $userId, null, [
            'person_id' => $personId,
            'condominium_id' => $condominiumId,
            'link_id' => $linkId,
            'role' => strtolower(trim($role)),
        ]);
        return $linkId;
    }

    public function endLink(int $userId, int $linkId, int $condominiumId, string $endsAt): bool
    {
        if (!$this->access->allows($userId, 'erp.people.write', ['scope_type' => 'condominium', 'scope_id' => $condominiumId])) {
            return false;
        }
        $ended = $this->links->end($linkId, $condominiumId, $endsAt);
        if ($ended) {
            $this->audit?->append('erp.people.link.ended', $userId, null, [
                'link_id' => $linkId,
                'condominium_id' => $condominiumId,
                'ends_at' => $endsAt,
            ]);
        }
        return $ended;
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