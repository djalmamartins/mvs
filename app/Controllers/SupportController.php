<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\Logger;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Models\User;
use Moves\Services\Support\ArticleService;
use Moves\Services\Support\CategoryService;
use Moves\Services\Support\ProductService;
use Moves\Services\Support\TagService;
use Throwable;

final class SupportController extends Controller
{
    public function articles(): void
    {
        $articleService = new ArticleService();
        $productService = new ProductService();
        $categoryService = new CategoryService();

        $search = mb_substr(
            trim(strip_tags((string) Request::get('q', ''))),
            0,
            120
        );
        $status = (string) Request::get('status', '');

        if (!in_array($status, ['', 'draft', 'published', 'archived'], true)) {
            $status = '';
        }

        $productId = max(0, (int) Request::get('product', 0));
        $categoryId = max(0, (int) Request::get('category', 0));

        $articles = array_values(array_filter(
            $articleService->all(),
            static function ($article) use (
                $search,
                $status,
                $productId,
                $categoryId
            ): bool {
                if ($status !== '' && $article->status !== $status) {
                    return false;
                }
                if ($productId > 0 && (int) $article->product_id !== $productId) {
                    return false;
                }
                if ($categoryId > 0 && (int) $article->category_id !== $categoryId) {
                    return false;
                }
                if ($search !== '') {
                    $haystack = mb_strtolower(
                        (string) $article->title . ' '
                        . strip_tags((string) $article->excerpt) . ' '
                        . strip_tags((string) $article->content)
                    );
                    if (!str_contains($haystack, mb_strtolower($search))) {
                        return false;
                    }
                }
                return true;
            }
        ));

        echo $this->view->render('pages/support-articles', [
            'title' => 'Artigos',
            'productName' => 'Suporte',
            'activeProduct' => 'support',
            'currentPage' => 'articles',
            'articles' => $articles,
            'products' => $productService->all(),
            'categories' => $categoryService->all(),
            'search' => $search,
            'status' => $status,
            'productId' => $productId,
            'categoryId' => $categoryId,
        ]);
    }

    /**
     * @param array<string, string> $data
     */
    public function articleForm(array $data = []): void
    {
        $articleService = new ArticleService();
        $productService = new ProductService();
        $categoryService = new CategoryService();
        $tagService = new TagService();

        $slug = trim((string) ($data['slug'] ?? ''));
        $article = $slug !== '' ? $articleService->findBySlug($slug) : null;

        if ($slug !== '' && $article === null) {
            Flash::set('error', 'Artigo não encontrado.');
            Response::to('/support/articles');
        }

        echo $this->view->render('pages/support-article-form', [
            'title' => $article === null ? 'Novo artigo' : 'Editar artigo',
            'productName' => 'Suporte',
            'activeProduct' => 'support',
            'currentPage' => 'articles',
            'article' => $article,
            'products' => $productService->all(),
            'categories' => $categoryService->all(),
            'authors' => (new User())
                ->find('status = :status', ['status' => 'active'])
                ->order('name ASC')
                ->fetch(true),
            'tags' => $article === null
                ? []
                : $tagService->forArticle((int) $article->id),
            'revisions' => $article === null
                ? []
                : $articleService->revisions((int) $article->id),
        ]);
    }

    public function articleSave(): never
    {
        $this->validateCsrf('/support/articles');
        $service = new ArticleService();
        $id = max(0, (int) Request::post('id', 0));
        $title = mb_substr(
            trim(strip_tags((string) Request::post('title', ''))),
            0,
            255
        );
        $productId = max(0, (int) Request::post('product_id', 0));
        $categoryId = max(0, (int) Request::post('category_id', 0));
        $authorId = max(0, (int) Request::post('author_id', 0));
        $authorId = $authorId > 0 ? $authorId : null;
        $tagsInput = Request::post('tags', '');
        $tags = is_array($tagsInput)
            ? array_map('strval', $tagsInput)
            : explode(',', (string) $tagsInput);
        $excerpt = trim((string) Request::post('excerpt', ''));
        $content = (string) Request::post('content', '');
        $slug = trim((string) Request::post('slug', ''));
        $coverMediaId = max(0, (int) Request::post('cover_media_id', 0));
        $coverMediaId = $coverMediaId > 0 ? $coverMediaId : null;
        $metaTitle = trim((string) Request::post('meta_title', ''));
        $metaDescription = trim((string) Request::post('meta_description', ''));
        $focusKeyword = trim((string) Request::post('focus_keyword', ''));
        $canonicalUrl = trim((string) Request::post('canonical_url', ''));
        $robotsIndex = Request::post('robots_index', null) !== null;
        $robotsFollow = Request::post('robots_follow', null) !== null;
        $status = (string) Request::post('status', 'draft');

        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            $status = 'draft';
        }

        $actorId = Auth::user()?->id;
        $actorId = $actorId === null ? null : (int) $actorId;
        $authorId ??= $actorId;

        try {
            if ($id > 0) {
                $service->update(
                    $id,
                    $title,
                    $productId > 0 ? $productId : null,
                    $categoryId > 0 ? $categoryId : null,
                    $excerpt,
                    $content,
                    $status,
                    $authorId,
                    $actorId,
                    $slug,
                    $coverMediaId,
                    $metaTitle,
                    $metaDescription,
                    $focusKeyword,
                    $canonicalUrl,
                    $robotsIndex,
                    $robotsFollow,
                    $tags
                );
                Logger::info('Artigo do Support atualizado.', [
                    'record_id' => $id,
                    'actor_id' => $actorId,
                ]);
                Flash::set('success', 'Artigo atualizado com sucesso.');
            } else {
                $article = $service->create(
                    $title,
                    $productId > 0 ? $productId : null,
                    $categoryId > 0 ? $categoryId : null,
                    $excerpt,
                    $content,
                    $authorId,
                    $slug,
                    $coverMediaId,
                    $metaTitle,
                    $metaDescription,
                    $focusKeyword,
                    $canonicalUrl,
                    $robotsIndex,
                    $robotsFollow,
                    $tags,
                    $status
                );
                Logger::info('Artigo do Support criado.', [
                    'record_id' => (int) $article->id,
                    'actor_id' => $actorId,
                ]);
                Flash::set('success', 'Artigo criado com sucesso.');
            }
        } catch (Throwable $exception) {
            Logger::exception($exception);
            Flash::set('error', $exception->getMessage());
            $current = $id > 0 ? $service->find($id) : null;
            Response::to(
                $current !== null
                    ? '/support/articles/' . rawurlencode((string) $current->slug) . '/edit'
                    : '/support/articles/create'
            );
        }

        Response::to('/support/articles');
    }

    private function validateCsrf(string $redirect): void
    {
        $token = Request::post('_token');

        if (!is_string($token) || !Csrf::validate($token)) {
            Flash::set('error', 'Token de segurança inválido.');
            Response::to($redirect);
        }
    }
}
