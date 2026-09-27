<?php

declare(strict_types=1);

namespace Moves\Boot;

use PDO;
use RuntimeException;

/** Replays the multitenancy migration over the earlier single-tenant Talk schema. */
final class LegacyTalkSchemaReconciler
{
    public function __construct(private PDO $pdo)
    {
    }

    public function apply(string $sql): void
    {
        $tenants = $this->pdo->query('SELECT id FROM talk_tenants ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        if (count($tenants) !== 1) {
            throw new RuntimeException('Upgrade Talk legado exige exatamente um tenant existente.');
        }
        $tenantId = (int) $tenants[0];
        $preservedTables = [
            'talk_attachments', 'talk_contacts', 'talk_conversations', 'talk_events',
            'talk_jack_interactions', 'talk_messages', 'talk_notes',
            'talk_notifications', 'talk_tags', 'talk_ticket_tags', 'talk_tickets',
            'talk_transfers',
        ];
        $counts = [];
        foreach ($preservedTables as $table) {
            $counts[$table] = (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        }
        if (!$this->hasColumn('talk_tenant_users', 'is_default')) {
            $this->pdo->exec('ALTER TABLE talk_tenant_users ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0');
        }
        if (!$this->hasColumn('talk_tenant_users', 'created_at')) {
            $this->pdo->exec('ALTER TABLE talk_tenant_users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
        }
        if (!$this->hasColumn('talk_tenant_users', 'updated_at')) {
            $this->pdo->exec('ALTER TABLE talk_tenant_users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        }
        if (!$this->hasIndex('talk_tenant_users', 'talk_tenant_users_user_default')) {
            $this->pdo->exec('ALTER TABLE talk_tenant_users ADD KEY talk_tenant_users_user_default (user_id,status,is_default)');
        }
        $this->pdo->exec("UPDATE talk_tenant_users SET role='agent' WHERE role='member'");
        $this->pdo->exec("ALTER TABLE talk_tenant_users MODIFY role VARCHAR(20) NOT NULL DEFAULT 'agent'");
        if (!$this->hasColumn('talk_tenants', 'created_at')) {
            $this->pdo->exec('ALTER TABLE talk_tenants ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
        }
        if (!$this->hasColumn('talk_tenants', 'updated_at')) {
            $this->pdo->exec('ALTER TABLE talk_tenants ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        }
        $this->pdo->exec('ALTER TABLE talk_channels MODIFY phone_number VARCHAR(40) NULL');
        // The legacy FK references the same channel but blocks the NOT NULL
        // conversion below. Migration 011 recreates it after the conversion.
        if ($this->hasConstraint('talk_conversations', 'talk_conversations_channel_fk')) {
            $this->pdo->exec('ALTER TABLE talk_conversations DROP FOREIGN KEY talk_conversations_channel_fk');
        }
        if ($this->hasIndex('talk_messages', 'talk_messages_external_id_unique')) {
            $this->pdo->exec('ALTER TABLE talk_messages DROP INDEX talk_messages_external_id_unique');
        }
        foreach (['talk_contacts', 'talk_conversations'] as $table) {
            $index = $table . '_tenant_channel_external';
            $columns = $this->indexColumns($table, $index);
            if ($columns !== [] && $columns !== ['tenant_id', 'channel_id', 'external_id']) {
                $this->pdo->exec('ALTER TABLE ' . $table . ' DROP INDEX ' . $index);
            }
        }
        $this->pdo->exec('UPDATE talk_tenant_users SET is_default=1 WHERE tenant_id=' . $tenantId);

        $sql = preg_replace('/^--[^\r\n]*/m', '', $sql) ?? $sql;
        foreach (explode(';', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            if (preg_match('/^INSERT IGNORE INTO talk_tenants\b/i', $statement) === 1) {
                continue;
            }
            if (preg_match('/^SET @talk_default_tenant\b/i', $statement) === 1) {
                $this->pdo->exec('SET @talk_default_tenant=' . $tenantId);
                continue;
            }
            if (preg_match('/^ALTER TABLE (\w+)\s+(.+)$/is', $statement, $match) === 1) {
                $alter = $this->adaptAlter($match[1], $match[2]);
                if ($alter !== null) {
                    $this->pdo->exec($alter);
                }
                continue;
            }
            $this->pdo->exec($statement);
        }
        if ((int) $this->pdo->query('SELECT COUNT(*) FROM talk_tenants')->fetchColumn() !== 1) {
            throw new RuntimeException('Upgrade Talk alterou a quantidade de tenants.');
        }
        foreach ($counts as $table => $before) {
            $after = (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
            if ($after !== $before) {
                throw new RuntimeException('Upgrade Talk alterou a quantidade de registros em ' . $table . '.');
            }
            $otherTenant = $this->pdo->query('SELECT COUNT(*) FROM ' . $table . ' WHERE tenant_id<>' . $tenantId);
            if ((int) $otherTenant->fetchColumn() !== 0) {
                throw new RuntimeException('Upgrade Talk encontrou tenant divergente em ' . $table . '.');
            }
        }
    }

    private function adaptAlter(string $table, string $clauses): ?string
    {
        $parts = $this->splitClauses($clauses);
        $requestedPrimary = null;
        foreach ($parts as $part) {
            if (preg_match('/^ADD PRIMARY KEY\s*\(([^)]+)\)/i', $part, $match) === 1) {
                $requestedPrimary = strtolower(preg_replace('/\s+/', '', $match[1]) ?? $match[1]);
            }
        }
        $primary = $this->pdo->prepare(
            "SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX"
        );
        $primary->execute(['table_name' => $table]);
        $currentPrimary = strtolower(implode(',', $primary->fetchAll(PDO::FETCH_COLUMN)));
        $samePrimary = $requestedPrimary !== null && $requestedPrimary === $currentPrimary;

        $filtered = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^ADD COLUMN (\w+)/i', $part, $match) === 1 && $this->hasColumn($table, $match[1])) {
                continue;
            }
            if (preg_match('/^DROP INDEX (\w+)/i', $part, $match) === 1 && !$this->hasIndex($table, $match[1])) {
                continue;
            }
            if (preg_match('/^ADD (?:UNIQUE )?KEY (\w+)/i', $part, $match) === 1 && $this->hasIndex($table, $match[1])) {
                continue;
            }
            if (preg_match('/^ADD CONSTRAINT (\w+)/i', $part, $match) === 1 && $this->hasConstraint($table, $match[1])) {
                continue;
            }
            if ($samePrimary && preg_match('/^(?:DROP PRIMARY KEY|ADD PRIMARY KEY)/i', $part) === 1) {
                continue;
            }
            $filtered[] = $part;
        }
        return $filtered === [] ? null : 'ALTER TABLE ' . $table . ' ' . implode(', ', $filtered);
    }

    /** @return list<string> */
    private function splitClauses(string $clauses): array
    {
        $result = [];
        $start = 0;
        $depth = 0;
        $length = strlen($clauses);
        for ($i = 0; $i < $length; $i++) {
            if ($clauses[$i] === '(') {
                $depth++;
            } elseif ($clauses[$i] === ')') {
                $depth--;
            } elseif ($clauses[$i] === ',' && $depth === 0) {
                $result[] = substr($clauses, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $result[] = substr($clauses, $start);
        return $result;
    }

    private function hasColumn(string $table, string $column): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name AND COLUMN_NAME=:column_name');
        $statement->execute(['table_name' => $table, 'column_name' => $column]);
        return (int) $statement->fetchColumn() > 0;
    }

    private function hasIndex(string $table, string $index): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name AND INDEX_NAME=:index_name');
        $statement->execute(['table_name' => $table, 'index_name' => $index]);
        return (int) $statement->fetchColumn() > 0;
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $index): array
    {
        $statement = $this->pdo->prepare('SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name AND INDEX_NAME=:index_name ORDER BY SEQ_IN_INDEX');
        $statement->execute(['table_name' => $table, 'index_name' => $index]);
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    private function hasConstraint(string $table, string $constraint): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name AND CONSTRAINT_NAME=:constraint_name');
        $statement->execute(['table_name' => $table, 'constraint_name' => $constraint]);
        return (int) $statement->fetchColumn() > 0;
    }
}
