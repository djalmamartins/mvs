<?php

declare(strict_types=1);

namespace Moves\Services;

use Moves\Boot\Connection;
use Moves\Core\Seo;
use PDO;
use RuntimeException;

final class CmsService
{
    public const PER_PAGE = 20;

    /** @param array{q?:string,status?:string,author?:int,category?:int} $filters
     *  @return array{items:list<array<string,mixed>>,page:int,perPage:int,total:int,totalPages:int}
     */
    public function contentPage(string $type, array $filters, int $page): array
    {
        $where = ['c.type=:type', 'c.deleted_at IS NULL'];
        $params = ['type' => $type];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(c.title LIKE :q_title OR c.slug LIKE :q_slug OR c.excerpt LIKE :q_excerpt)';
            $params['q_title'] = $params['q_slug'] = $params['q_excerpt'] = '%' . $q . '%';
        }
        if (($filters['status'] ?? '') !== '') { $where[] = 'c.status=:status'; $params['status'] = $filters['status']; }
        if ((int) ($filters['author'] ?? 0) > 0) { $where[] = 'c.created_by=:author'; $params['author'] = (int) $filters['author']; }
        if ((int) ($filters['category'] ?? 0) > 0) { $where[] = 'c.category_id=:category'; $params['category'] = (int) $filters['category']; }
        $sqlWhere = implode(' AND ', $where);
        $pdo = Connection::getInstance();
        $count = $pdo->prepare('SELECT COUNT(*) FROM studio_content c WHERE ' . $sqlWhere);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * self::PER_PAGE;
        $statement = $pdo->prepare(
            'SELECT c.*,u.name author_name,t.name category_name,(SELECT COUNT(*) FROM studio_content_tags ct WHERE ct.content_id=c.id) tag_count '
            . 'FROM studio_content c LEFT JOIN users u ON u.id=c.created_by LEFT JOIN studio_taxonomies t ON t.id=c.category_id '
            . 'WHERE ' . $sqlWhere . ' ORDER BY c.position,c.id DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset
        );
        $statement->execute($params);
        return ['items' => $statement->fetchAll(PDO::FETCH_ASSOC), 'page' => $page, 'perPage' => self::PER_PAGE, 'total' => $total, 'totalPages' => $totalPages];
    }

    /** @return list<array<string,mixed>> */
    public function authors(): array
    {
        return Connection::getInstance()->query("SELECT id,name FROM users WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function tagsForContent(int $contentId): array
    {
        $statement = Connection::getInstance()->prepare('SELECT t.id,t.name,t.slug FROM studio_tags t JOIN studio_content_tags ct ON ct.tag_id=t.id WHERE ct.content_id=? ORDER BY t.name');
        $statement->execute([$contentId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param list<int> $tagIds */
    public function syncTags(int $contentId, array $tagIds): void
    {
        $pdo = Connection::getInstance();
        $pdo->prepare('DELETE FROM studio_content_tags WHERE content_id=?')->execute([$contentId]);
        $insert = $pdo->prepare('INSERT INTO studio_content_tags(content_id,tag_id) SELECT ?,id FROM studio_tags WHERE id=?');
        foreach (array_values(array_unique(array_filter($tagIds))) as $tagId) { $insert->execute([$contentId, $tagId]); }
    }

    /** @return list<array<string,mixed>> */
    public function tags(string $q = ''): array
    {
        $params = [];
        $where = '';
        if ($q !== '') { $where = ' WHERE t.name LIKE ? OR t.slug LIKE ?'; $params = ['%' . $q . '%', '%' . $q . '%']; }
        $statement = Connection::getInstance()->prepare('SELECT t.*,COUNT(ct.content_id) usage_count FROM studio_tags t LEFT JOIN studio_content_tags ct ON ct.tag_id=t.id' . $where . ' GROUP BY t.id ORDER BY t.name');
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveTag(int $id, string $name): int
    {
        $name = mb_substr(trim(strip_tags($name)), 0, 120);
        $slug = Seo::slug($name);
        if (mb_strlen($name) < 2 || $slug === '') { throw new RuntimeException('Informe uma tag válida.'); }
        $pdo = Connection::getInstance();
        if ($id > 0) { $pdo->prepare('UPDATE studio_tags SET name=?,slug=? WHERE id=?')->execute([$name, $slug, $id]); return $id; }
        $pdo->prepare('INSERT INTO studio_tags(name,slug) VALUES(?,?)')->execute([$name, $slug]);
        return (int) $pdo->lastInsertId();
    }

    public function deleteTag(int $id): void
    {
        $pdo = Connection::getInstance();
        $usage = $pdo->prepare('SELECT COUNT(*) FROM studio_content_tags WHERE tag_id=?'); $usage->execute([$id]);
        if ((int) $usage->fetchColumn() > 0) { throw new RuntimeException('Esta tag está sendo utilizada. Remova as relações antes de excluir.'); }
        $pdo->prepare('DELETE FROM studio_tags WHERE id=?')->execute([$id]);
    }

    public function trash(int $id, string $type, int $actorId): bool
    {
        $statement = Connection::getInstance()->prepare('UPDATE studio_content SET deleted_status=status,deleted_at=NOW(),deleted_by=? WHERE id=? AND type=? AND deleted_at IS NULL');
        $statement->execute([$actorId, $id, $type]);
        return $statement->rowCount() === 1;
    }

    public function restore(int $id, int $actorId): bool
    {
        $statement = Connection::getInstance()->prepare("UPDATE studio_content SET status=IF(deleted_status IN ('draft','published','archived'),deleted_status,'draft'),deleted_at=NULL,deleted_by=NULL,deleted_status=NULL WHERE id=? AND deleted_at IS NOT NULL");
        $statement->execute([$id]);
        return $statement->rowCount() === 1;
    }

    public function deletePermanently(int $id): bool
    {
        $statement = Connection::getInstance()->prepare('DELETE FROM studio_content WHERE id=? AND deleted_at IS NOT NULL');
        $statement->execute([$id]);
        return $statement->rowCount() === 1;
    }

    /** @return array{items:list<array<string,mixed>>,page:int,perPage:int,total:int,totalPages:int} */
    public function trashPage(string $q = '', string $type = '', int $page = 1): array
    {
        $where = ['c.deleted_at IS NOT NULL']; $params = [];
        if ($q !== '') { $where[] = '(c.title LIKE :q_title OR c.slug LIKE :q_slug)'; $params['q_title'] = $params['q_slug'] = '%' . $q . '%'; }
        if ($type !== '') { $where[] = 'c.type=:type'; $params['type'] = $type; }
        $sqlWhere = implode(' AND ', $where);
        $pdo = Connection::getInstance();
        $count = $pdo->prepare('SELECT COUNT(*) FROM studio_content c WHERE ' . $sqlWhere);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * self::PER_PAGE;
        $statement = $pdo->prepare('SELECT c.*,u.name deleted_by_name FROM studio_content c LEFT JOIN users u ON u.id=c.deleted_by WHERE ' . $sqlWhere . ' ORDER BY c.deleted_at DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset);
        $statement->execute($params);
        return ['items'=>$statement->fetchAll(PDO::FETCH_ASSOC),'page'=>$page,'perPage'=>self::PER_PAGE,'total'=>$total,'totalPages'=>$totalPages];
    }

    /** @return list<array{source:string,title:string,url:string}> */
    public function mediaUsage(int $mediaId): array
    {
        $pdo = Connection::getInstance(); $uses = [];
        $direct = $pdo->prepare('SELECT id,title,type FROM studio_content WHERE media_id=?'); $direct->execute([$mediaId]);
        foreach ($direct->fetchAll(PDO::FETCH_ASSOC) as $row) { $uses[] = ['source'=>'CMS · Capa','title'=>(string)$row['title'],'url'=>'/studio/' . $this->moduleForType((string)$row['type']) . '?edit=' . $row['id']]; }
        $supportCover = $pdo->prepare('SELECT id,title,slug FROM support_articles WHERE cover_media_id=?'); $supportCover->execute([$mediaId]);
        foreach ($supportCover->fetchAll(PDO::FETCH_ASSOC) as $row) { $uses[] = ['source'=>'Support · Capa','title'=>(string)$row['title'],'url'=>'/support/articles/' . $row['slug'] . '/edit']; }
        $needle = '/media/' . $mediaId;
        $cmsHtml = $pdo->prepare('SELECT id,title,type,content FROM studio_content WHERE content LIKE ?'); $cmsHtml->execute(['%' . $needle . '%']);
        foreach ($cmsHtml->fetchAll(PDO::FETCH_ASSOC) as $row) { if ($this->containsMedia((string)$row['content'], $mediaId)) { $uses[] = ['source'=>'CMS · Conteúdo','title'=>(string)$row['title'],'url'=>'/studio/' . $this->moduleForType((string)$row['type']) . '?edit=' . $row['id']]; } }
        $supportHtml = $pdo->prepare('SELECT id,title,slug,content FROM support_articles WHERE content LIKE ?'); $supportHtml->execute(['%' . $needle . '%']);
        foreach ($supportHtml->fetchAll(PDO::FETCH_ASSOC) as $row) { if ($this->containsMedia((string)$row['content'], $mediaId)) { $uses[] = ['source'=>'Support · Conteúdo','title'=>(string)$row['title'],'url'=>'/support/articles/' . $row['slug'] . '/edit']; } }
        return $uses;
    }

    public function containsMedia(string $html, int $mediaId): bool
    {
        return preg_match('~(?:src\s*=\s*["\']|url\(\s*["\']?)/media/' . preg_quote((string) $mediaId, '~') . '(?:[?#["\')\s]|$)~i', $html) === 1;
    }

    /** @return list<array<string,mixed>> */
    public function menus(): array
    {
        return Connection::getInstance()->query('SELECT m.*,COUNT(i.id) item_count FROM studio_menus m LEFT JOIN studio_menu_items i ON i.menu_id=m.id GROUP BY m.id ORDER BY m.name')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function menuItems(int $menuId): array
    {
        $statement = Connection::getInstance()->prepare('SELECT i.*,p.title page_title FROM studio_menu_items i LEFT JOIN studio_content p ON p.id=i.page_id WHERE i.menu_id=? ORDER BY i.parent_id IS NOT NULL,i.parent_id,i.position,i.id');
        $statement->execute([$menuId]); return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function publicMenu(string $location = 'principal'): array
    {
        $statement = Connection::getInstance()->prepare(
            "SELECT i.id,i.parent_id,i.label,i.target,i.position,CASE WHEN i.type='page' THEN CONCAT('/pagina/',p.slug) ELSE i.url END href "
            . "FROM studio_menus m JOIN studio_menu_items i ON i.menu_id=m.id AND i.status='active' LEFT JOIN studio_content p ON p.id=i.page_id AND p.deleted_at IS NULL AND p.status='published' "
            . "WHERE m.location=? AND m.status='active' ORDER BY i.parent_id IS NOT NULL,i.parent_id,i.position,i.id"
        );
        $statement->execute([$location]);
        return array_values(array_filter($statement->fetchAll(PDO::FETCH_ASSOC), static fn(array $item): bool => is_string($item['href']) && $item['href'] !== ''));
    }

    private function moduleForType(string $type): string
    {
        return ['page'=>'pages','project'=>'projects','article'=>'articles','highlight'=>'highlights','testimonial'=>'testimonials','faq'=>'faq'][$type] ?? 'pages';
    }
}
