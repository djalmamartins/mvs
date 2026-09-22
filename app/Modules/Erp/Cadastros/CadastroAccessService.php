<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Cadastros;

use Moves\Modules\Erp\Security\ScopedAccess;

/**
 * Application boundary for ERP cadastro reads.
 *
 * Authorization stays outside repositories so callers cannot accidentally
 * treat a successful database lookup as an authorization decision.
 */
final readonly class CadastroAccessService
{
    public function __construct(
        private AdministratorRepository $administrators,
        private CondominiumRepository $condominiums,
        private ScopedAccess $access,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function findAdministrator(int $userId, int $administratorId): ?array
    {
        if (!$this->access->allows($userId, 'erp.cadastros.read', [
            'administrator_id' => $administratorId,
        ])) {
            return null;
        }

        return $this->administrators->find($administratorId);
    }

    /** @return array<string, mixed>|null */
    public function findCondominium(int $userId, int $administratorId, int $condominiumId): ?array
    {
        if (!$this->access->allows($userId, 'erp.cadastros.read', [
            'administrator_id' => $administratorId,
            'condominium_id' => $condominiumId,
        ])) {
            return null;
        }

        $condominium = $this->condominiums->find($condominiumId);
        if ($condominium === null || (int) $condominium['administrator_id'] !== $administratorId) {
            return null;
        }

        return $condominium;
    }

    /** @return list<array<string, mixed>> */
    public function listCondominiums(int $userId, int $administratorId): array
    {
        if (!$this->access->allows($userId, 'erp.cadastros.read', [
            'administrator_id' => $administratorId,
        ])) {
            return [];
        }

        return $this->condominiums->listByAdministrator($administratorId);
    }
}
