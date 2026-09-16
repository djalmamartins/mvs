<?php

declare(strict_types=1);

namespace Moves\Services\Support;

use Moves\Models\Support\Article;
use Moves\Models\Support\ArticleRevision;
use Moves\Models\Support\Category;
use Moves\Models\Support\Product;
use Moves\Models\User;
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
            ->find()
            ->order('updated_at DESC, id DESC')
            ->fetch(true);

        return is_array($articles) ? $articles : [];
    }

    public function find(int $id): ?Article
    {
        return (new Article())->findById($id);
    }

    public function create(
        string $title,
        ?int $productId = null,
        ?int $categoryId = null,
        ?string $excerpt = null,
        ?string $content = null,
        ?int $authorId = null
    ): Article {
        $title = trim($title);

        if ($title === '') {
            throw new RuntimeException(
                'Informe o título do artigo.'
            );
        }

        $this->validateProduct($productId);
        $this->validateCategory($categoryId, $productId);
        $this->validateAuthor($authorId);

        $article = new Article();
        $article->product_id = $productId;
        $article->category_id = $categoryId;
        $article->title = $title;
        $article->slug = $this->uniqueSlug($title);
        $article->excerpt = $this->nullableText($excerpt);
        $article->content = $this->nullableText($content);
        $article->status = 'draft';
        $article->author_id = $authorId;
        $article->published_at = null;

        if (!$article->save()) {
            throw new RuntimeException(
                $article->message()->getText()
                ?: 'Não foi possível criar o artigo.'
            );
        }

        return $article;
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

    private function uniqueSlug(string $value): string
    {
        $base = $this->slug($value);
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug): bool
    {
        return (new Article())
            ->find(
                'slug = :slug',
                ['slug' => $slug]
            )
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
