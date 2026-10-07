<?php

declare(strict_types=1);

namespace Moves\Services\Platform;

use InvalidArgumentException;
use PDO;
use Throwable;

final readonly class CompanyService
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string,mixed> $data */
    public function create(array $data, int $ownerUserId, array $products): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $legalName = trim((string) ($data['legal_name'] ?? $name));
        $taxId = $this->taxId((string) ($data['tax_id'] ?? ''));
        if ($name === '' || $legalName === '') {
            throw new InvalidArgumentException('Informe o nome da administradora.');
        }

        $started = !$this->pdo->inTransaction();
        if ($started) {
            $this->pdo->beginTransaction();
        }
        try {
            $insert = $this->pdo->prepare(
                'INSERT INTO talk_tenants(name,slug,legal_name,tax_id,email,phone,postal_code,street,address_number,complement,district,city,state,timezone,status,created_by)
                 VALUES(:name,:slug,:legal_name,:tax_id,:email,:phone,:postal_code,:street,:address_number,:complement,:district,:city,:state,:timezone,\'active\',:created_by)'
            );
            $insert->execute($this->companyParams($data, $name, $legalName, $taxId) + ['slug' => 'tenant-' . bin2hex(random_bytes(12)), 'created_by' => $ownerUserId]);
            $tenantId = (int) $this->pdo->lastInsertId();

            $this->ensureRoles($tenantId);
            $role = $this->pdo->prepare("SELECT id FROM platform_roles WHERE tenant_id=? AND slug='owner'");
            $role->execute([$tenantId]);
            $roleId = (int) $role->fetchColumn();
            $membership = $this->pdo->prepare(
                "INSERT INTO talk_tenant_users(tenant_id,user_id,role,role_id,status,is_default) VALUES(?,?,'admin',?,'active',1)"
            );
            $membership->execute([$tenantId, $ownerUserId, $roleId]);

            foreach (ProductEntitlement::PRODUCTS as $product) {
                $enabled = in_array($product, $products, true);
                $this->pdo->prepare('INSERT INTO platform_tenant_products(tenant_id,product,enabled) VALUES(?,?,?)')
                    ->execute([$tenantId, $product, $enabled ? 1 : 0]);
            }

            $administrator = $this->pdo->prepare(
                'INSERT INTO erp_administrators(tenant_id,legal_name,trade_name,tax_id,status) VALUES(?,?,?,?,\'active\')'
            );
            $administrator->execute([$tenantId, $legalName, $name, $taxId ?? 'TENANT-' . $tenantId]);
            $administratorId = (int) $this->pdo->lastInsertId();
            $grant = $this->pdo->prepare(
                "INSERT INTO erp_scope_grants(user_id,capability,scope_type,scope_id)
                 VALUES(?,?,'administrator',?)"
            );
            foreach (['erp.cadastros.read', 'erp.cadastros.write'] as $capability) {
                $grant->execute([$ownerUserId, $capability, $administratorId]);
            }
            (new PlatformAudit($this->pdo))->record($tenantId, $ownerUserId, 'tenant.created', 'tenant', $tenantId);
            if ($started) {
                $this->pdo->commit();
            }
            return $tenantId;
        } catch (Throwable $exception) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed> $data */
    public function update(int $tenantId, array $data, int $actorId): void
    {
        $name = trim((string) ($data['name'] ?? ''));
        $legalName = trim((string) ($data['legal_name'] ?? $name));
        if ($name === '' || $legalName === '') {
            throw new InvalidArgumentException('Informe o nome da administradora.');
        }
        $statement = $this->pdo->prepare(
            'UPDATE talk_tenants SET name=:name,legal_name=:legal_name,tax_id=:tax_id,email=:email,phone=:phone,postal_code=:postal_code,street=:street,address_number=:address_number,complement=:complement,district=:district,city=:city,state=:state,timezone=:timezone WHERE id=:id'
        );
        $statement->execute($this->companyParams($data, $name, $legalName, $this->taxId((string) ($data['tax_id'] ?? ''))) + ['id' => $tenantId]);
        (new PlatformAudit($this->pdo))->record($tenantId, $actorId, 'tenant.updated', 'tenant', $tenantId);
    }

    /** @return array<string,mixed>|null */
    public function find(int $tenantId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM talk_tenants WHERE id=?');
        $statement->execute([$tenantId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function ensureRoles(int $tenantId): void
    {
        $roles = ['owner' => 'Proprietário', 'administrator' => 'Administrador', 'supervisor' => 'Supervisor', 'agent' => 'Atendente', 'operator' => 'Operacional'];
        $insert = $this->pdo->prepare('INSERT IGNORE INTO platform_roles(tenant_id,slug,name,is_system) VALUES(?,?,?,1)');
        foreach ($roles as $slug => $name) {
            $insert->execute([$tenantId, $slug, $name]);
        }
        $grant = $this->pdo->prepare(
            "INSERT IGNORE INTO platform_role_permissions(role_id,permission_id)
             SELECT r.id,p.id FROM platform_roles r CROSS JOIN platform_permissions p
             WHERE r.tenant_id=? AND (r.slug IN ('owner','administrator') OR
               (r.slug='supervisor' AND p.slug IN ('condominiums.read','talk.access','erp.access','erp.pending.manage','support.access')) OR
               (r.slug='agent' AND p.slug IN ('talk.access','support.access')) OR
               (r.slug='operator' AND p.slug IN ('condominiums.read','erp.access')))"
        );
        $grant->execute([$tenantId]);
    }

    private function taxId(string $value): ?string
    {
        $value = preg_replace('/\D+/', '', $value) ?? '';
        if ($value !== '' && strlen($value) !== 14) {
            throw new InvalidArgumentException('CNPJ deve conter 14 dígitos.');
        }
        return $value === '' ? null : $value;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function companyParams(array $data, string $name, string $legalName, ?string $taxId): array
    {
        return [
            'name' => $name, 'legal_name' => $legalName, 'tax_id' => $taxId,
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'postal_code' => preg_replace('/\D+/', '', (string) ($data['postal_code'] ?? '')) ?: null,
            'street' => trim((string) ($data['street'] ?? '')) ?: null,
            'address_number' => trim((string) ($data['address_number'] ?? '')) ?: null,
            'complement' => trim((string) ($data['complement'] ?? '')) ?: null,
            'district' => trim((string) ($data['district'] ?? '')) ?: null,
            'city' => trim((string) ($data['city'] ?? '')) ?: null,
            'state' => strtoupper(trim((string) ($data['state'] ?? ''))) ?: null,
            'timezone' => trim((string) ($data['timezone'] ?? 'America/Sao_Paulo')),
        ];
    }
}
