<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\ChartOfAccounts;

use InvalidArgumentException;
use Moves\Services\Platform\PlatformAudit;
use PDO;
use PDOException;

final readonly class ChartOfAccountsService
{
    private const NATURES = ['asset', 'liability', 'equity', 'revenue', 'expense'];
    private const MAX_LEVEL = 8;

    public function __construct(
        private PDO $pdo,
        private ChartOfAccountsRepository $repository,
        private PlatformAudit $audit
    ) {
    }

    /** @param array<string,mixed> $input */
    public function createPlan(int $tenantId, int $administratorId, int $actorId, array $input): int
    {
        $condominiumId = filter_var($input['condominium_id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim((string) ($input['name'] ?? ''));
        if ($condominiumId === false || $condominiumId < 1 || !$this->condominiumExists($administratorId, $condominiumId)) {
            throw new InvalidArgumentException('Selecione um condomínio ativo da administradora atual.');
        }
        if ($name === '' || mb_strlen($name) > 160) {
            throw new InvalidArgumentException('Informe um nome de plano com até 160 caracteres.');
        }
        try {
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare(
                "INSERT INTO erp_accounting_plans(administrator_id,condominium_id,name,status,created_by_user_id,updated_by_user_id)
                 VALUES(:administrator_id,:condominium_id,:name,'active',:created_by,:updated_by)"
            );
            $statement->execute(['administrator_id' => $administratorId, 'condominium_id' => $condominiumId, 'name' => $name, 'created_by' => $actorId, 'updated_by' => $actorId]);
            $id = (int) $this->pdo->lastInsertId();
            $this->audit->record($tenantId, $actorId, 'erp.accounting_plan.created', 'accounting_plan', $id, ['condominium_id' => $condominiumId, 'name' => $name]);
            $this->pdo->commit();
            return $id;
        } catch (PDOException $exception) {
            $this->rollback();
            if (self::isDuplicate($exception)) {
                throw new InvalidArgumentException('Este condomínio já possui um plano de contas.');
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    /** @param array<string,mixed> $input */
    public function createAccount(int $tenantId, int $administratorId, int $actorId, int $planId, array $input): int
    {
        $this->assertPlan($administratorId, $planId);
        $account = $this->normalizeAccount($input);
        try {
            $this->pdo->beginTransaction();
            $this->lockPlan($administratorId, $planId);
            [$parentId, $level] = $this->resolveParent($administratorId, $planId, $account['parent_id'], null, $account['nature']);
            $statement = $this->pdo->prepare(
                'INSERT INTO erp_accounting_accounts(administrator_id,plan_id,parent_id,code,name,nature,account_type,level,sort_order,status,created_by_user_id,updated_by_user_id)
                 VALUES(:administrator_id,:plan_id,:parent_id,:code,:name,:nature,:account_type,:level,:sort_order,:status,:created_by,:updated_by)'
            );
            $statement->execute([
                'administrator_id' => $administratorId, 'plan_id' => $planId, 'parent_id' => $parentId,
                'code' => $account['code'], 'name' => $account['name'], 'nature' => $account['nature'],
                'account_type' => $account['account_type'], 'level' => $level, 'sort_order' => $account['sort_order'],
                'status' => $account['status'], 'created_by' => $actorId, 'updated_by' => $actorId,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->audit->record($tenantId, $actorId, 'erp.accounting_account.created', 'accounting_account', $id, [
                'plan_id' => $planId, 'code' => $account['code'], 'name' => $account['name'], 'nature' => $account['nature'],
                'account_type' => $account['account_type'], 'parent_id' => $parentId, 'level' => $level,
                'sort_order' => $account['sort_order'], 'status' => $account['status'],
            ]);
            $this->pdo->commit();
            return $id;
        } catch (PDOException $exception) {
            $this->rollback();
            if (self::isDuplicate($exception)) {
                throw new InvalidArgumentException('O código já existe neste plano de contas.');
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    /** @param array<string,mixed> $input */
    public function updateAccount(int $tenantId, int $administratorId, int $actorId, int $planId, int $accountId, array $input): void
    {
        $this->assertPlan($administratorId, $planId);
        $account = $this->normalizeAccount($input);
        try {
            $this->pdo->beginTransaction();
            $this->lockPlan($administratorId, $planId);
            $current = $this->repository->findAccount($administratorId, $planId, $accountId);
            if ($current === null) {
                throw new InvalidArgumentException('Conta não encontrada neste plano.');
            }
            [$parentId, $level] = $this->resolveParent($administratorId, $planId, $account['parent_id'], $accountId, $account['nature']);
            $hasChildren = $this->hasChildren($planId, $accountId);
            if ($hasChildren && $account['nature'] !== $current['nature']) {
                throw new InvalidArgumentException('A natureza não pode mudar enquanto a conta tiver filhas.');
            }
            if ($hasChildren && $account['account_type'] !== 'synthetic') {
                throw new InvalidArgumentException('Uma conta com filhas deve permanecer sintética.');
            }
            if ($account['account_type'] === 'analytic' && $hasChildren) {
                throw new InvalidArgumentException('Uma conta analítica não pode agrupar outras contas.');
            }
            if ($account['status'] === 'inactive' && $this->hasActiveDescendants($administratorId, $planId, $accountId)) {
                throw new InvalidArgumentException('Inative primeiro as contas filhas ativas.');
            }
            $this->shiftSubtreeLevels($administratorId, $planId, $accountId, $level - (int) $current['level']);
            $statement = $this->pdo->prepare(
                'UPDATE erp_accounting_accounts SET parent_id=:parent_id,code=:code,name=:name,nature=:nature,account_type=:account_type,
                    level=:level,sort_order=:sort_order,status=:status,updated_by_user_id=:actor_id
                 WHERE administrator_id=:administrator_id AND plan_id=:plan_id AND id=:account_id'
            );
            $statement->execute([
                'parent_id' => $parentId, 'code' => $account['code'], 'name' => $account['name'], 'nature' => $account['nature'],
                'account_type' => $account['account_type'], 'level' => $level, 'sort_order' => $account['sort_order'],
                'status' => $account['status'], 'actor_id' => $actorId, 'administrator_id' => $administratorId, 'plan_id' => $planId, 'account_id' => $accountId,
            ]);
            $this->audit->record($tenantId, $actorId, 'erp.accounting_account.updated', 'accounting_account', $accountId, [
                'plan_id' => $planId,
                'changes' => [
                    'parent_id' => [$current['parent_id'] === null ? null : (int) $current['parent_id'], $parentId],
                    'code' => [$current['code'], $account['code']], 'name' => [$current['name'], $account['name']],
                    'nature' => [$current['nature'], $account['nature']], 'account_type' => [$current['account_type'], $account['account_type']],
                    'level' => [(int) $current['level'], $level], 'sort_order' => [(int) $current['sort_order'], $account['sort_order']],
                    'status' => [$current['status'], $account['status']],
                ],
            ]);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->rollback();
            if (self::isDuplicate($exception)) {
                throw new InvalidArgumentException('O código já existe neste plano de contas.');
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    /** @return array{code:string,name:string,nature:string,account_type:string,status:string,parent_id:?int,sort_order:int} */
    private function normalizeAccount(array $input): array
    {
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));
        $nature = (string) ($input['nature'] ?? '');
        $type = (string) ($input['account_type'] ?? '');
        $status = (string) ($input['status'] ?? 'active');
        $parent = filter_var($input['parent_id'] ?? '', FILTER_VALIDATE_INT);
        $order = filter_var($input['sort_order'] ?? 0, FILTER_VALIDATE_INT);
        if ($code === '' || mb_strlen($code) > 32 || preg_match('/^[A-Z0-9][A-Z0-9._-]*$/', $code) !== 1) {
            throw new InvalidArgumentException('Informe um código válido de até 32 caracteres (letras, números, ponto, hífen ou sublinhado).');
        }
        if ($name === '' || mb_strlen($name) > 160) {
            throw new InvalidArgumentException('Informe um nome de conta com até 160 caracteres.');
        }
        if (!in_array($nature, self::NATURES, true)) {
            throw new InvalidArgumentException('Selecione uma natureza contábil válida.');
        }
        if (!in_array($type, ['synthetic', 'analytic'], true)) {
            throw new InvalidArgumentException('Selecione sintética ou analítica.');
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('Selecione uma situação válida.');
        }
        $rawParent = $input['parent_id'] ?? '';
        if ($rawParent === '') {
            $parentId = null;
        } else {
            if ($parent === false || $parent < 1) {
                throw new InvalidArgumentException('Selecione uma conta pai válida deste plano.');
            }
            $parentId = $parent;
        }
        if ($order === false || $order < 0 || $order > 4_294_967_295) {
            throw new InvalidArgumentException('A ordenação deve ser um inteiro entre zero e 4.294.967.295.');
        }
        return ['code' => $code, 'name' => $name, 'nature' => $nature, 'account_type' => $type, 'status' => $status, 'parent_id' => $parentId, 'sort_order' => $order];
    }

    /** @return array{0:?int,1:int} */
    private function resolveParent(int $administratorId, int $planId, ?int $parentId, ?int $accountId, string $nature): array
    {
        if ($parentId === null) {
            return [null, 1];
        }
        if ($accountId !== null && $parentId === $accountId) {
            throw new InvalidArgumentException('Uma conta não pode ser pai dela mesma.');
        }
        $parent = $this->repository->findAccount($administratorId, $planId, $parentId);
        if ($parent === null) {
            throw new InvalidArgumentException('A conta pai não pertence a este plano.');
        }
        if ($parent['account_type'] !== 'synthetic' || $parent['status'] !== 'active') {
            throw new InvalidArgumentException('A conta pai deve ser sintética e estar ativa.');
        }
        if ($parent['nature'] !== $nature) {
            throw new InvalidArgumentException('A conta filha deve manter a natureza da conta pai.');
        }
        if ((int) $parent['level'] >= self::MAX_LEVEL) {
            throw new InvalidArgumentException('A hierarquia não pode ultrapassar oito níveis.');
        }
        $cursor = (int) $parentId;
        $visited = [];
        while ($cursor > 0) {
            if ($cursor === $accountId || isset($visited[$cursor])) {
                throw new InvalidArgumentException('A alteração criaria um ciclo na hierarquia.');
            }
            $visited[$cursor] = true;
            $statement = $this->pdo->prepare('SELECT parent_id FROM erp_accounting_accounts WHERE administrator_id=:administrator_id AND plan_id=:plan_id AND id=:id');
            $statement->execute(['administrator_id' => $administratorId, 'plan_id' => $planId, 'id' => $cursor]);
            $next = $statement->fetchColumn();
            $cursor = $next === false || $next === null ? 0 : (int) $next;
        }
        return [$parentId, (int) $parent['level'] + 1];
    }

    private function shiftSubtreeLevels(int $administratorId, int $planId, int $accountId, int $delta): void
    {
        if ($delta === 0) {
            return;
        }
        $statement = $this->pdo->prepare('SELECT id,level FROM erp_accounting_accounts WHERE administrator_id=:administrator_id AND plan_id=:plan_id AND parent_id=:parent_id');
        $statement->execute(['administrator_id' => $administratorId, 'plan_id' => $planId, 'parent_id' => $accountId]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $child) {
            $level = (int) $child['level'] + $delta;
            if ($level > self::MAX_LEVEL) {
                throw new InvalidArgumentException('A alteração ultrapassaria oito níveis na hierarquia.');
            }
            $update = $this->pdo->prepare('UPDATE erp_accounting_accounts SET level=:level WHERE administrator_id=:administrator_id AND plan_id=:plan_id AND id=:id');
            $update->execute(['level' => $level, 'administrator_id' => $administratorId, 'plan_id' => $planId, 'id' => (int) $child['id']]);
            $this->shiftSubtreeLevels($administratorId, $planId, (int) $child['id'], $delta);
        }
    }

    private function hasChildren(int $planId, int $accountId): bool
    {
        if ($accountId < 1) {
            return false;
        }
        $statement = $this->pdo->prepare('SELECT 1 FROM erp_accounting_accounts WHERE plan_id=:plan_id AND parent_id=:parent_id LIMIT 1');
        $statement->execute(['plan_id' => $planId, 'parent_id' => $accountId]);
        return $statement->fetchColumn() !== false;
    }

    private function hasActiveDescendants(int $administratorId, int $planId, int $accountId): bool
    {
        $statement = $this->pdo->prepare('SELECT id,status FROM erp_accounting_accounts WHERE administrator_id=:administrator_id AND plan_id=:plan_id AND parent_id=:parent_id');
        $statement->execute(['administrator_id' => $administratorId, 'plan_id' => $planId, 'parent_id' => $accountId]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $descendant) {
            if ($descendant['status'] === 'active' || $this->hasActiveDescendants($administratorId, $planId, (int) $descendant['id'])) {
                return true;
            }
        }
        return false;
    }

    private function condominiumExists(int $administratorId, int $condominiumId): bool
    {
        $statement = $this->pdo->prepare("SELECT 1 FROM erp_condominiums WHERE administrator_id=:administrator_id AND id=:condominium_id AND status='active' LIMIT 1");
        $statement->execute(['administrator_id' => $administratorId, 'condominium_id' => $condominiumId]);
        return $statement->fetchColumn() !== false;
    }

    private function assertPlan(int $administratorId, int $planId): void
    {
        if (!$this->repository->planExists($administratorId, $planId)) {
            throw new InvalidArgumentException('Plano de contas não encontrado.');
        }
    }

    private function lockPlan(int $administratorId, int $planId): void
    {
        $sql = 'SELECT id FROM erp_accounting_plans WHERE administrator_id=:administrator_id AND id=:plan_id';
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['administrator_id' => $administratorId, 'plan_id' => $planId]);
        if ($statement->fetchColumn() === false) {
            throw new InvalidArgumentException('Plano de contas não encontrado.');
        }
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private static function isDuplicate(PDOException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062
            || str_contains(strtolower($exception->getMessage()), 'unique constraint failed')
            || str_contains(strtolower($exception->getMessage()), 'duplicate entry');
    }
}
