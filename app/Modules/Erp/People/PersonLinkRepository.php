<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\People;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final readonly class PersonLinkRepository
{
    public const ROLES = ['owner','tenant','resident','manager','deputy_manager','council','proxy'];

    public function __construct(private PDO $pdo)
    {
    }

    public function create(int $administratorId, int $actorId, int $personId, int $condominiumId, ?int $unitId, string $role, string $startsAt, ?string $endsAt, string $source = 'staff', ?string $ownershipFractionPct = null): int
    {
        self::assertDate($startsAt, 'Data inicial');
        if ($endsAt !== null) {
            self::assertDate($endsAt, 'Data final');
            if ($endsAt < $startsAt) {
                throw new InvalidArgumentException('A data final não pode anteceder a data inicial.');
            }
        }
        if (!in_array($role, self::ROLES, true)) {
            throw new InvalidArgumentException('Selecione um tipo de vínculo válido.');
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO erp_person_links(administrator_id,person_id,condominium_id,unit_id,role,ownership_fraction_pct,starts_at,ends_at,status,source,created_by_user_id)
             VALUES(:administrator_id,:person_id,:condominium_id,:unit_id,:role,:ownership_fraction_pct,:starts_at,:ends_at,:status,:source,:actor_id)'
        );
        $statement->execute([
            'administrator_id'=>$administratorId,
            'person_id'=>$personId,
            'condominium_id'=>$condominiumId,
            'unit_id'=>$unitId,
            'role'=>$role,
            'ownership_fraction_pct'=>$ownershipFractionPct,
            'starts_at'=>$startsAt,
            'ends_at'=>$endsAt,
            'status'=>$endsAt === null || $endsAt >= date('Y-m-d') ? 'active' : 'inactive',
            'source'=>$source,
            'actor_id'=>$actorId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function end(int $administratorId, int $actorId, int $linkId, string $endsAt): bool
    {
        self::assertDate($endsAt, 'Data de encerramento');
        $statement = $this->pdo->prepare(
            "UPDATE erp_person_links SET ends_at=:ends_at,status='inactive',ended_by_user_id=:actor_id
             WHERE administrator_id=:administrator_id AND id=:id AND status='active' AND starts_at<=:start_compare AND (ends_at IS NULL OR ends_at>=:end_compare)"
        );
        $statement->execute([
            'ends_at'=>$endsAt,
            'actor_id'=>$actorId,
            'administrator_id'=>$administratorId,
            'id'=>$linkId,
            'start_compare'=>$endsAt,
            'end_compare'=>$endsAt,
        ]);
        return $statement->rowCount() === 1;
    }

    /** @param list<int> $personIds @return list<array<string,mixed>> */
    public function forPeople(int $administratorId, array $personIds): array
    {
        if ($personIds === []) {
            return [];
        }
        $slots = [];
        $params = ['administrator_id'=>$administratorId];
        foreach (array_values(array_unique($personIds)) as $index => $personId) {
            $key = 'person_' . $index;
            $slots[] = ':' . $key;
            $params[$key] = $personId;
        }
        $statement = $this->pdo->prepare($this->detailsSelect() .
            " WHERE l.administrator_id=:administrator_id AND l.person_id IN (" . implode(',', $slots) . ') ORDER BY l.starts_at DESC,l.id DESC'
        );
        $statement->execute($params);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string,mixed>> */
    public function forPerson(int $administratorId, int $personId): array
    {
        $statement = $this->pdo->prepare($this->detailsSelect() .
            ' WHERE l.administrator_id=:administrator_id AND l.person_id=:person_id ORDER BY l.starts_at DESC,l.id DESC'
        );
        $statement->execute(['administrator_id'=>$administratorId,'person_id'=>$personId]);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string,mixed>> */
    public function forUnit(int $administratorId, int $unitId): array
    {
        $statement = $this->pdo->prepare($this->detailsSelect() .
            ' WHERE l.administrator_id=:administrator_id AND l.unit_id=:unit_id ORDER BY l.starts_at DESC,l.id DESC'
        );
        $statement->execute(['administrator_id'=>$administratorId,'unit_id'=>$unitId]);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function detailsSelect(): string
    {
        return "SELECT l.id,l.administrator_id,l.person_id,l.condominium_id,l.unit_id,l.role,l.ownership_fraction_pct,l.starts_at,l.ends_at,l.status,l.source,l.created_by_user_id,l.ended_by_user_id,
                       c.legal_name AS condominium_legal_name,c.trade_name AS condominium_name,u.code AS unit_code,u.complement AS unit_complement,b.name AS block_name,
                       p.entity_type,p.full_name,p.trade_name AS person_trade_name,p.document_type,p.document_number,p.email,p.phone,
                       CASE WHEN l.status='active' AND l.starts_at<=CURRENT_DATE AND (l.ends_at IS NULL OR l.ends_at>=CURRENT_DATE) THEN 1 ELSE 0 END AS is_current
                FROM erp_person_links l
                JOIN erp_condominiums c ON c.id=l.condominium_id
                JOIN erp_administrators a ON a.id=c.administrator_id AND a.id=l.administrator_id
                JOIN erp_people p ON p.id=l.person_id AND p.administrator_id=l.administrator_id
                LEFT JOIN erp_units u ON u.id=l.unit_id AND u.condominium_id=l.condominium_id
                LEFT JOIN erp_blocks b ON b.id=u.block_id AND b.condominium_id=u.condominium_id";
    }

    public static function assertDate(string $value, string $field): void
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException($field . ' deve usar o formato AAAA-MM-DD.');
        }
    }
}
