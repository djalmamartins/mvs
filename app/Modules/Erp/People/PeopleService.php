<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\People;

use InvalidArgumentException;
use Moves\Modules\Erp\Structure\PhysicalStructureRepository;
use Moves\Services\Platform\PlatformAudit;
use PDO;

final readonly class PeopleService
{
    public function __construct(
        private PDO $pdo,
        private PersonRepository $people,
        private PersonLinkRepository $links,
        private PhysicalStructureRepository $structure,
        private PlatformAudit $audit,
    ) {
    }

    /** @param array<string,mixed> $data @return array{id:int,created:bool,link_id:?int} */
    public function create(int $tenantId, int $administratorId, int $actorId, array $data): array
    {
        $person = $this->normalize($data);
        $hasFirstLink = ($data['create_link'] ?? '') === '1';
        $link = $hasFirstLink ? $this->normalizeLink($data) : null;

        $this->pdo->beginTransaction();
        try {
            $result = $this->people->createOrFind($administratorId, $actorId, $person);
            if ($result['created']) {
                $this->audit->record($tenantId, $actorId, 'erp.person.created', 'person', $result['id'], ['entity_type'=>$person['entity_type']]);
            }
            $linkId = null;
            if ($link !== null) {
                $linkId = $this->createLinkRecord($tenantId, $administratorId, $actorId, $result['id'], $link);
            }
            $this->pdo->commit();
            return ['id'=>$result['id'],'created'=>$result['created'],'link_id'=>$linkId];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array{q:string,status:string,condominium:string,role:string} $filters @return list<array<string,mixed>> */
    public function search(int $administratorId, array $filters): array
    {
        $people = $this->people->search($administratorId, $filters);
        if ($people === []) {
            return [];
        }
        $links = $this->links->forPeople($administratorId, array_map(static fn (array $row): int => (int) $row['id'], $people));
        $currentByPerson = [];
        foreach ($links as $link) {
            if ((int) $link['is_current'] !== 1) {
                continue;
            }
            $personId = (int) $link['person_id'];
            $currentByPerson[$personId]['roles'][$link['role']] = self::roleLabel((string) $link['role']);
            $currentByPerson[$personId]['condominiums'][(int) $link['condominium_id']] = (string) (($link['condominium_name'] ?? '') ?: $link['condominium_legal_name']);
            if ($link['unit_id'] !== null) {
                $unit = trim((string) (($link['block_name'] ?? '') ? $link['block_name'] . ' · ' : '') . (string) $link['unit_code']);
                $currentByPerson[$personId]['units'][(int) $link['unit_id']] = $unit;
            }
        }
        foreach ($people as &$person) {
            $personId = (int) $person['id'];
            $summary = $currentByPerson[$personId] ?? [];
            $person['roles_label'] = implode(', ', array_values($summary['roles'] ?? [])) ?: 'Sem vínculo ativo';
            $person['condominiums_label'] = implode(', ', array_values($summary['condominiums'] ?? [])) ?: '—';
            $person['units_label'] = implode(', ', array_values($summary['units'] ?? [])) ?: '—';
            $person['document_display'] = self::maskDocument($person['document_type'] ?? null, $person['document_number'] ?? null);
            $person['contact_display'] = trim(implode(' · ', array_filter([(string) ($person['email'] ?? ''), (string) ($person['phone'] ?? '')]))) ?: '—';
        }
        unset($person);
        return $people;
    }

    /** @return array{person:array<string,mixed>,links:list<array<string,mixed>>}|null */
    public function detail(int $administratorId, int $personId): ?array
    {
        $person = $this->people->find($administratorId, $personId);
        if ($person === null) {
            return null;
        }
        return ['person'=>$person,'links'=>$this->links->forPerson($administratorId, $personId)];
    }

    /** @param array<string,mixed> $data */
    public function addLink(int $tenantId, int $administratorId, int $actorId, int $personId, array $data): int
    {
        $link = $this->normalizeLink($data);
        if ($this->people->find($administratorId, $personId) === null) {
            throw new InvalidArgumentException('Pessoa não encontrada nesta administradora.');
        }
        $this->pdo->beginTransaction();
        try {
            $linkId = $this->createLinkRecord($tenantId, $administratorId, $actorId, $personId, $link);
            $this->pdo->commit();
            return $linkId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function endLink(int $tenantId, int $administratorId, int $actorId, int $linkId, string $endsAt): bool
    {
        PersonLinkRepository::assertDate($endsAt, 'Data de encerramento');
        $this->pdo->beginTransaction();
        try {
            $ended = $this->links->end($administratorId, $actorId, $linkId, $endsAt);
            if ($ended) {
                $this->audit->record($tenantId, $actorId, 'erp.person_link.ended', 'person_link', $linkId, ['ends_at'=>$endsAt]);
            }
            $this->pdo->commit();
            return $ended;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<array<string,mixed>> */
    public function linksForUnit(int $administratorId, int $unitId): array
    {
        return $this->links->forUnit($administratorId, $unitId);
    }

    /** @param array<string,mixed> $data @return array{entity_type:string,full_name:string,trade_name:?string,document_type:?string,document_number:?string,email:?string,phone:?string} */
    private function normalize(array $data): array
    {
        $entityType = (string) ($data['entity_type'] ?? 'person');
        if (!in_array($entityType, ['person','organization'], true)) {
            throw new InvalidArgumentException('Selecione pessoa física ou jurídica.');
        }
        $fullName = trim(strip_tags((string) ($data['full_name'] ?? '')));
        $tradeName = trim(strip_tags((string) ($data['trade_name'] ?? ''))) ?: null;
        if ($fullName === '' || mb_strlen($fullName) > 190 || ($tradeName !== null && mb_strlen($tradeName) > 190)) {
            throw new InvalidArgumentException('Informe um nome ou razão social com até 190 caracteres.');
        }
        $rawDocument = preg_replace('/\D+/', '', (string) ($data['document_number'] ?? '')) ?? '';
        $documentType = strtolower(trim((string) ($data['document_type'] ?? '')));
        if ($rawDocument === '' && $documentType === '') {
            $document = null;
        } else {
            $expectedType = $entityType === 'person' ? 'cpf' : 'cnpj';
            if ($documentType !== $expectedType || strlen($rawDocument) !== ($entityType === 'person' ? 11 : 14)) {
                throw new InvalidArgumentException($entityType === 'person' ? 'Informe um CPF com 11 dígitos.' : 'Informe um CNPJ com 14 dígitos.');
            }
            $document = $rawDocument;
        }
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190)) {
            throw new InvalidArgumentException('Informe um e-mail válido com até 190 caracteres.');
        }
        $phone = trim(strip_tags((string) ($data['phone'] ?? '')));
        if (mb_strlen($phone) > 40) {
            throw new InvalidArgumentException('O telefone aceita até 40 caracteres.');
        }
        return [
            'entity_type'=>$entityType,
            'full_name'=>$fullName,
            'trade_name'=>$tradeName,
            'document_type'=>$document === null ? null : ($entityType === 'person' ? 'cpf' : 'cnpj'),
            'document_number'=>$document,
            'email'=>$email === '' ? null : $email,
            'phone'=>$phone === '' ? null : $phone,
        ];
    }

    /** @param array<string,mixed> $data @return array{condominium_id:int,unit_id:?int,role:string,starts_at:string,ends_at:?string} */
    private function normalizeLink(array $data): array
    {
        $condominiumId = filter_var($data['condominium_id'] ?? null, FILTER_VALIDATE_INT);
        $unitValue = trim((string) ($data['unit_id'] ?? ''));
        $unitId = $unitValue === '' ? null : filter_var($unitValue, FILTER_VALIDATE_INT);
        if (!is_int($condominiumId) || $condominiumId < 1 || ($unitValue !== '' && (!is_int($unitId) || $unitId < 1))) {
            throw new InvalidArgumentException('Selecione um condomínio válido e uma unidade opcional.');
        }
        $role = (string) ($data['role'] ?? '');
        $startsAt = trim((string) ($data['starts_at'] ?? ''));
        $endsAt = trim((string) ($data['ends_at'] ?? '')) ?: null;
        if (!in_array($role, PersonLinkRepository::ROLES, true)) {
            throw new InvalidArgumentException('Selecione um tipo de vínculo válido.');
        }
        PersonLinkRepository::assertDate($startsAt, 'Data inicial');
        if ($endsAt !== null) {
            PersonLinkRepository::assertDate($endsAt, 'Data final');
            if ($endsAt < $startsAt) {
                throw new InvalidArgumentException('A data final não pode anteceder a data inicial.');
            }
        }
        return ['condominium_id'=>$condominiumId,'unit_id'=>$unitId,'role'=>$role,'starts_at'=>$startsAt,'ends_at'=>$endsAt];
    }

    /** @param array{condominium_id:int,unit_id:?int,role:string,starts_at:string,ends_at:?string} $link */
    private function createLinkRecord(int $tenantId, int $administratorId, int $actorId, int $personId, array $link): int
    {
        if (!$this->structure->condominiumExists($administratorId, $link['condominium_id'])) {
            throw new InvalidArgumentException('O condomínio não pertence à administradora atual.');
        }
        if ($link['unit_id'] !== null && !$this->unitBelongsToCondominium($link['unit_id'], $link['condominium_id'])) {
            throw new InvalidArgumentException('A unidade selecionada não pertence ao condomínio informado.');
        }
        $linkId = $this->links->create(
            $administratorId,$actorId,$personId,$link['condominium_id'],$link['unit_id'],
            $link['role'],$link['starts_at'],$link['ends_at']
        );
        $this->audit->record($tenantId, $actorId, 'erp.person_link.created', 'person_link', $linkId, [
            'person_id'=>$personId,'condominium_id'=>$link['condominium_id'],'unit_id'=>$link['unit_id'],'role'=>$link['role'],
            'starts_at'=>$link['starts_at'],'ends_at'=>$link['ends_at'],
        ]);
        return $linkId;
    }

    private function unitBelongsToCondominium(int $unitId, int $condominiumId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM erp_units WHERE id=:id AND condominium_id=:condominium_id');
        $statement->execute(['id'=>$unitId,'condominium_id'=>$condominiumId]);
        return $statement->fetchColumn() !== false;
    }

    private static function roleLabel(string $role): string
    {
        return match ($role) {
            'owner'=>'Proprietário','tenant'=>'Inquilino','resident'=>'Morador','manager'=>'Síndico',
            'deputy_manager'=>'Subs. síndico','council'=>'Conselheiro','proxy'=>'Procurador',default=>$role,
        };
    }

    private static function maskDocument(mixed $type, mixed $number): string
    {
        if (!is_string($number) || $number === '') {
            return '—';
        }
        $tail = substr($number, -4);
        return is_string($type) && $type === 'cnpj' ? 'CNPJ · ••••••••' . $tail : 'CPF · •••.•••.' . substr($tail, 0, 3) . '-' . substr($tail, 3);
    }
}
