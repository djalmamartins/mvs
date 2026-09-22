<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Cadastros;

use Moves\Modules\Erp\Security\ScopedAccess;
use Moves\Modules\Erp\Security\SecurityAuditRepository;

/**
 * Application boundary for security-sensitive ERP cadastro writes.
 *
 * Repositories remain persistence-only. Authorization and the append-only
 * audit trail are enforced here before/after the mutation respectively.
 */
final readonly class CadastroWriteService
{
    public function __construct(
        private AdministratorRepository $administrators,
        private CondominiumRepository $condominiums,
        private ScopedAccess $access,
        private SecurityAuditRepository $audit,
    ) {
    }

    public function createCondominium(
        int $actorUserId,
        int $administratorId,
        string $legalName,
        ?string $tradeName,
        string $taxId,
        string $timezone = 'America/Sao_Paulo',
    ): ?int {
        if (!$this->access->allows($actorUserId, 'erp.cadastros.write', [
            'scope_type' => 'administrator',
            'scope_id' => $administratorId,
        ])) {
            return null;
        }

        if ($this->administrators->find($administratorId) === null) {
            return null;
        }

        $condominiumId = $this->condominiums->create(
            $administratorId,
            $legalName,
            $tradeName,
            $taxId,
            $timezone,
        );

        $this->audit->append('erp.cadastros.condominium.created', $actorUserId, null, [
            'administrator_id' => $administratorId,
            'condominium_id' => $condominiumId,
        ]);

        return $condominiumId;
    }
}
