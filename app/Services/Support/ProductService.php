<?php

declare(strict_types=1);

namespace Moves\Services\Support;

use Moves\Boot\Connection;
use Moves\Models\Support\Product;
use RuntimeException;

/**
 * Moves Support | Product Service
 *
 * Regras de negócio dos produtos da Base de Conhecimento.
 */
final class ProductService
{
    /**
     * Retorna os produtos cadastrados.
     *
     * @return Product[]
     */
    public function all(): array
    {
        $products = (new Product())
            ->find()
            ->order('name ASC')
            ->fetch(true);

        return is_array($products) ? $products : [];
    }

    /**
     * Retorna um produto pelo ID.
     */
    public function find(int $id): ?Product
    {
        return (new Product())->findById($id);
    }

    /**
     * Cria um produto.
     */
    public function create(
        string $name,
        ?string $description = null,
        ?int $createdBy = null
    ): Product {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('Informe o nome do produto.');
        }

        $product = new Product();
        $product->name = $name;
        $product->slug = $this->uniqueSlug($name);
        $product->description = $this->nullableText($description);
        $product->status = 'active';
        $product->created_by = $createdBy;

        if (!$product->save()) {
            throw new RuntimeException(
                $product->message()->text()
                ?: 'Não foi possível criar o produto.'
            );
        }

        return $product;
    }

    /**
     * Atualiza um produto.
     */
    public function update(
        int $id,
        string $name,
        ?string $description = null,
        string $status = 'active'
    ): Product {
        $product = $this->find($id);

        if (!$product instanceof Product) {
            throw new RuntimeException('Produto não encontrado.');
        }

        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('Informe o nome do produto.');
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException('Status do produto inválido.');
        }

        $product->name = $name;
        $product->slug = $this->uniqueSlug($name, $id);
        $product->description = $this->nullableText($description);
        $product->status = $status;

        if (!$product->save()) {
            throw new RuntimeException(
                $product->message()->text()
                ?: 'Não foi possível atualizar o produto.'
            );
        }

        return $product;
    }

    /**
     * Exclui um produto.
     */
    public function delete(int $id): void
    {
        $product = $this->find($id);

        if (!$product instanceof Product) {
            throw new RuntimeException('Produto não encontrado.');
        }

        $statement = Connection::getInstance()->prepare(
            'SELECT
                (SELECT COUNT(*) FROM support_categories WHERE product_id = ?) AS categories,
                (SELECT COUNT(*) FROM support_articles WHERE product_id = ?) AS articles'
        );
        $statement->execute([$id, $id]);
        $usage = $statement->fetch(\PDO::FETCH_ASSOC) ?: [];
        if ((int) ($usage['categories'] ?? 0) > 0 || (int) ($usage['articles'] ?? 0) > 0) {
            throw new RuntimeException('O produto está vinculado a categorias ou artigos e não pode ser excluído.');
        }

        if (!$product->destroy()) {
            throw new RuntimeException(
                'Não foi possível excluir o produto.'
            );
        }
    }

    /**
     * Gera um slug disponível para produtos.
     */
    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
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
            $terms .= ' AND id != :id';
            $params['id'] = $ignoreId;
        }

        return (new Product())
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

        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'produto';
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
