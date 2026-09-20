<?php

declare(strict_types=1);

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Services\Support\ArticleService;
use Moves\Services\Support\CategoryService;
use Moves\Services\Support\ProductService;
use Moves\Core\HtmlSanitizer;
use MovesCode\Model\Connection as ModelConnection;
use PHPUnit\Framework\TestCase;

final class SupportKnowledgeBaseTest extends TestCase
{
    private PDO $pdo;
    private int $userId;
    private string $prefix;
    private ?int $productId = null;

    protected function setUp(): void
    {
        Environment::load(dirname(__DIR__));
        $this->pdo = Connection::getInstance();
        ModelConnection::configure($this->pdo);
        $this->prefix = 'kb2b-' . bin2hex(random_bytes(5));
        $statement = $this->pdo->prepare(
            'INSERT INTO users (name, email, password, role, status)
             VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([
            'Support Test',
            $this->prefix . '@example.test',
            password_hash('test-only', PASSWORD_DEFAULT),
            'admin',
            'active',
        ]);
        $this->userId = (int) $this->pdo->lastInsertId();
        self::assertNotSame(1, $this->userId);
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM support_articles WHERE title LIKE ?')
            ->execute([$this->prefix . '%']);
        $this->pdo->prepare('DELETE FROM support_tags WHERE name LIKE ?')
            ->execute([$this->prefix . '%']);
        if ($this->productId !== null) {
            $this->pdo->prepare('DELETE FROM support_products WHERE id = ?')
                ->execute([$this->productId]);
        }
        $this->pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$this->userId]);
    }

    public function testKnowledgeBasePaginationFiltersRevisionsAndTrashLifecycle(): void
    {
        $product = (new ProductService())->create($this->prefix . ' Produto', null, $this->userId);
        $this->productId = (int) $product->id;
        $category = (new CategoryService())->create(
            $this->prefix . ' Categoria',
            $this->productId
        );
        $service = new ArticleService();
        $created = [];

        for ($index = 1; $index <= 12; $index++) {
            $created[] = $service->create(
                $this->prefix . ' Artigo ' . $index,
                $this->productId,
                (int) $category->id,
                'Resumo pesquisável ' . $index,
                '<p>Conteúdo controlado.</p>',
                $this->userId,
                $this->prefix . '-slug-' . $index,
                null,
                null,
                null,
                null,
                null,
                true,
                true,
                [$this->prefix . ' Tag'],
                $index === 12 ? 'published' : 'draft'
            );
        }

        $firstPage = $service->paginate('', $this->productId, (int) $category->id, null, 1, 10);
        $secondPage = $service->paginate('', $this->productId, (int) $category->id, null, 2, 10);
        self::assertSame(12, $firstPage['total']);
        self::assertCount(10, $firstPage['items']);
        self::assertCount(2, $secondPage['items']);
        self::assertSame(11, $secondPage['from']);
        self::assertSame(12, $secondPage['to']);

        self::assertSame(1, $service->paginate('pesquisável 7')['total']);
        self::assertSame(1, $service->paginate($this->prefix . '-slug-8')['total']);
        self::assertSame(11, $service->paginate('', $this->productId, (int) $category->id, 'draft')['total']);
        self::assertSame(1, $service->paginate('', $this->productId, (int) $category->id, 'published')['total']);

        $target = $created[0];
        $service->update(
            (int) $target->id,
            $this->prefix . ' Artigo atualizado',
            $this->productId,
            (int) $category->id,
            'Resumo atualizado',
            '<p>Conteúdo atualizado.</p>',
            'draft',
            $this->userId,
            $this->userId,
            (string) $target->slug
        );
        self::assertCount(1, $service->revisions((int) $target->id));
        self::assertSame(1, $service->paginateRevisions($this->prefix)['total']);

        $service->trash((int) $target->id, $this->userId);
        self::assertNull($service->find((int) $target->id));
        self::assertSame(1, $service->paginate('', null, null, null, 1, 10, true)['total']);

        $service->restore((int) $target->id);
        self::assertNotNull($service->find((int) $target->id));
        self::assertSame(0, $service->paginate('', null, null, null, 1, 10, true)['total']);

        $discard = $created[1];
        $service->update(
            (int) $discard->id,
            $this->prefix . ' Descartável atualizado',
            $this->productId,
            (int) $category->id,
            'Resumo',
            '<p>Nova revisão.</p>',
            'draft',
            $this->userId,
            $this->userId,
            (string) $discard->slug,
            null,
            null,
            null,
            null,
            null,
            true,
            true,
            [$this->prefix . ' Tag']
        );
        $service->trash((int) $discard->id, $this->userId);
        $service->permanentDelete((int) $discard->id);

        $exists = $this->pdo->prepare('SELECT COUNT(*) FROM support_articles WHERE id = ?');
        $exists->execute([(int) $discard->id]);
        self::assertSame(0, (int) $exists->fetchColumn());
        $pivot = $this->pdo->prepare('SELECT COUNT(*) FROM support_article_tags WHERE article_id = ?');
        $pivot->execute([(int) $discard->id]);
        self::assertSame(0, (int) $pivot->fetchColumn());
        $revisions = $this->pdo->prepare('SELECT COUNT(*) FROM support_article_revisions WHERE article_id = ?');
        $revisions->execute([(int) $discard->id]);
        self::assertSame(0, (int) $revisions->fetchColumn());
    }

    public function testArticleSecuritySlugConflictsAndRelationshipGuards(): void
    {
        $productService = new ProductService();
        $categoryService = new CategoryService();
        $articleService = new ArticleService();
        $product = $productService->create($this->prefix . ' Produto protegido', null, $this->userId);
        $this->productId = (int) $product->id;
        $category = $categoryService->create($this->prefix . ' Categoria protegida', $this->productId);

        $article = $articleService->create(
            $this->prefix . ' Segurança',
            $this->productId,
            (int) $category->id,
            'Resumo',
            '<script>alert(1)</script><p onclick="alert(2)">Seguro</p><a href="javascript:alert(3)">link</a><figure data-align="center" style="width: 65%;"><img src="/media/1" onerror="alert(4)" alt="Imagem"></figure>',
            $this->userId,
            $this->prefix . '-seguranca'
        );

        $stored = (string) $articleService->find((int) $article->id)?->content;
        self::assertStringNotContainsString('<script', $stored);
        self::assertStringNotContainsString('onclick', $stored);
        self::assertStringNotContainsString('javascript:', $stored);
        self::assertStringNotContainsString('onerror', $stored);
        self::assertStringContainsString('data-align="center"', $stored);
        self::assertStringContainsString('style="width: 65%;"', $stored);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Este slug já está em uso');
        $articleService->create(
            $this->prefix . ' Slug duplicado',
            $this->productId,
            (int) $category->id,
            null,
            '<p>Conteúdo.</p>',
            $this->userId,
            $this->prefix . '-seguranca'
        );
    }

    public function testRelatedProductAndCategoryCannotBeDeleted(): void
    {
        $productService = new ProductService();
        $categoryService = new CategoryService();
        $product = $productService->create($this->prefix . ' Produto relacionado', null, $this->userId);
        $this->productId = (int) $product->id;
        $category = $categoryService->create($this->prefix . ' Categoria relacionada', $this->productId);

        try {
            $productService->delete($this->productId);
            self::fail('Produto relacionado deveria ser protegido.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('vinculado', $exception->getMessage());
        }

        (new ArticleService())->create(
            $this->prefix . ' Artigo relacionado',
            $this->productId,
            (int) $category->id,
            null,
            '<p>Conteúdo.</p>',
            $this->userId
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A categoria está vinculada');
        $categoryService->delete((int) $category->id);
    }

    public function testSanitizerPreservesOnlySupportedEditorFigurePresentation(): void
    {
        $html = HtmlSanitizer::clean('<figure data-align="right" style="width: 420px; color:red"><img src="/media/9"></figure><figure data-align="left" style="width: 420px;"><img src="/media/9"></figure>');
        self::assertStringNotContainsString('color:red', $html);
        self::assertStringContainsString('data-align="left"', $html);
        self::assertStringContainsString('style="width: 420px;"', $html);
    }
}
