<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Cadastros;

use Moves\Modules\Erp\Security\ScopedAccess;

/**
 * Application boundary for ERP cadastros.
 *
 * Authorization context must come from server-controlled route attributes.
 * This service deliberately denies before touching repositories.
 */
final readonly class CadastrosService
{
    public function __construct(
        private AdministratorRepository $administrators,
        private CondominiumRepository $condominiums,
        private ScopedAccess $access
    ) {
    }

    /** @param array<string, mixed> $routeAttributes
     *  @return array<string, mixed>|null
     */
    public function findAdministrator(int $userId, int $administratorId, array $routeAttributes): ?array
    {
        if ($administratorId <= 0 || !$this->access->allows($userId, 'erp.cadastros.read', $routeAttributes)) {
            return null;
        }

        $administrator = $this->administrators->find($administratorId);
        if ($administrator === null) {
            return null;
        }

        return $this->routeMatchesAdministrator($routeAttributes, $administratorId) ? $administrator : null;
    }

    /** @param array<string, mixed> $routeAttributes
     *  @return array<string, mixed>|null
     */
    public function findCondominium(int $userId, int $condominiumId, array $routeAttributes): ?array
    {
        if ($condominiumId <= 0 || !$this->access->allows($userId, 'erp.cadastros.read', $routeAttributes)) {
            return null;
        }

        $condominium = $this->condominiums->find($condominiumId);
        if ($condominium === null) {
            return null;
        }

        $scopeType = $routeAttributes['scope_type'] ?? null;
        $scopeId = (int) ($routeAttributes['scope_id'] ?? 0);

        if ($scopeType === 'condominium') {
            return $scopeId === $condominiumId ? $condominium : null;
        }

        if ($scopeType === 'administrator') {
            return $scopeId === (int) $condominium['administrator_id'] ? $condominium : null;
        }

        return null;
    }

    /** @param array<string, mixed> $routeAttributes */
    private function routeMatchesAdministrator(array $routeAttributes, int $administratorId): bool
    {
        return ($routeAttributes['scope_type'] ?? null) === 'administrator'
            && (int) ($routeAttributes['scope_id'] ?? 0) === $administratorId;
    }
}
