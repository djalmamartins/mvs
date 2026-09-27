<?php

declare(strict_types=1);

namespace Moves\Services\Platform;

use InvalidArgumentException;
use PDO;

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

    /** @param array<string,mixed> $data */
    public function save(int $tenantId, array $data, int $actorId, ?int $id = null): int
    {
        $name = trim((string) ($data['legal_name'] ?? ''));
        $taxId = preg_replace('/\D+/', '', (string) ($data['tax_id'] ?? '')) ?? '';
        if ($name === '' || ($taxId !== '' && strlen($taxId) !== 14)) {
            throw new InvalidArgumentException('Informe a razão social e um CNPJ válido.');
        }
        $administrator = $this->pdo->prepare('SELECT id FROM erp_administrators WHERE tenant_id=? AND status=\'active\' LIMIT 1');
        $administrator->execute([$tenantId]);
        $administratorId = (int) $administrator->fetchColumn();
        if ($administratorId < 1) {
            throw new InvalidArgumentException('Administradora ativa não encontrada.');
        }
        $params = [
            'administrator_id' => $administratorId, 'legal_name' => $name,
            'trade_name' => trim((string) ($data['trade_name'] ?? '')) ?: null,
            'tax_id' => $taxId, 'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'postal_code' => preg_replace('/\D+/', '', (string) ($data['postal_code'] ?? '')) ?: null,
            'street' => trim((string) ($data['street'] ?? '')) ?: null,
            'address_number' => trim((string) ($data['address_number'] ?? '')) ?: null,
            'complement' => trim((string) ($data['complement'] ?? '')) ?: null,
            'district' => trim((string) ($data['district'] ?? '')) ?: null,
            'city' => trim((string) ($data['city'] ?? '')) ?: null,
            'state' => strtoupper(trim((string) ($data['state'] ?? ''))) ?: null,
            'timezone' => trim((string) ($data['timezone'] ?? 'America/Sao_Paulo')),
            'status' => in_array(($data['status'] ?? 'active'), ['active','inactive'], true) ? (string) ($data['status'] ?? 'active') : 'active',
            'created_by' => $actorId,
        ];
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
        return $id;
    }

    private function belongsTo(int $id, int $tenantId): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM erp_condominiums c JOIN erp_administrators a ON a.id=c.administrator_id WHERE c.id=? AND a.tenant_id=?');
        $statement->execute([$id, $tenantId]);
        return (int) $statement->fetchColumn() === 1;
    }
}
