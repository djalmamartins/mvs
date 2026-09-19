<?php

declare(strict_types=1);

namespace Moves\Services\Support;

use Moves\Models\Support\Article;
use Moves\Models\Support\ArticleRevision;
use Moves\Models\Support\Category;
use Moves\Models\Support\Product;
use Moves\Models\User;
use Moves\Boot\Connection;
use PDO;
use RuntimeException;

/**
 * Moves Support | Article Service
 *
 * Regras de negócio dos artigos da Base de Conhecimento.
 */
final class ArticleService
{
    /**
     * @return Article[]
     */
    public function all(): array
    {
        $articles = (new Article())
            ->find('deleted_at IS NULL')
            ->order('updated_at DESC, id DESC')
            ->fetch(true);

        return is_array($articles) ? $articles : [];
    }

    public function find(int $id): ?Article
    {
        $article = (new Article())
            ->find('id = :id AND deleted_at IS NULL', ['id' => $id])
            ->fetch();

        return $article instanceof Article ? $article : null;
    }

    public function findBySlug(string $slug): ?Article
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        $record = (new Article())
            ->find(
                'slug = :slug AND deleted_at IS NULL',
                ['slug' => $slug]
            )
            ->fetch();

        return $record instanceof Article
            ? $record
            : null;
    }

    /**
     * @return array{items:Article[],total:int,page:int,per_page:int,pages:int,from:int,to:int}
     */
    public function paginate(
        string $search = '',
        ?int $productId = null,
        ?int $categoryId = null,
        ?string $status = null,
        int $page = 1,
        int $perPage = 10,
        bool $trashed = false
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $terms = [$trashed ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL'];
        $params = [];

        if ($search !== '') {
            $terms[] = '(title LIKE :search_title OR excerpt LIKE :search_excerpt OR slug LIKE :search_slug)';
            $params['search_title'] = '%' . $search . '%';
            $params['search_excerpt'] = '%' . $search . '%';
            $params['search_slug'] = '%' . $search . '%';
        }
        if ($productId !== null && $productId > 0) {
            $terms[] = 'product_id = :product_id';
            $params['product_id'] = $productId;
        }
        if ($categoryId !== null && $categoryId > 0) {
            $terms[] = 'category_id = :category_id';
            $params['category_id'] = $categoryId;
        }
        if ($status !== null && $status !== '') {
            if (!in_array($status, ['draft', 'published', 'archived'], true)) {
                throw new RuntimeException('Status do artigo inválido.');
            }
            $terms[] = 'status = :status';
            $params['status'] = $status;
        }

        $where = implode(' AND ', $terms);
        $model = (new Article())->find($where, $params);
        $total = $model->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $items = (new Article())
            ->find($where, $params)
            ->order($trashed ? 'deleted_at DESC, id DESC' : 'updated_at DESC, id DESC')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->fetch(true);

        $items = is_array($items) ? $items : [];
        $from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
            'from' => $from,
            'to' => $total === 0 ? 0 : $from + count($items) - 1,
        ];
    }

    /**
     * @return array{items:array<int,object>,total:int,page:int,per_page:int,pages:int,from:int,to:int}
     */
    public function paginateRevisions(
        string $search = '',
        int $page = 1,
        int $perPage = 10
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $params = [];
        $where = ' WHERE a.deleted_at IS NULL';

        if ($search !== '') {
            $where .= ' AND (r.title LIKE :revision_title OR a.title LIKE :article_title)';
            $params['revision_title'] = '%' . $search . '%';
            $params['article_title'] = '%' . $search . '%';
        }

        $pdo = Connection::getInstance();
        $count = $pdo->prepare(
            'SELECT COUNT(*) FROM support_article_revisions r
             INNER JOIN support_articles a ON a.id = r.article_id' . $where
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $statement = $pdo->prepare(
            'SELECT r.*, a.title AS article_title, a.slug AS article_slug,
                    u.name AS author_name
               FROM support_article_revisions r
               INNER JOIN support_articles a ON a.id = r.article_id
               LEFT JOIN users u ON u.id = r.created_by' . $where . '
              ORDER BY r.created_at DESC, r.id DESC
              LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $statement->execute($params);
        $items = $statement->fetchAll(PDO::FETCH_OBJ);
        $from = $total === 0 ? 0 : $offset + 1;

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
            'from' => $from,
            'to' => $total === 0 ? 0 : $from + count($items) - 1,
        ];
    }

    public function trash(int $id, ?int $deletedBy = null): void
    {
        $article = $this->find($id);
        if (!$article instanceof Article) {
            throw new RuntimeException('Artigo não encontrado.');
        }
        $this->validateAuthor($deletedBy);
        $article->deleted_at = date('Y-m-d H:i:s');
        $article->deleted_by = $deletedBy;
        if (!$article->save()) {
            throw new RuntimeException('Não foi possível mover o artigo para a lixeira.');
        }
    }

    public function restore(int $id): void
    {
        $article = $this->findTrashed($id);
        if (!$article instanceof Article) {
            throw new RuntimeException('Artigo não encontrado na lixeira.');
        }
        $article->deleted_at = null;
        $article->deleted_by = null;
        if (!$article->save()) {
            throw new RuntimeException('Não foi possível restaurar o artigo.');
        }
    }

    public function permanentDelete(int $id): void
    {
        $article = $this->findTrashed($id);
        if (!$article instanceof Article) {
            throw new RuntimeException('Artigo não encontrado na lixeira.');
        }
        if (!$article->destroy()) {
            throw new RuntimeException('Não foi possível excluir o artigo permanentemente.');
        }
    }

    private function findTrashed(int $id): ?Article
    {
        $article = (new Article())
            ->find('id = :id AND deleted_at IS NOT NULL', ['id' => $id])
            ->fetch();

        return $article instanceof Article ? $article : null;
    }

    /**
     * @param string[] $tags
     */
    public function create(
        string $title,
        ?int $productId = null,
        ?int $categoryId = null,
        ?string $excerpt = null,
        ?string $content = null,
        ?int $authorId = null,
        ?string $slug = null,
        ?int $coverMediaId = null,
        ?string $metaTitle = null,
        ?string $metaDescription = null,
        ?string $focusKeyword = null,
        ?string $canonicalUrl = null,
        bool $robotsIndex = true,
        bool $robotsFollow = true,
        array $tags = [],
        string $status = 'draft'
    ): Article {
        $title = $this->cleanText($title, 255);

        if ($title === '') {
            throw new RuntimeException('Informe o título do artigo.');
        }

        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            throw new RuntimeException('Status do artigo inválido.');
        }

        $this->validateProduct($productId);
        $this->validateCategory($categoryId, $productId);
        $this->validateAuthor($authorId);
        $this->validateMedia($coverMediaId);

        $slug = $this->uniqueSlug(
            $slug !== null && trim($slug) !== ''
                ? $slug
                : $title
        );

        $content = $this->nullableText($content);
        [$wordCount, $readingTime] = $this->contentMetrics($content);

        $article = new Article();
        $article->product_id = $productId;
        $article->category_id = $categoryId;
        $article->title = $title;
        $article->slug = $slug;
        $article->excerpt = $this->nullableText($excerpt);
        $article->content = $content;
        $article->cover_media_id = $coverMediaId;
        $article->meta_title = $this->nullableLimited($metaTitle, 255);
        $article->meta_description = $this->nullableLimited(
            $metaDescription,
            320
        );
        $article->focus_keyword = $this->nullableLimited(
            $focusKeyword,
            150
        );
        $article->canonical_url = $this->normalizeCanonical($canonicalUrl);
        $article->robots_index = $robotsIndex ? 1 : 0;
        $article->robots_follow = $robotsFollow ? 1 : 0;
        $article->word_count = $wordCount;
        $article->reading_time = $readingTime;
        $article->status = $status;
        $article->author_id = $authorId;
        $article->published_at = $status === 'published'
            ? date('Y-m-d H:i:s')
            : null;

        $pdo = Connection::getInstance();

        try {
            $pdo->beginTransaction();

            if (!$article->save()) {
                throw new RuntimeException(
                    $article->message()->getText()
                    ?: 'Não foi possível criar o artigo.'
                );
            }

            (new TagService())->syncArticle(
                (int) $article->id,
                $tags
            );

            $pdo->commit();

            return $article;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param string[] $tags
     */
    public function update(
        int $id,
        string $title,
        ?int $productId = null,
        ?int $categoryId = null,
        ?string $excerpt = null,
        ?string $content = null,
        string $status = 'draft',
        ?int $authorId = null,
        ?int $updatedBy = null,
        ?string $slug = null,
        ?int $coverMediaId = null,
        ?string $metaTitle = null,
        ?string $metaDescription = null,
        ?string $focusKeyword = null,
        ?string $canonicalUrl = null,
        bool $robotsIndex = true,
        bool $robotsFollow = true,
        array $tags = []
    ): Article {
        $article = $this->find($id);

        if (!$article instanceof Article) {
            throw new RuntimeException('Artigo não encontrado.');
        }

        $title = $this->cleanText($title, 255);

        if ($title === '') {
            throw new RuntimeException('Informe o título do artigo.');
        }

        if (!in_array(
            $status,
            ['draft', 'published', 'archived'],
            true
        )) {
            throw new RuntimeException('Status do artigo inválido.');
        }

        $this->validateProduct($productId);
        $this->validateCategory($categoryId, $productId);
        $this->validateAuthor($authorId);
        $this->validateAuthor($updatedBy);
        $this->validateMedia($coverMediaId);

        /*
         * URL estável:
         * sem slug informado, preservamos o atual.
         * Só alteramos quando o usuário realmente editar o slug.
         */
        $requestedSlug = $slug !== null && trim($slug) !== ''
            ? $slug
            : (string) $article->slug;

        $requestedSlug = $this->uniqueSlug(
            $requestedSlug,
            $id
        );

        $content = $this->nullableText($content);
        [$wordCount, $readingTime] = $this->contentMetrics($content);

        $excerpt = $this->nullableText($excerpt);
        $hasContentChange = (string) $article->title !== $title
            || (string) ($article->excerpt ?? '') !== (string) ($excerpt ?? '')
            || (string) ($article->content ?? '') !== (string) ($content ?? '');

        $pdo = Connection::getInstance();

        try {
            $pdo->beginTransaction();

            if ($hasContentChange) {
                $this->createRevision($id, $updatedBy);
            }

            $article->product_id = $productId;
            $article->category_id = $categoryId;
            $article->title = $title;
            $article->slug = $requestedSlug;
            $article->excerpt = $excerpt;
            $article->content = $content;
            $article->cover_media_id = $coverMediaId;
            $article->meta_title = $this->nullableLimited(
                $metaTitle,
                255
            );
            $article->meta_description = $this->nullableLimited(
                $metaDescription,
                320
            );
            $article->focus_keyword = $this->nullableLimited(
                $focusKeyword,
                150
            );
            $article->canonical_url = $this->normalizeCanonical(
                $canonicalUrl
            );
            $article->robots_index = $robotsIndex ? 1 : 0;
            $article->robots_follow = $robotsFollow ? 1 : 0;
            $article->word_count = $wordCount;
            $article->reading_time = $readingTime;
            $article->status = $status;
            $article->author_id = $authorId;

            if (
                $status === 'published'
                && $article->published_at === null
            ) {
                $article->published_at = date('Y-m-d H:i:s');
            } elseif ($status !== 'published') {
                $article->published_at = null;
            }

            if (!$article->save()) {
                throw new RuntimeException(
                    $article->message()->getText()
                    ?: 'Não foi possível atualizar o artigo.'
                );
            }

            (new TagService())->syncArticle($id, $tags);

            $pdo->commit();

            return $article;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return ArticleRevision[]
     */
    public function revisions(int $articleId): array
    {
        if (!$this->find($articleId) instanceof Article) {
            throw new RuntimeException(
                'Artigo não encontrado.'
            );
        }

        $revisions = (new ArticleRevision())
            ->find(
                'article_id = :article_id',
                ['article_id' => $articleId]
            )
            ->order('created_at DESC, id DESC')
            ->fetch(true);

        return is_array($revisions) ? $revisions : [];
    }

    public function createRevision(
        int $articleId,
        ?int $createdBy = null
    ): ArticleRevision {
        $article = $this->find($articleId);

        if (!$article instanceof Article) {
            throw new RuntimeException(
                'Artigo não encontrado.'
            );
        }

        $this->validateAuthor($createdBy);

        $revision = new ArticleRevision();
        $revision->article_id = $articleId;
        $revision->title = (string) $article->title;
        $revision->excerpt = $article->excerpt;
        $revision->content = $article->content;
        $revision->created_by = $createdBy;

        if (!$revision->save()) {
            throw new RuntimeException(
                $revision->message()->getText()
                ?: 'Não foi possível criar a revisão do artigo.'
            );
        }

        return $revision;
    }

    private function validateMedia(?int $mediaId): void
    {
        if ($mediaId === null) {
            return;
        }

        $statement = Connection::getInstance()->prepare(
            'SELECT 1
               FROM studio_media
              WHERE id = ?
              LIMIT 1'
        );

        $statement->execute([$mediaId]);

        if (!$statement->fetchColumn()) {
            throw new RuntimeException(
                'A imagem de capa selecionada não foi encontrada.'
            );
        }
    }

    /**
     * @return array{0:int,1:int}
     */
    private function contentMetrics(?string $html): array
    {
        if ($html === null || trim($html) === '') {
            return [0, 0];
        }

        $text = html_entity_decode(
            strip_tags($html),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        if ($text === '') {
            return [0, 0];
        }

        preg_match_all(
            '/[\p{L}\p{N}]+(?:[\'’\-][\p{L}\p{N}]+)*/u',
            $text,
            $matches
        );

        $wordCount = count($matches[0]);

        /*
         * Referência editorial do Moves:
         * aproximadamente 200 palavras por minuto.
         */
        $readingTime = $wordCount > 0
            ? max(1, (int) ceil($wordCount / 200))
            : 0;

        return [$wordCount, $readingTime];
    }

    private function normalizeCanonical(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (
            filter_var($url, FILTER_VALIDATE_URL) === false
            || !preg_match('#^https?://#i', $url)
        ) {
            throw new RuntimeException(
                'Informe uma URL canonical válida.'
            );
        }

        return mb_substr($url, 0, 500);
    }

    private function nullableLimited(
        ?string $value,
        int $length
    ): ?string {
        $value = trim(strip_tags((string) $value));

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $length);
    }

    private function cleanText(
        string $value,
        int $length
    ): string {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_substr($value, 0, $length);
    }

    private function validateProduct(?int $productId): void
    {
        if ($productId === null) {
            return;
        }

        if (!(new Product())->findById($productId) instanceof Product) {
            throw new RuntimeException(
                'Produto não encontrado.'
            );
        }
    }

    private function validateCategory(
        ?int $categoryId,
        ?int $productId
    ): void {
        if ($categoryId === null) {
            return;
        }

        $category = (new Category())->findById($categoryId);

        if (!$category instanceof Category) {
            throw new RuntimeException(
                'Categoria não encontrada.'
            );
        }

        $categoryProductId = $category->product_id !== null
            ? (int) $category->product_id
            : null;

        if ($categoryProductId !== $productId) {
            throw new RuntimeException(
                'A categoria deve pertencer ao mesmo produto do artigo.'
            );
        }
    }

    private function validateAuthor(?int $authorId): void
    {
        if ($authorId === null) {
            return;
        }

        if (!(new User())->findById($authorId) instanceof User) {
            throw new RuntimeException(
                'Autor não encontrado.'
            );
        }
    }

    private function uniqueSlug(
        string $value,
        ?int $ignoreId = null
    ): string {
        $base = $this->slug($value);
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(
        string $slug,
        ?int $ignoreId = null
    ): bool {
        $terms = 'slug = :slug';
        $params = ['slug' => $slug];

        if ($ignoreId !== null) {
            $terms .= ' AND id != :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        return (new Article())
            ->find($terms, $params)
            ->count() > 0;
    }

    private function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));

        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
            'ñ' => 'n',
        ]);

        $value = preg_replace(
            '/[^a-z0-9]+/',
            '-',
            $value
        ) ?? '';

        $value = trim($value, '-');

        return $value !== '' ? $value : 'artigo';
    }

    private function nullableText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
