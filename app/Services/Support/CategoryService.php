<?php

declare(strict_types=1);

namespace Moves\Services\Support;

use Moves\Models\Support\Category;
use Moves\Models\Support\Product;
use RuntimeException;

/**
 * Moves Support | Category Service
 *
 * Regras de negócio das categorias da Base de Conhecimento.
 */
final class CategoryService
{
    /**
     * @return Category[]
     */
    public function all(?int $productId = null): array
    {
        $model = new Category();

        if ($productId !== null) {
            $categories = $model
                ->find(
                    'product_id = :product_id',
                    ['product_id' => $productId]
                )
                ->order('position ASC, name ASC')
                ->fetch(true);
        } else {
            $categories = $model
                ->find()
                ->order('position ASC, name ASC')
                ->fetch(true);
        }

        return is_array($categories) ? $categories : [];
    }

    public function find(int $id): ?Category
    {
        return (new Category())->findById($id);
    }

    public function create(
        string $name,
        ?int $productId = null,
        ?int $parentId = null,
        ?string $description = null,
        int $position = 0
    ): Category {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('Informe o nome da categoria.');
        }

        $this->validatePosition($position);
        $this->validateProduct($productId);
        $this->validateParent($parentId, $productId);

        $category = new Category();
        $category->product_id = $productId;
        $category->parent_id = $parentId;
        $category->name = $name;
        $category->slug = $this->uniqueSlug($name, $productId);
        $category->description = $this->nullableText($description);
        $category->position = $position;
        $category->status = 'active';

        if (!$category->save()) {
            throw new RuntimeException(
                $category->message()->getText()
                ?: 'Não foi possível criar a categoria.'
            );
        }

        return $category;
    }

    /**
     * Atualiza uma categoria.
     */
    public function update(
        int $id,
        string $name,
        ?int $productId = null,
        ?int $parentId = null,
        ?string $description = null,
        int $position = 0,
        string $status = 'active'
    ): Category {
        $category = $this->find($id);

        if (!$category instanceof Category) {
            throw new RuntimeException('Categoria não encontrada.');
        }

        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('Informe o nome da categoria.');
        }

        if ($parentId === $id) {
            throw new RuntimeException(
                'Uma categoria não pode ser pai dela mesma.'
            );
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException('Status da categoria inválido.');
        }

        $this->validatePosition($position);
        $this->validateProduct($productId);
        $this->validateParent($parentId, $productId);

        $category->product_id = $productId;
        $category->parent_id = $parentId;
        $category->name = $name;
        $category->slug = $this->uniqueSlug(
            $name,
            $productId,
            $id
        );
        $category->description = $this->nullableText($description);
        $category->position = $position;
        $category->status = $status;

        if (!$category->save()) {
            throw new RuntimeException(
                $category->message()->getText()
                ?: 'Não foi possível atualizar a categoria.'
            );
        }

        return $category;
    }

    /**
     * Exclui uma categoria.
     */
    public function delete(int $id): void
    {
        $category = $this->find($id);

        if (!$category instanceof Category) {
            throw new RuntimeException('Categoria não encontrada.');
        }

        if (!$category->destroy()) {
            throw new RuntimeException(
                'Não foi possível excluir a categoria.'
            );
        }
    }

    private function validateProduct(?int $productId): void
    {
        if ($productId === null) {
            return;
        }

        if (!(new Product())->findById($productId) instanceof Product) {
            throw new RuntimeException('Produto não encontrado.');
        }
    }

    private function validateParent(
        ?int $parentId,
        ?int $productId
    ): void {
        if ($parentId === null) {
            return;
        }

        $parent = $this->find($parentId);

        if (!$parent instanceof Category) {
            throw new RuntimeException('Categoria pai não encontrada.');
        }

        $parentProductId = $parent->product_id !== null
            ? (int) $parent->product_id
            : null;

        if ($parentProductId !== $productId) {
            throw new RuntimeException(
                'A categoria pai deve pertencer ao mesmo produto.'
            );
        }
    }

    private function uniqueSlug(
        string $value,
        ?int $productId,
        ?int $ignoreId = null
    ): string {
        $base = $this->slug($value);
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug, $productId, $ignoreId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(
        string $slug,
        ?int $productId,
        ?int $ignoreId = null
    ): bool {
        $params = ['slug' => $slug];

        if ($productId === null) {
            $terms = 'product_id IS NULL AND slug = :slug';
        } else {
            $terms = 'product_id = :product_id AND slug = :slug';
            $params['product_id'] = $productId;
        }

        if ($ignoreId !== null) {
            $terms .= ' AND id != :id';
            $params['id'] = $ignoreId;
        }

        return (new Category())
            ->find($terms, $params)
            ->count() > 0;
    }

    private function validatePosition(int $position): void
    {
        if ($position < 0) {
            throw new RuntimeException(
                'A posição da categoria não pode ser negativa.'
            );
        }
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

        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'categoria';
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
