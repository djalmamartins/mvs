<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Auth;
use Moves\Core\Controller;
use Moves\Core\Csrf;
use Moves\Core\Flash;
use Moves\Core\HtmlSanitizer;
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
        $this->renderArticleList();
    }

    public function drafts(): void
    {
        $this->renderArticleList('draft');
    }

    public function revisions(): void
    {
        $service = new ArticleService();
        $search = $this->search();
        $pagination = $service->paginateRevisions(
            $search,
            max(1, (int) Request::get('page', 1))
        );

        echo $this->view->render('pages/support-revisions', [
            'title' => 'Revisões',
            'productName' => 'Suporte',
            'activeProduct' => 'support',
            'currentPage' => 'revisions',
            'revisions' => $pagination['items'],
            'pagination' => $pagination,
            'search' => $search,
        ]);
    }

    public function trash(): void
    {
        $service = new ArticleService();
        $search = $this->search();
        $pagination = $service->paginate(
            $search,
            null,
            null,
            null,
            max(1, (int) Request::get('page', 1)),
            10,
            true
        );
        $users = [];
        foreach ($pagination['items'] as $article) {
            foreach (['author_id', 'deleted_by'] as $field) {
                $id = (int) ($article->{$field} ?? 0);
                if ($id > 0 && !isset($users[$id])) {
                    $foundUser = (new User())->findById($id);
                    $users[$id] = $foundUser !== null ? (string) $foundUser->name : '—';
                }
            }
        }

        echo $this->view->render('pages/support-trash', [
            'title' => 'Lixeira',
            'productName' => 'Suporte',
            'activeProduct' => 'support',
            'currentPage' => 'trash',
            'articles' => $pagination['items'],
            'pagination' => $pagination,
            'search' => $search,
            'users' => $users,
        ]);
    }

    public function articleTrash(): never
    {
        $this->validateCsrf('/support/articles');
        try {
            (new ArticleService())->trash(
                max(0, (int) Request::post('id', 0)),
                Auth::user()?->id !== null ? (int) Auth::user()->id : null
            );
            Flash::set('success', 'Artigo movido para a lixeira.');
        } catch (Throwable $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::to('/support/articles');
    }

    public function articleRestore(): never
    {
        $this->validateCsrf('/support/trash');
        try {
            (new ArticleService())->restore(max(0, (int) Request::post('id', 0)));
            Flash::set('success', 'Artigo restaurado com sucesso.');
        } catch (Throwable $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::to('/support/trash');
    }

    public function articleDelete(): never
    {
        $this->validateCsrf('/support/trash');
        try {
            (new ArticleService())->permanentDelete(max(0, (int) Request::post('id', 0)));
            Flash::set('success', 'Artigo excluído permanentemente.');
        } catch (Throwable $exception) {
            Flash::set('error', $exception->getMessage());
        }
        Response::to('/support/trash');
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

    private function renderArticleList(?string $lockedStatus = null): void
    {
        $articleService = new ArticleService();
        $products = (new ProductService())->all();
        $categories = (new CategoryService())->all();
        $search = $this->search();
        $status = $lockedStatus ?? (string) Request::get('status', '');
        if (!in_array($status, ['', 'draft', 'published', 'archived'], true)) {
            $status = '';
        }
        $productId = max(0, (int) Request::get('product', 0));
        $categoryId = max(0, (int) Request::get('category', 0));
        $pagination = $articleService->paginate(
            $search,
            $productId > 0 ? $productId : null,
            $categoryId > 0 ? $categoryId : null,
            $status !== '' ? $status : null,
            max(1, (int) Request::get('page', 1))
        );
        $productNames = [];
        $categoryNames = [];
        foreach ($products as $product) {
            $productNames[(int) $product->id] = (string) $product->name;
        }
        foreach ($categories as $category) {
            $categoryNames[(int) $category->id] = (string) $category->name;
        }

        echo $this->view->render('pages/support-articles', [
            'title' => $lockedStatus === 'draft' ? 'Rascunhos' : 'Artigos',
            'productName' => 'Suporte',
            'activeProduct' => 'support',
            'currentPage' => $lockedStatus === 'draft' ? 'drafts' : 'articles',
            'listMode' => $lockedStatus === 'draft' ? 'drafts' : 'articles',
            'articles' => $pagination['items'],
            'pagination' => $pagination,
            'products' => $products,
            'categories' => $categories,
            'search' => $search,
            'status' => $status,
            'lockedStatus' => $lockedStatus,
            'productId' => $productId,
            'categoryId' => $categoryId,
            'articleDetails' => $this->articleDetails(
                $pagination['items'],
                $productNames,
                $categoryNames,
                $articleService
            ),
        ]);
    }

    /**
     * @param array<int,object> $articles
     * @param array<int,string> $productNames
     * @param array<int,string> $categoryNames
     * @return array<int,array<string,mixed>>
     */
    private function articleDetails(
        array $articles,
        array $productNames,
        array $categoryNames,
        ArticleService $articleService
    ): array {
        $details = [];
        $tagService = new TagService();
        foreach ($articles as $article) {
            $articleId = (int) $article->id;
            $author = $article->author_id !== null
                ? (new User())->findById((int) $article->author_id)
                : null;
            $revisions = [];
            foreach ($articleService->revisions($articleId) as $revision) {
                $revisionAuthor = $revision->created_by !== null
                    ? (new User())->findById((int) $revision->created_by)
                    : null;
                $revisions[] = [
                    'id' => (int) $revision->id,
                    'title' => (string) $revision->title,
                    'author' => $revisionAuthor !== null ? (string) $revisionAuthor->name : 'Sistema',
                    'created_at' => (string) $revision->created_at,
                    'excerpt' => (string) ($revision->excerpt ?? ''),
                    'content' => HtmlSanitizer::clean((string) ($revision->content ?? '')),
                ];
            }
            $details[$articleId] = [
                'id' => $articleId,
                'title' => (string) $article->title,
                'slug' => (string) $article->slug,
                'excerpt' => (string) ($article->excerpt ?? ''),
                'content' => HtmlSanitizer::clean((string) ($article->content ?? '')),
                'status' => (string) $article->status,
                'product' => $productNames[(int) $article->product_id] ?? '—',
                'category' => $categoryNames[(int) $article->category_id] ?? '—',
                'tags' => array_map(
                    static fn ($tag): string => (string) $tag->name,
                    $tagService->forArticle($articleId)
                ),
                'author' => $author !== null ? (string) $author->name : '—',
                'created_at' => (string) ($article->created_at ?? '—'),
                'updated_at' => (string) ($article->updated_at ?? '—'),
                'reading_time' => (int) ($article->reading_time ?? 0),
                'meta_title' => (string) ($article->meta_title ?? ''),
                'meta_description' => (string) ($article->meta_description ?? ''),
                'focus_keyword' => (string) ($article->focus_keyword ?? ''),
                'canonical_url' => (string) ($article->canonical_url ?? ''),
                'robots_index' => (bool) ($article->robots_index ?? false),
                'robots_follow' => (bool) ($article->robots_follow ?? false),
                'revisions' => $revisions,
            ];
        }

        return $details;
    }

    private function search(): string
    {
        return mb_substr(
            trim(strip_tags((string) Request::get('q', ''))),
            0,
            120
        );
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
