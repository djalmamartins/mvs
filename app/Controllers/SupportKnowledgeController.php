<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Boot\Connection;
use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Services\Support\CategoryService;
use Moves\Services\Support\ProductService;
use Moves\Services\Support\TagService;
use Throwable;

final class SupportKnowledgeController extends Controller
{
    public function products(): void
    {
        $search = $this->search();
        $status = $this->status(['active', 'inactive']);
        $products = array_values(array_filter(
            (new ProductService())->all(),
            static function ($product) use ($search, $status): bool {
                if ($status !== '' && $product->status !== $status) {
                    return false;
                }

                return $search === '' || str_contains(
                    mb_strtolower(
                        (string) $product->name . ' '
                        . (string) $product->slug . ' '
                        . (string) $product->description
                    ),
                    mb_strtolower($search)
                );
            }
        ));

        echo $this->view->render('pages/support-products', [
            'title' => 'Produtos',
            'productName' => 'Suporte',
            'activeProduct' => 'support',
            'currentPage' => 'products',
            'products' => $products,
            'categoryCounts' => $this->counts(
                'support_categories',
                'product_id'
            ),
            'articleCounts' => $this->counts(
                'support_articles',
                'product_id'
            ),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function categories(): void
    {
        $service = new CategoryService();
        $search = $this->search();
        $status = $this->status(['active', 'inactive']);
        $productId = max(0, (int) Request::get('product', 0));

        $categories = array_values(array_filter(
            $service->all(),
            static function ($category) use ($search, $status, $productId): bool {
                if ($status !== '' && $category->status !== $status) {
                    return false;
                }
                if ($productId > 0 && (int) $category->product_id !== $productId) {
                    return false;
                }

                return $search === '' || str_contains(
                    mb_strtolower(
                        (string) $category->name . ' '
                        . (string) $category->slug . ' '
                        . (string) $category->description
                    ),
                    mb_strtolower($search)
                );
            }
        ));

        echo $this->view->render('pages/support-categories', [
            'title' => 'Categorias',
            'productName' => 'Suporte',
            'activeProduct' => 'support',
            'currentPage' => 'categories',
            'categories' => $categories,
            'allCategories' => $service->all(),
            'products' => (new ProductService())->all(),
            'articleCounts' => $this->counts(
                'support_articles',
                'category_id'
            ),
            'search' => $search,
            'status' => $status,
            'productId' => $productId,
        ]);
    }

    public function tags(): void
    {
        $search = $this->search();
        $tags = array_values(array_filter(
            (new TagService())->all(),
            static fn ($tag): bool => $search === '' || str_contains(
                mb_strtolower((string) $tag->name . ' ' . (string) $tag->slug),
                mb_strtolower($search)
            )
        ));

        echo $this->view->render('pages/support-tags', [
            'title' => 'Tags',
            'productName' => 'Suporte',
            'activeProduct' => 'support',
            'currentPage' => 'tags',
            'tags' => $tags,
            'articleCounts' => $this->counts(
                'support_article_tags',
                'tag_id',
                'COUNT(DISTINCT article_id)'
            ),
            'search' => $search,
        ]);
    }

    public function productSave(): never
    {
        $this->validateCsrf('/support/products');
        $service = new ProductService();
        $id = max(0, (int) Request::post('id', 0));
        $name = mb_substr(
            trim(strip_tags((string) Request::post('name', ''))),
            0,
            150
        );
        $description = trim((string) Request::post('description', ''));
        $status = $this->status(['active', 'inactive']) ?: 'active';

        try {
            $product = $id > 0
                ? $service->update($id, $name, $description, $status)
                : $service->create(
                    $name,
                    $description,
                    (int) Auth::user()->id
                );

            $this->respondSaved(
                '/support/products',
                'Produto salvo com sucesso.',
                [
                    'id' => (int) $product->id,
                    'name' => (string) $product->name,
                    'slug' => (string) $product->slug,
                    'status' => (string) $product->status,
                ]
            );
        } catch (Throwable $exception) {
            $this->respondError($exception);
        }
    }

    public function productDelete(): never
    {
        $this->validateCsrf('/support/products');

        try {
            (new ProductService())->delete(
                max(0, (int) Request::post('id', 0))
            );
            Flash::set('success', 'Produto excluído com sucesso.');
        } catch (Throwable $exception) {
            Flash::set('error', $exception->getMessage());
        }

        Response::to('/support/products');
    }

    public function categorySave(): never
    {
        $this->validateCsrf('/support/categories');
        $service = new CategoryService();
        $id = max(0, (int) Request::post('id', 0));
        $productId = max(0, (int) Request::post('product_id', 0));
        $parentId = max(0, (int) Request::post('parent_id', 0));
        $name = mb_substr(
            trim(strip_tags((string) Request::post('name', ''))),
            0,
            150
        );
        $description = trim((string) Request::post('description', ''));
        $position = max(0, (int) Request::post('position', 0));
        $status = $this->status(['active', 'inactive']) ?: 'active';

        try {
            if ($id > 0) {
                $category = $service->update(
                    $id,
                    $name,
                    $productId > 0 ? $productId : null,
                    $parentId > 0 ? $parentId : null,
                    $description,
                    $position,
                    $status
                );
            } else {
                $category = $service->create(
                    $name,
                    $productId > 0 ? $productId : null,
                    $parentId > 0 ? $parentId : null,
                    $description,
                    $position
                );

                if ($status !== 'active') {
                    $category = $service->update(
                        (int) $category->id,
                        $name,
                        $productId > 0 ? $productId : null,
                        $parentId > 0 ? $parentId : null,
                        $description,
                        $position,
                        $status
                    );
                }
            }

            $this->respondSaved(
                '/support/categories',
                'Categoria salva com sucesso.',
                [
                    'id' => (int) $category->id,
                    'name' => (string) $category->name,
                    'slug' => (string) $category->slug,
                    'product_id' => (int) ($category->product_id ?? 0),
                    'parent_id' => (int) ($category->parent_id ?? 0),
                    'status' => (string) $category->status,
                ]
            );
        } catch (Throwable $exception) {
            $this->respondError($exception);
        }
    }

    public function categoryDelete(): never
    {
        $this->validateCsrf('/support/categories');

        try {
            (new CategoryService())->delete(
                max(0, (int) Request::post('id', 0))
            );
            Flash::set('success', 'Categoria excluída com sucesso.');
        } catch (Throwable $exception) {
            Flash::set('error', $exception->getMessage());
        }

        Response::to('/support/categories');
    }

    public function tagSave(): never
    {
        $this->validateCsrf('/support/tags');
        $service = new TagService();
        $id = max(0, (int) Request::post('id', 0));
        $name = trim(strip_tags((string) Request::post('name', '')));

        try {
            $tag = $id > 0
                ? $service->update($id, $name)
                : $service->create($name);

            $this->respondSaved(
                '/support/tags',
                'Tag salva com sucesso.',
                [
                    'id' => (int) $tag->id,
                    'name' => (string) $tag->name,
                    'slug' => (string) $tag->slug,
                ]
            );
        } catch (Throwable $exception) {
            $this->respondError($exception);
        }
    }

    public function tagDelete(): never
    {
        $this->validateCsrf('/support/tags');

        try {
            (new TagService())->delete(
                max(0, (int) Request::post('id', 0))
            );
            Flash::set('success', 'Tag excluída com sucesso.');
        } catch (Throwable $exception) {
            Flash::set('error', $exception->getMessage());
        }

        Response::to('/support/tags');
    }

    private function search(): string
    {
        return mb_substr(
            trim(strip_tags((string) Request::get('q', ''))),
            0,
            120
        );
    }

    /** @param string[] $allowed */
    private function status(array $allowed): string
    {
        $status = (string) Request::input('status', '');

        return in_array($status, $allowed, true) ? $status : '';
    }

    /** @return array<int, int> */
    private function counts(
        string $table,
        string $column,
        string $aggregate = 'COUNT(*)'
    ): array {
        if (!preg_match('/^[a-z_]+$/', $table . $column)) {
            return [];
        }

        $from = " FROM {$table}";
        $where = '';
        if ($table === 'support_articles') {
            $where = ' WHERE deleted_at IS NULL';
        } elseif ($table === 'support_article_tags') {
            $from .= ' INNER JOIN support_articles ON support_articles.id = support_article_tags.article_id';
            $where = ' WHERE support_articles.deleted_at IS NULL';
        }
        $statement = Connection::getInstance()->query(
            "SELECT {$column} AS item_id, {$aggregate} AS total"
            . $from . $where . " GROUP BY {$column}"
        );
        $counts = [];

        foreach ($statement->fetchAll() as $row) {
            $counts[(int) $row['item_id']] = (int) $row['total'];
        }

        return $counts;
    }

    /** @param array<string, mixed> $item */
    private function respondSaved(
        string $redirect,
        string $message,
        array $item
    ): never {
        if (Request::post('response') === 'json') {
            Response::json([
                'ok' => true,
                'message' => $message,
                'item' => $item,
            ]);
        }

        Flash::set('success', $message);
        Response::to($redirect);
    }

    private function respondError(Throwable $exception): never
    {
        if (Request::post('response') === 'json') {
            Response::json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 422);
        }

        Flash::set('error', $exception->getMessage());
        Response::to('/support');
    }

    private function validateCsrf(string $redirect): void
    {
        $token = Request::post('_token');

        if (!is_string($token) || !Csrf::validate($token)) {
            if (Request::post('response') === 'json') {
                Response::json([
                    'ok' => false,
                    'error' => 'Token de segurança inválido.',
                ], 419);
            }

            Flash::set('error', 'Token de segurança inválido.');
            Response::to($redirect);
        }
    }
}
