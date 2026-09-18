<?php

declare(strict_types=1);

namespace Moves\Services\Support;

use Moves\Boot\Connection;
use Moves\Models\Support\Tag;
use PDO;
use RuntimeException;

final class TagService
{
    /**
     * @return Tag[]
     */
    public function all(): array
    {
        $tags = (new Tag())
            ->find()
            ->order('name ASC')
            ->fetch(true);

        return is_array($tags) ? $tags : [];
    }

    public function find(int $id): ?Tag
    {
        return (new Tag())->findById($id);
    }

    public function findBySlug(string $slug): ?Tag
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        $tag = (new Tag())
            ->find('slug = :slug', ['slug' => $slug])
            ->fetch();

        return $tag instanceof Tag ? $tag : null;
    }

    public function create(string $name): Tag
    {
        $name = $this->normalizeName($name);

        if ($name === '') {
            throw new RuntimeException('Informe o nome da tag.');
        }

        $slug = $this->uniqueSlug($name);

        $tag = new Tag();
        $tag->name = $name;
        $tag->slug = $slug;

        if (!$tag->save()) {
            throw new RuntimeException(
                $tag->message()->getText()
                ?: 'Não foi possível criar a tag.'
            );
        }

        return $tag;
    }

    public function findOrCreate(string $name): Tag
    {
        $name = $this->normalizeName($name);

        if ($name === '') {
            throw new RuntimeException('Informe o nome da tag.');
        }

        $slug = $this->slug($name);
        $existing = $this->findBySlug($slug);

        if ($existing instanceof Tag) {
            return $existing;
        }

        return $this->create($name);
    }

    /**
     * Sincroniza as tags de um artigo.
     *
     * @param string[] $names
     */
    public function syncArticle(int $articleId, array $names): void
    {
        if ($articleId <= 0) {
            throw new RuntimeException('Artigo inválido.');
        }

        $normalized = [];

        foreach ($names as $name) {
            $name = $this->normalizeName((string) $name);

            if ($name === '') {
                continue;
            }

            $key = mb_strtolower($name);
            $normalized[$key] = $name;
        }

        $pdo = Connection::getInstance();
        $ownsTransaction = !$pdo->inTransaction();

        try {
            if ($ownsTransaction) {
                $pdo->beginTransaction();
            }

            $article = $pdo->prepare(
                'SELECT id
                   FROM support_articles
                  WHERE id = ?
                  LIMIT 1'
            );
            $article->execute([$articleId]);

            if (!$article->fetchColumn()) {
                throw new RuntimeException('Artigo não encontrado.');
            }

            $tagIds = [];

            foreach ($normalized as $name) {
                $tag = $this->findOrCreate($name);
                $tagIds[] = (int) $tag->id;
            }

            $pdo->prepare(
                'DELETE FROM support_article_tags
                  WHERE article_id = ?'
            )->execute([$articleId]);

            if ($tagIds !== []) {
                $insert = $pdo->prepare(
                    'INSERT INTO support_article_tags
                        (article_id, tag_id)
                     VALUES (?, ?)'
                );

                foreach (array_unique($tagIds) as $tagId) {
                    $insert->execute([$articleId, $tagId]);
                }
            }

            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return Tag[]
     */
    public function forArticle(int $articleId): array
    {
        $pdo = Connection::getInstance();

        $statement = $pdo->prepare(
            'SELECT t.*
               FROM support_tags t
               INNER JOIN support_article_tags at
                       ON at.tag_id = t.id
              WHERE at.article_id = ?
              ORDER BY t.name ASC'
        );

        $statement->execute([$articleId]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $tags = [];

        foreach ($rows as $row) {
            $tag = (new Tag())->findById((int) $row['id']);

            if ($tag instanceof Tag) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    public function delete(int $id): void
    {
        $tag = $this->find($id);

        if (!$tag instanceof Tag) {
            throw new RuntimeException('Tag não encontrada.');
        }

        $pdo = Connection::getInstance();

        $statement = $pdo->prepare(
            'SELECT COUNT(*)
               FROM support_article_tags
              WHERE tag_id = ?'
        );

        $statement->execute([$id]);

        if ((int) $statement->fetchColumn() > 0) {
            throw new RuntimeException(
                'A tag está vinculada a artigos e não pode ser excluída.'
            );
        }

        if (!$tag->destroy()) {
            throw new RuntimeException(
                'Não foi possível excluir a tag.'
            );
        }
    }

    private function normalizeName(string $name): string
    {
        $name = trim(strip_tags($name));
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return mb_substr($name, 0, 100);
    }

    private function uniqueSlug(
        string $name,
        ?int $ignoreId = null
    ): string {
        $base = $this->slug($name);
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
            $terms .= ' AND id != :id';
            $params['id'] = $ignoreId;
        }

        return (new Tag())
            ->find($terms, $params)
            ->count() > 0;
    }

    private function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));

        $transliterator = [
            'á'=>'a', 'à'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a',
            'é'=>'e', 'è'=>'e', 'ê'=>'e', 'ë'=>'e',
            'í'=>'i', 'ì'=>'i', 'î'=>'i', 'ï'=>'i',
            'ó'=>'o', 'ò'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o',
            'ú'=>'u', 'ù'=>'u', 'û'=>'u', 'ü'=>'u',
            'ç'=>'c',
        ];

        $value = strtr($value, $transliterator);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        if ($value === '') {
            throw new RuntimeException(
                'Não foi possível gerar o slug da tag.'
            );
        }

        return mb_substr($value, 0, 120);
    }
}
