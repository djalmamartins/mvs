<?php

declare(strict_types=1);

namespace Moves\Services\Platform;

use InvalidArgumentException;
use Moves\Services\Day\OperationalPendingService;
use PDO;
use Throwable;

final readonly class CondominiumService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.* FROM erp_condominiums c JOIN erp_administrators a ON a.id=c.administrator_id WHERE a.tenant_id=? ORDER BY c.legal_name,c.id'
        );
        $statement->execute([$tenantId]);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function canEdit(int $tenantId, int $administratorId, int $condominiumId, int $actorId): bool
    {
        $scopeId = $condominiumId > 0 ? $condominiumId : null;
        return $this->canWriteCondominium($tenantId, $administratorId, $scopeId, $actorId)
            && ($scopeId === null || $this->hasCondominiumAccess($tenantId, $administratorId, $scopeId, $actorId, 'condominiums.read', 'erp.cadastros.read'));
    }

    public function canView(int $tenantId, int $administratorId, int $condominiumId, int $actorId): bool
    {
        return $this->hasCondominiumAccess($tenantId, $administratorId, $condominiumId > 0 ? $condominiumId : null, $actorId, 'condominiums.read', 'erp.cadastros.read');
    }

    public function canList(int $tenantId, int $administratorId, int $actorId): bool
    {
        return $this->hasCondominiumAccess($tenantId, $administratorId, null, $actorId, 'condominiums.read', 'erp.cadastros.read');
    }

    /** @param array<string,mixed> $data */
    public function save(int $tenantId, array $data, int $actorId, ?int $id = null): int
    {
        $name = trim((string) ($data['legal_name'] ?? ''));
        $taxId = Cnpj::normalize((string) ($data['tax_id'] ?? ''));
        if ($name === '' || mb_strlen($name) > 190 || ($taxId !== null && !Cnpj::isValid($taxId))) {
            throw new InvalidArgumentException($taxId === null ? 'Informe a razão social.' : 'Informe um CNPJ válido.');
        }
        $email=trim((string)($data['email']??''));
        if($email!==''&&(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190))throw new InvalidArgumentException('Informe um e-mail de contato válido.');
        $state=strtoupper(trim((string)($data['state']??'')));
        if($state!==''&&!preg_match('/^[A-Z]{2}$/',$state))throw new InvalidArgumentException('Informe a UF com duas letras.');
        $administrator = $this->pdo->prepare('SELECT id FROM erp_administrators WHERE tenant_id=? AND status=\'active\' LIMIT 1');
        $administrator->execute([$tenantId]);
        $administratorId = (int) $administrator->fetchColumn();
        if ($administratorId < 1) {
            throw new InvalidArgumentException('Administradora ativa não encontrada.');
        }
        if ($id !== null) {
            $target = $this->pdo->prepare('SELECT c.administrator_id FROM erp_condominiums c JOIN erp_administrators a ON a.id=c.administrator_id WHERE c.id=:id AND a.tenant_id=:tenant AND a.status=\'active\'');
            $target->execute(['id' => $id, 'tenant' => $tenantId]);
            $targetAdministratorId = (int) $target->fetchColumn();
            if ($targetAdministratorId < 1) {
                throw new InvalidArgumentException('Condomínio não encontrado.');
            }
            $administratorId = $targetAdministratorId;
        }
        if (!$this->canWriteCondominium($tenantId, $administratorId, $id, $actorId)
            || ($id !== null && !$this->hasCondominiumAccess($tenantId, $administratorId, $id, $actorId, 'condominiums.read', 'erp.cadastros.read'))) {
            throw new InvalidArgumentException('Você não tem permissão para editar este cadastro.');
        }
        $params = [
            'administrator_id' => $administratorId, 'legal_name' => $name,
            'trade_name' => trim((string) ($data['trade_name'] ?? '')) ?: null,
            'tax_id' => $taxId, 'email' => $email ?: null,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'postal_code' => preg_replace('/\D+/', '', (string) ($data['postal_code'] ?? '')) ?: null,
            'street' => trim((string) ($data['street'] ?? '')) ?: null,
            'address_number' => trim((string) ($data['address_number'] ?? '')) ?: null,
            'complement' => trim((string) ($data['complement'] ?? '')) ?: null,
            'district' => trim((string) ($data['district'] ?? '')) ?: null,
            'city' => trim((string) ($data['city'] ?? '')) ?: null,
            'state' => $state ?: null,
            'timezone' => trim((string) ($data['timezone'] ?? 'America/Sao_Paulo')),
            'status' => in_array(($data['status'] ?? 'active'), ['active','inactive'], true) ? (string) ($data['status'] ?? 'active') : 'active',
            'created_by' => $actorId,
        ];
        $started = !$this->pdo->inTransaction();
        if ($started) {
            $this->pdo->beginTransaction();
        }
        try {
        if ($id === null) {
            $statement = $this->pdo->prepare('INSERT INTO erp_condominiums(administrator_id,legal_name,trade_name,tax_id,email,phone,postal_code,street,address_number,complement,district,city,state,timezone,status,created_by) VALUES(:administrator_id,:legal_name,:trade_name,:tax_id,:email,:phone,:postal_code,:street,:address_number,:complement,:district,:city,:state,:timezone,:status,:created_by)');
            $statement->execute($params);
            $id = (int) $this->pdo->lastInsertId();
        } else {
            $statement = $this->pdo->prepare('UPDATE erp_condominiums c JOIN erp_administrators a ON a.id=c.administrator_id SET c.legal_name=:legal_name,c.trade_name=:trade_name,c.tax_id=:tax_id,c.email=:email,c.phone=:phone,c.postal_code=:postal_code,c.street=:street,c.address_number=:address_number,c.complement=:complement,c.district=:district,c.city=:city,c.state=:state,c.timezone=:timezone,c.status=:status WHERE c.id=:id AND a.tenant_id=:tenant_id');
            $params['id'] = $id; $params['tenant_id'] = $tenantId;
            unset($params['administrator_id'], $params['created_by']);
            $statement->execute($params);
            if ($statement->rowCount() < 1 && !$this->belongsTo($id, $tenantId)) {
                throw new InvalidArgumentException('Condomínio não encontrado.');
            }
        }
        (new PlatformAudit($this->pdo))->record($tenantId, $actorId, 'condominium.saved', 'condominium', $id);
        (new OperationalPendingService($this->pdo))->evaluateCondominiumCnpj($tenantId, $administratorId, $id, $taxId, $actorId);
        if ($started) {
            $this->pdo->commit();
        }
        return $id;
        } catch (Throwable $exception) {
            if ($started) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function belongsTo(int $id, int $tenantId): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM erp_condominiums c JOIN erp_administrators a ON a.id=c.administrator_id WHERE c.id=? AND a.tenant_id=?');
        $statement->execute([$id, $tenantId]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function canWriteCondominium(int $tenantId, int $administratorId, ?int $condominiumId, int $actorId): bool
    {
        return $this->hasCondominiumAccess($tenantId, $administratorId, $condominiumId, $actorId, 'condominiums.manage', 'erp.cadastros.write');
    }

    private function hasCondominiumAccess(int $tenantId, int $administratorId, ?int $condominiumId, int $actorId, string $rolePermission, string $capability): bool
    {
        $scopeSql = $condominiumId === null
            ? "g.scope_type='administrator' AND g.scope_id=:scope_admin"
            : "((g.scope_type='administrator' AND g.scope_id=:scope_admin) OR (g.scope_type='condominium' AND g.scope_id=:scope_condominium))";
        $sql = "SELECT 1 FROM talk_tenant_users m JOIN platform_role_permissions rp ON rp.role_id=m.role_id JOIN platform_permissions p ON p.id=rp.permission_id AND p.slug=:role_permission JOIN erp_scope_grants g ON g.user_id=m.user_id AND g.capability=:capability AND g.revoked_at IS NULL JOIN erp_administrators a ON a.id=:administrator AND a.tenant_id=m.tenant_id AND a.status='active' WHERE m.tenant_id=:tenant AND m.user_id=:actor AND m.status='active' AND {$scopeSql} LIMIT 1";
        $statement = $this->pdo->prepare($sql);
        $params = ['administrator' => $administratorId, 'tenant' => $tenantId, 'actor' => $actorId, 'scope_admin' => $administratorId, 'role_permission' => $rolePermission, 'capability' => $capability];
        if ($condominiumId !== null) { $params['scope_condominium'] = $condominiumId; }
        $statement->execute($params);
        return $statement->fetchColumn() !== false;
    }

}
