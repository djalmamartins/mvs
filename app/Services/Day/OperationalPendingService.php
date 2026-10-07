<?php

declare(strict_types=1);

namespace Moves\Services\Day;

use InvalidArgumentException;
use Moves\Services\Platform\Cnpj;
use Moves\Services\Platform\PlatformAudit;
use PDO;
use RuntimeException;

/** Event-driven operational rule projected into the canonical day_tasks domain. */
final readonly class OperationalPendingService
{
    public const MISSING_CONDOMINIUM_CNPJ = 'ERP_CONDOMINIUM_MISSING_CNPJ';

    public function __construct(private PDO $pdo) {}

    public function evaluateCondominiumCnpj(int $tenantId, int $administratorId, int $condominiumId, ?string $taxId, int $actorId): void
    {
        $condominium = $this->pdo->prepare(
            'SELECT c.id,c.legal_name FROM erp_condominiums c JOIN erp_administrators a ON a.id=c.administrator_id WHERE c.id=:condominium AND c.administrator_id=:administrator AND a.tenant_id=:tenant'
        );
        $condominium->execute(['condominium' => $condominiumId, 'administrator' => $administratorId, 'tenant' => $tenantId]);
        $source = $condominium->fetch(PDO::FETCH_ASSOC);
        if (!is_array($source)) {
            throw new RuntimeException('Condomínio não encontrado.');
        }

        $key = self::MISSING_CONDOMINIUM_CNPJ . ':' . $condominiumId;
        $missing = !Cnpj::isValid((string) $taxId);
        if ($missing) {
            $insert = $this->pdo->prepare(
                "INSERT INTO day_tasks(tenant_id,assigned_user_id,created_by,title,description,status,priority,source_type,source_id,automation_key,source_url)
                 VALUES(:tenant,NULL,:actor,:title,:description,'pending','normal','erp_condo_cnpj',:source_id,:automation_key,:source_url)
                 ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),title=VALUES(title)"
            );
            $insert->execute([
                'tenant' => $tenantId, 'actor' => $actorId,
                'title' => 'Regularizar CNPJ — ' . (string) $source['legal_name'],
                'description' => 'Informe o CNPJ válido do condomínio. O prazo, responsável e contexto podem ser definidos pela supervisão.',
                'source_id' => $condominiumId, 'automation_key' => $key,
                'source_url' => '/erp/condominiums/' . $condominiumId,
            ]);
            $taskId = (int) $this->pdo->lastInsertId();
            $created = $insert->rowCount() === 1;
            $task = $this->taskById($tenantId, $taskId, true);
            if ($task === null) {
                throw new RuntimeException('Não foi possível persistir a pendência operacional.');
            }
            if ($created) {
                (new PlatformAudit($this->pdo))->record($tenantId, $actorId, 'erp.pending.created', 'day_task', $taskId, ['type' => self::MISSING_CONDOMINIUM_CNPJ, 'condominium_id' => $condominiumId]);
            } elseif ($task['status'] === 'done') {
                $update = $this->pdo->prepare("UPDATE day_tasks SET status='pending',completed_at=NULL WHERE id=:id AND tenant_id=:tenant AND automation_key=:key");
                $update->execute(['id' => $taskId, 'tenant' => $tenantId, 'key' => $key]);
                (new PlatformAudit($this->pdo))->record($tenantId, $actorId, 'erp.pending.reopened', 'day_task', $taskId, ['type' => self::MISSING_CONDOMINIUM_CNPJ, 'condominium_id' => $condominiumId, 'reason' => 'condominium_cnpj_missing']);
            }
            return;
        }

        $task = $this->taskByAutomationKey($tenantId, $key, true);
        if ($task !== null && $task['status'] !== 'done') {
            $update = $this->pdo->prepare("UPDATE day_tasks SET status='done',completed_at=NOW() WHERE id=:id AND tenant_id=:tenant AND automation_key=:key AND status<>'done'");
            $update->execute(['id' => (int) $task['id'], 'tenant' => $tenantId, 'key' => $key]);
            if ($update->rowCount() === 1) {
                (new PlatformAudit($this->pdo))->record($tenantId, $actorId, 'erp.pending.resolved', 'day_task', (int) $task['id'], ['type' => self::MISSING_CONDOMINIUM_CNPJ, 'condominium_id' => $condominiumId, 'reason' => 'valid_cnpj_registered']);
            }
        }
    }

    /** @return list<array<string,mixed>> */
    public function list(int $tenantId, int $administratorId, array $filters = []): array
    {
        $where = ['t.tenant_id=:tenant', 't.source_type=\'erp_condo_cnpj\'', 't.automation_key IS NOT NULL', 'c.administrator_id=:administrator'];
        $params = ['tenant' => $tenantId, 'administrator' => $administratorId];
        if (in_array($filters['status'] ?? '', ['pending', 'in_progress', 'done'], true)) { $where[] = 't.status=:status'; $params['status'] = $filters['status']; }
        if (in_array($filters['priority'] ?? '', ['low', 'normal', 'high', 'urgent'], true)) { $where[] = 't.priority=:priority'; $params['priority'] = $filters['priority']; }
        if (isset($filters['condominium_id']) && is_int($filters['condominium_id']) && $filters['condominium_id'] > 0) { $where[] = 'c.id=:condominium_id'; $params['condominium_id'] = $filters['condominium_id']; }
        if (isset($filters['responsible']) && $filters['responsible'] !== '') {
            if ($filters['responsible'] === 'unassigned') { $where[] = 't.assigned_user_id IS NULL'; }
            elseif (ctype_digit((string) $filters['responsible'])) { $where[] = 't.assigned_user_id=:responsible'; $params['responsible'] = (int) $filters['responsible']; }
        }
        if (($filters['overdue'] ?? '') === '1') { $where[] = "t.status<>'done' AND t.due_at IS NOT NULL AND t.due_at<NOW()"; }
        $statement = $this->pdo->prepare(
            'SELECT t.id,t.title,t.description,t.status,t.priority,t.assigned_user_id,t.due_at,t.created_at,t.completed_at,c.id condominium_id,c.legal_name condominium_name,u.name responsible_name FROM day_tasks t JOIN erp_condominiums c ON c.id=t.source_id JOIN erp_administrators a ON a.id=c.administrator_id AND a.tenant_id=t.tenant_id LEFT JOIN users u ON u.id=t.assigned_user_id WHERE ' . implode(' AND ', $where) . " ORDER BY CASE WHEN t.status<>'done' AND t.due_at IS NOT NULL AND t.due_at<NOW() THEN 0 ELSE 1 END, CASE t.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END, CASE t.status WHEN 'done' THEN 1 ELSE 0 END, (t.due_at IS NULL), t.due_at,t.created_at,t.id"
        );
        $statement->execute($params);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string,mixed>|null */
    public function detail(int $tenantId, int $administratorId, int $taskId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT t.*,c.legal_name condominium_name,c.id condominium_id,u.name responsible_name FROM day_tasks t JOIN erp_condominiums c ON c.id=t.source_id JOIN erp_administrators a ON a.id=c.administrator_id AND a.tenant_id=t.tenant_id LEFT JOIN users u ON u.id=t.assigned_user_id WHERE t.id=:id AND t.tenant_id=:tenant AND a.id=:administrator AND t.automation_key IS NOT NULL AND t.source_type=\'erp_condo_cnpj\''
        );
        $statement->execute(['id' => $taskId, 'tenant' => $tenantId, 'administrator' => $administratorId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function assignees(int $tenantId, int $administratorId, ?int $condominiumId = null): array
    {
        $condominiumScopeSql = $condominiumId === null
            ? "g.scope_type='condominium' AND g.scope_id IN (SELECT c.id FROM erp_condominiums c WHERE c.administrator_id=:administrator_condominiums)"
            : "g.scope_type='condominium' AND g.scope_id=:condominium_scope";
        $readCondominiumScopeSql = $condominiumId === null
            ? "read_scope.scope_type='condominium' AND read_scope.scope_id IN (SELECT c.id FROM erp_condominiums c WHERE c.administrator_id=:read_administrator_condominiums)"
            : "read_scope.scope_type='condominium' AND read_scope.scope_id=:read_condominium_scope";
        $statement = $this->pdo->prepare(
            "SELECT DISTINCT u.id,u.name FROM users u JOIN talk_tenant_users m ON m.user_id=u.id AND m.tenant_id=:tenant AND m.status='active' JOIN platform_role_permissions rp ON rp.role_id=m.role_id JOIN platform_permissions p ON p.id=rp.permission_id AND p.slug='condominiums.manage' JOIN erp_scope_grants g ON g.user_id=u.id AND g.capability='erp.cadastros.write' AND g.revoked_at IS NULL WHERE u.status='active' AND ((g.scope_type='administrator' AND g.scope_id=:administrator_scope) OR ({$condominiumScopeSql})) AND EXISTS(SELECT 1 FROM erp_scope_grants read_scope WHERE read_scope.user_id=u.id AND read_scope.capability='erp.cadastros.read' AND read_scope.revoked_at IS NULL AND ((read_scope.scope_type='administrator' AND read_scope.scope_id=:read_administrator) OR ({$readCondominiumScopeSql}))) ORDER BY u.name,u.id"
        );
        $params = ['tenant' => $tenantId, 'administrator_scope' => $administratorId];
        $params['read_administrator'] = $administratorId;
        if ($condominiumId === null) { $params['administrator_condominiums'] = $administratorId; $params['read_administrator_condominiums'] = $administratorId; }
        else { $params['condominium_scope'] = $condominiumId; $params['read_condominium_scope'] = $condominiumId; }
        $statement->execute($params);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array{id:int,legal_name:string}> */
    public function condominiums(int $tenantId, int $administratorId): array
    {
        $statement = $this->pdo->prepare("SELECT id,legal_name FROM erp_condominiums WHERE administrator_id=:administrator AND EXISTS(SELECT 1 FROM erp_administrators a WHERE a.id=:administrator_scope AND a.tenant_id=:tenant AND a.id=erp_condominiums.administrator_id) ORDER BY legal_name,id");
        $statement->execute(['administrator' => $administratorId, 'administrator_scope' => $administratorId, 'tenant' => $tenantId]);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function update(int $tenantId, int $administratorId, int $taskId, int $actorId, array $data): void
    {
        $task = $this->detail($tenantId, $administratorId, $taskId);
        if ($task === null) { throw new RuntimeException('Pendência não encontrada.'); }
        if ($task['status'] === 'done') { throw new RuntimeException('Pendências resolvidas não podem ser alteradas.'); }
        $assignedRaw = trim((string) ($data['assigned_user_id'] ?? ''));
        $assigned = $assignedRaw === '' ? null : (ctype_digit($assignedRaw) ? (int) $assignedRaw : -1);
        if ($assigned !== null && $assigned < 1) { throw new InvalidArgumentException('Responsável inválido.'); }
        if ($assigned !== null) {
            $valid = false;
            foreach ($this->assignees($tenantId, $administratorId, (int) $task['condominium_id']) as $candidate) { if ((int) $candidate['id'] === $assigned) { $valid = true; break; } }
            if (!$valid) { throw new InvalidArgumentException('Selecione um usuário ativo com acesso ao ERP deste tenant.'); }
        }
        $status = (string) ($data['status'] ?? 'pending');
        $priority = (string) ($data['priority'] ?? 'normal');
        if (!in_array($status, ['pending', 'in_progress'], true)) { throw new InvalidArgumentException('Status inválido.'); }
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) { throw new InvalidArgumentException('Prioridade inválida.'); }
        $dueInput = trim((string) ($data['due_date'] ?? ''));
        $dueAt = null;
        if ($dueInput !== '') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dueInput);
            if (!$date || $date->format('Y-m-d') !== $dueInput) { throw new InvalidArgumentException('Informe um prazo operacional válido.'); }
            $dueAt = $date->setTime(23, 59, 59)->format('Y-m-d H:i:s');
        }
        $description = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($description) > 5000) { throw new InvalidArgumentException('O contexto deve ter até 5.000 caracteres.'); }
        $statement = $this->pdo->prepare('UPDATE day_tasks SET assigned_user_id=:assignee,status=:status,priority=:priority,due_at=:due_at,description=:description WHERE id=:id AND tenant_id=:tenant AND automation_key=:key AND status<>\'done\'');
        $statement->execute(['assignee' => $assigned, 'status' => $status, 'priority' => $priority, 'due_at' => $dueAt, 'description' => $description ?: null, 'id' => $taskId, 'tenant' => $tenantId, 'key' => self::MISSING_CONDOMINIUM_CNPJ . ':' . (int) $task['condominium_id']]);
        if ($statement->rowCount() > 0) {
            if (($task['assigned_user_id'] ?? null) != $assigned) { (new PlatformAudit($this->pdo))->record($tenantId, $actorId, 'erp.pending.assigned', 'day_task', $taskId, ['type' => self::MISSING_CONDOMINIUM_CNPJ, 'responsible_user_id' => $assigned]); }
            if ($task['status'] !== $status) { (new PlatformAudit($this->pdo))->record($tenantId, $actorId, 'erp.pending.status_changed', 'day_task', $taskId, ['from' => $task['status'], 'to' => $status]); }
            if ($task['priority'] !== $priority || ($task['due_at'] ?? null) !== $dueAt || (string) ($task['description'] ?? '') !== ($description ?: '')) { (new PlatformAudit($this->pdo))->record($tenantId, $actorId, 'erp.pending.updated', 'day_task', $taskId, ['priority' => $priority, 'due_at' => $dueAt]); }
        }
    }

    /** @return list<array<string,mixed>> */
    public function history(int $tenantId, int $taskId): array
    {
        $statement = $this->pdo->prepare("SELECT e.event_type,e.metadata,e.created_at,u.name actor_name FROM platform_audit_events e LEFT JOIN users u ON u.id=e.actor_user_id WHERE e.tenant_id=:tenant AND e.subject_type='day_task' AND e.subject_id=:task AND e.event_type LIKE 'erp.pending.%' ORDER BY e.id DESC");
        $statement->execute(['tenant' => $tenantId, 'task' => $taskId]);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string,mixed>|null */
    private function taskByAutomationKey(int $tenantId, string $key, bool $lock): ?array
    {
        $statement = $this->pdo->prepare('SELECT id,status FROM day_tasks WHERE tenant_id=:tenant AND automation_key=:key LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute(['tenant' => $tenantId, 'key' => $key]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    private function taskById(int $tenantId, int $id, bool $lock): ?array
    {
        $statement = $this->pdo->prepare('SELECT id,status FROM day_tasks WHERE tenant_id=:tenant AND id=:id LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute(['tenant' => $tenantId, 'id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}
