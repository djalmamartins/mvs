<?php

declare(strict_types=1);

namespace Moves\Services;

use DOMDocument;
use DOMElement;
use Moves\Boot\Connection;
use Moves\Core\HtmlSanitizer;
use PDO;

final class HelpService
{
    private const PUBLISHED = "a.status = 'published' AND a.deleted_at IS NULL AND (a.published_at IS NULL OR a.published_at <= NOW())";

    /** @return array{products:list<array<string,mixed>>,categories:list<array<string,mixed>>,articles:list<array<string,mixed>>} */
    public function home(): array
    {
        $pdo = Connection::getInstance();
        $products = $pdo->query(
            "SELECT p.id,p.name,p.slug,p.description,COUNT(a.id) article_count
               FROM support_products p
               JOIN support_articles a ON a.product_id=p.id AND " . self::PUBLISHED . "
              WHERE p.status='active'
              GROUP BY p.id,p.name,p.slug,p.description
              ORDER BY p.name"
        )->fetchAll(PDO::FETCH_ASSOC);
        $categories = $pdo->query(
            "SELECT c.id,c.name,c.slug,c.description,c.parent_id,p.name product_name,p.slug product_slug,COUNT(a.id) article_count
               FROM support_categories c
               JOIN support_products p ON p.id=c.product_id AND p.status='active'
               JOIN support_articles a ON a.category_id=c.id AND " . self::PUBLISHED . "
              WHERE c.status='active'
              GROUP BY c.id,c.name,c.slug,c.description,c.parent_id,p.name,p.slug
              ORDER BY p.name,c.position,c.name"
        )->fetchAll(PDO::FETCH_ASSOC);
        $articles = $pdo->query($this->articleSelect() . " WHERE " . self::PUBLISHED . " ORDER BY a.updated_at DESC,a.id DESC LIMIT 8")
            ->fetchAll(PDO::FETCH_ASSOC);
        return compact('products', 'categories', 'articles');
    }

