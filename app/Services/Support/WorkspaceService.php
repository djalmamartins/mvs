<?php

declare(strict_types=1);

namespace Moves\Services\Support;

use Moves\Boot\Connection;
use PDO;

final class WorkspaceService
{
    public function dashboard(): array
    {
        $pdo = Connection::getInstance();
        $counts = $pdo->query(
            "SELECT
                SUM(status = 'published') AS published,
                SUM(status = 'draft') AS drafts,
                SUM(status = 'archived') AS archived,
                COUNT(*) AS articles
             FROM support_articles WHERE deleted_at IS NULL"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        foreach (['products' => 'support_products', 'categories' => 'support_categories', 'tags' => 'support_tags'] as $key => $table) {
            $counts[$key] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        }
        $counts['revisions'] = (int) $pdo->query(
            'SELECT COUNT(*) FROM support_article_revisions r
             INNER JOIN support_articles a ON a.id = r.article_id
             WHERE a.deleted_at IS NULL'
        )->fetchColumn();

        $recent = $pdo->query(
            "SELECT a.title, a.slug, a.status, a.updated_at,
                    p.name AS product_name, c.name AS category_name
               FROM support_articles a
               LEFT JOIN support_products p ON p.id = a.product_id
               LEFT JOIN support_categories c ON c.id = a.category_id
              WHERE a.deleted_at IS NULL
              ORDER BY a.updated_at DESC, a.id DESC LIMIT 6"
        )->fetchAll(PDO::FETCH_ASSOC);

        return ['counts' => array_map('intval', $counts), 'recent' => $recent];
    }

    public function reports(): array
    {
        $pdo = Connection::getInstance();
        $summary = $this->dashboard()['counts'];
        $byStatus = $pdo->query(
            "SELECT status AS label, COUNT(*) AS total FROM support_articles
             WHERE deleted_at IS NULL GROUP BY status ORDER BY total DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $byProduct = $pdo->query(
            "SELECT COALESCE(p.name, 'Sem produto') AS label, COUNT(a.id) AS total
               FROM support_articles a LEFT JOIN support_products p ON p.id = a.product_id
              WHERE a.deleted_at IS NULL GROUP BY p.id, p.name ORDER BY total DESC, label ASC LIMIT 8"
        )->fetchAll(PDO::FETCH_ASSOC);
        $byCategory = $pdo->query(
            "SELECT COALESCE(c.name, 'Sem categoria') AS label, COUNT(a.id) AS total
               FROM support_articles a LEFT JOIN support_categories c ON c.id = a.category_id
              WHERE a.deleted_at IS NULL GROUP BY c.id, c.name ORDER BY total DESC, label ASC LIMIT 8"
        )->fetchAll(PDO::FETCH_ASSOC);

        return compact('summary', 'byStatus', 'byProduct', 'byCategory');
    }

    public function users(int $tenantId, string $search = '', string $role = '', string $status = ''): array
    {
        if ($tenantId <= 0) {
            return [];
        }
        $terms = ['membership.tenant_id = :tenant_id', "membership.status = 'active'"];
        $params = ['tenant_id' => $tenantId];
        if ($search !== '') {
            $terms[] = '(account.name LIKE :name OR account.email LIKE :email)';
            $params['name'] = '%' . $search . '%';
            $params['email'] = '%' . $search . '%';
        }
        if ($role !== '' && in_array($role, ['admin', 'user'], true)) {
            $terms[] = 'account.role = :role';
            $params['role'] = $role;
        }
        if ($status !== '' && in_array($status, ['active', 'inactive'], true)) {
            $terms[] = 'account.status = :status';
            $params['status'] = $status;
        }
        $statement = Connection::getInstance()->prepare(
            'SELECT account.id, account.name, account.email, account.role, account.status,
                    account.created_at, account.updated_at, membership.role AS tenant_role
               FROM users account
               INNER JOIN talk_tenant_users membership ON membership.user_id = account.id
              WHERE '
            . implode(' AND ', $terms) . ' ORDER BY account.name ASC, account.id ASC'
        );
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
