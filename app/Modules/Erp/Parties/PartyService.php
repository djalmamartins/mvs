<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Parties;

use Moves\Modules\Erp\Security\ScopedAccess;
use Moves\Modules\Erp\Security\SecurityAuditRepository;

final readonly class PartyService
{
    public function __construct(
        private PartyRepository $parties,
        private ScopedAccess $access,
        private ?SecurityAuditRepository $audit = null,
    ) {}

    public function create(int $userId, int $condominiumId, string $kind, string $legalName, ?string $tradeName = null, ?string $taxId = null, ?string $email = null, ?string $phone = null): ?int
    {
        if (!$this->access->allows($userId, 'erp.parties.write', ['scope_type' => 'condominium', 'scope_id' => $condominiumId])) {
            return null;
        }
        $partyId = $this->parties->create($condominiumId, $kind, $legalName, $tradeName, $taxId, $email, $phone);
        $this->audit?->append('erp.party.created', $userId, null, [
            'party_id' => $partyId,
            'condominium_id' => $condominiumId,
            'kind' => strtolower(trim($kind)),
        ]);
        return $partyId;
    }

    /** @return list<array<string, mixed>> */
    public function list(int $userId, int $condominiumId): array
    {
        if (!$this->access->allows($userId, 'erp.parties.read', ['scope_type' => 'condominium', 'scope_id' => $condominiumId])) {
            return [];
        }
        return $this->parties->listByCondominium($condominiumId);
    }
}