    /** @return array{query:string,items:list<array<string,mixed>>} */
    public function search(string $query): array
    {
        $query = mb_substr(trim(strip_tags($query)), 0, 100);
        if ($query === '') {
            return ['query' => '', 'items' => []];
        }
        $statement = Connection::getInstance()->prepare(
            $this->articleSelect() . " WHERE " . self::PUBLISHED . "
             AND (a.title LIKE :title OR a.excerpt LIKE :excerpt OR a.content LIKE :content)
             ORDER BY a.title ASC LIMIT 50"
        );
        $like = '%' . $query . '%';
        $statement->execute(['title' => $like, 'excerpt' => $like, 'content' => $like]);
        $items = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$item) {
            $item['snippet'] = $this->snippet((string) ($item['excerpt'] ?: $item['content']), $query);
        }
        unset($item);
        return ['query' => $query, 'items' => $items];
    }

    /** @return array{product:array<string,mixed>,categories:list<array<string,mixed>>,articles:list<array<string,mixed>>}|null */
    public function product(string $slug): ?array
    {
        $pdo = Connection::getInstance();
        $statement = $pdo->prepare("SELECT id,name,slug,description FROM support_products WHERE slug=? AND status='active' LIMIT 1");
        $statement->execute([$slug]);
        $product = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            return null;
        }
        $categories = $pdo->prepare(
            "SELECT c.id,c.name,c.slug,c.description,c.parent_id,COUNT(a.id) article_count
               FROM support_categories c
               JOIN support_articles a ON a.category_id=c.id AND " . self::PUBLISHED . "
              WHERE c.product_id=? AND c.status='active'
              GROUP BY c.id,c.name,c.slug,c.description,c.parent_id
              ORDER BY c.position,c.name"
        );
        $categories->execute([(int) $product['id']]);
        $articles = $pdo->prepare($this->articleSelect() . " WHERE " . self::PUBLISHED . " AND a.product_id=? ORDER BY a.title");
        $articles->execute([(int) $product['id']]);
        return ['product' => $product, 'categories' => $categories->fetchAll(PDO::FETCH_ASSOC), 'articles' => $articles->fetchAll(PDO::FETCH_ASSOC)];
    }

    /** @return array{category:array<string,mixed>,articles:list<array<string,mixed>>,children:list<array<string,mixed>>}|null */
    public function category(string $slug): ?array
    {
        $pdo = Connection::getInstance();
        $statement = $pdo->prepare(
            "SELECT c.id,c.name,c.slug,c.description,c.parent_id,p.name product_name,p.slug product_slug
               FROM support_categories c JOIN support_products p ON p.id=c.product_id
              WHERE c.slug=? AND c.status='active' AND p.status='active' ORDER BY c.id LIMIT 1"
        );
        $statement->execute([$slug]);
        $category = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$category) {
            return null;
        }
        $articles = $pdo->prepare($this->articleSelect() . " WHERE " . self::PUBLISHED . " AND a.category_id=? ORDER BY a.title");
        $articles->execute([(int) $category['id']]);
        $children = $pdo->prepare(
            "SELECT c.id,c.name,c.slug,c.description,COUNT(a.id) article_count
               FROM support_categories c
               JOIN support_articles a ON a.category_id=c.id AND " . self::PUBLISHED . "
              WHERE c.parent_id=? AND c.status='active'
              GROUP BY c.id,c.name,c.slug,c.description ORDER BY c.position,c.name"
        );
        $children->execute([(int) $category['id']]);
        return ['category' => $category, 'articles' => $articles->fetchAll(PDO::FETCH_ASSOC), 'children' => $children->fetchAll(PDO::FETCH_ASSOC)];
    }

    /** @return array{article:array<string,mixed>,tags:list<array<string,mixed>>,related:list<array<string,mixed>>,sectionArticles:list<array<string,mixed>>,feedback:array{yes:int,no:int,total:int},toc:list<array{id:string,label:string,level:int}>}|null */
    public function article(string $slug): ?array
    {
        $pdo = Connection::getInstance();
        $statement = $pdo->prepare($this->articleSelect(true) . " WHERE " . self::PUBLISHED . " AND a.slug=? LIMIT 1");
        $statement->execute([$slug]);
        $article = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$article) {
            return null;
        }
        [$content, $toc] = $this->contentWithToc((string) ($article['content'] ?? ''));
        $article['rendered_content'] = $content;
        $tags = $pdo->prepare('SELECT t.name,t.slug FROM support_tags t JOIN support_article_tags at ON at.tag_id=t.id WHERE at.article_id=? ORDER BY t.name');
        $tags->execute([(int) $article['id']]);
        $related = $pdo->prepare(
            $this->articleSelect() . " WHERE " . self::PUBLISHED . " AND a.id != :exclude_id
             ORDER BY ((a.category_id <=> :category_id) * 30 + (a.product_id <=> :product_id) * 10 +
              (SELECT COUNT(*) FROM support_article_tags own_tags JOIN support_article_tags candidate_tags ON candidate_tags.tag_id=own_tags.tag_id WHERE own_tags.article_id=:article_id AND candidate_tags.article_id=a.id) * 20) DESC,
              a.updated_at DESC LIMIT 4"
        );
        $related->execute([
            'category_id' => $article['category_id'],
            'product_id' => $article['product_id'],
            'article_id' => $article['id'],
            'exclude_id' => $article['id'],
        ]);
        $section = $pdo->prepare($this->articleSelect() . " WHERE " . self::PUBLISHED . " AND a.category_id=? ORDER BY a.title LIMIT 8");
        $section->execute([(int) $article['category_id']]);
        $feedback = $pdo->prepare('SELECT SUM(helpful=1) yes_count,SUM(helpful=0) no_count,COUNT(*) total FROM support_article_feedback WHERE article_id=?');
        $feedback->execute([(int) $article['id']]);
        $feedbackRow = $feedback->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'article' => $article,
            'tags' => $tags->fetchAll(PDO::FETCH_ASSOC),
            'related' => $related->fetchAll(PDO::FETCH_ASSOC),
            'sectionArticles' => $section->fetchAll(PDO::FETCH_ASSOC),
            'feedback' => ['yes' => (int) ($feedbackRow['yes_count'] ?? 0), 'no' => (int) ($feedbackRow['no_count'] ?? 0), 'total' => (int) ($feedbackRow['total'] ?? 0)],
            'toc' => $toc,
        ];
    }

    public function recordFeedback(int $articleId, string $visitorHash, bool $helpful): void
    {
        $statement = Connection::getInstance()->prepare(
            'INSERT INTO support_article_feedback(article_id,visitor_hash,helpful) VALUES(?,?,?) '
            . 'ON DUPLICATE KEY UPDATE helpful=VALUES(helpful),updated_at=CURRENT_TIMESTAMP'
        );
        $statement->execute([$articleId, $visitorHash, $helpful ? 1 : 0]);
    }

    private function articleSelect(bool $withContent = false): string
    {
        return 'SELECT a.id,a.product_id,a.category_id,a.title,a.slug,a.excerpt,'
            . ($withContent ? 'a.content,' : '')
            . 'a.cover_media_id,a.meta_title,a.meta_description,a.canonical_url,a.robots_index,a.robots_follow,a.reading_time,a.published_at,a.updated_at,'
            . 'p.name product_name,p.slug product_slug,c.name category_name,c.slug category_slug,m.alt_text cover_alt '
            . 'FROM support_articles a LEFT JOIN support_products p ON p.id=a.product_id LEFT JOIN support_categories c ON c.id=a.category_id LEFT JOIN studio_media m ON m.id=a.cover_media_id';
    }

    private function snippet(string $html, string $query): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        $position = mb_stripos($text, $query);
        $start = $position === false ? 0 : max(0, $position - 70);
        $snippet = mb_substr($text, $start, 210);
        return ($start > 0 ? '…' : '') . $snippet . (mb_strlen($text) > $start + 210 ? '…' : '');
    }

    /** @return array{0:string,1:list<array{id:string,label:string,level:int}>} */
    private function contentWithToc(string $html): array
    {
        $clean = HtmlSanitizer::clean($html);
        if ($clean === '') {
            return ['', []];
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div id="help-content">' . $clean . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementById('help-content');
        if (!$root) {
            return [$clean, []];
        }
        $toc = [];
        $used = [];
        foreach (iterator_to_array($root->getElementsByTagName('*')) as $element) {
            if (!in_array(strtolower($element->tagName), ['h2', 'h3'], true)) {
                continue;
            }
            $base = $this->slug((string) $element->textContent) ?: 'secao';
            $id = $base;
            $suffix = 2;
            while (isset($used[$id])) {
                $id = $base . '-' . $suffix++;
            }
            $used[$id] = true;
            $element->setAttribute('id', $id);
            $toc[] = ['id' => $id, 'label' => trim((string) $element->textContent), 'level' => strtolower($element->tagName) === 'h3' ? 3 : 2];
        }
        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }
        return [trim($output), $toc];
    }

    private function slug(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower(trim($value))) ?: '';
        return trim(preg_replace('/[^a-z0-9]+/', '-', $value) ?? '', '-');
    }
}
