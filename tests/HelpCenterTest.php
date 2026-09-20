<?php

declare(strict_types=1);

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Services\HelpService;
use Moves\Services\Support\ArticleService;
use Moves\Services\Support\CategoryService;
use Moves\Services\Support\ProductService;
use MovesCode\Model\Connection as ModelConnection;
use PHPUnit\Framework\TestCase;

final class HelpCenterTest extends TestCase
{
    private PDO $pdo;
    private int $userId;
    private int $productId;
    private int $categoryId;
    private string $prefix;

    protected function setUp(): void
    {
        Environment::load(dirname(__DIR__));
        $this->pdo = Connection::getInstance();
        ModelConnection::configure($this->pdo);
        $this->prefix = 'help-' . bin2hex(random_bytes(5));
        $this->pdo->prepare('INSERT INTO users (name,email,password,role,status) VALUES (?,?,?,?,?)')->execute([
            'Help Test', $this->prefix . '@example.test', password_hash('test-only', PASSWORD_DEFAULT), 'admin', 'active',
        ]);
        $this->userId = (int) $this->pdo->lastInsertId();
        $product = (new ProductService())->create($this->prefix . ' Produto', 'Produto público de teste', $this->userId);
        $this->productId = (int) $product->id;
        $category = (new CategoryService())->create($this->prefix . ' Categoria', $this->productId);
        $this->categoryId = (int) $category->id;
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM support_articles WHERE slug LIKE ?')->execute([$this->prefix . '%']);
        $this->pdo->prepare('DELETE FROM support_tags WHERE slug LIKE ?')->execute([$this->prefix . '%']);
        $this->pdo->prepare('DELETE FROM support_products WHERE id=?')->execute([$this->productId]);
        $this->pdo->prepare('DELETE FROM users WHERE id=?')->execute([$this->userId]);
    }

    public function testPublicQueriesExposeOnlyPublishedArticlesAndKeepSeo(): void
    {
        $articles = new ArticleService();
        $published = $articles->create(
            $this->prefix . ' Guia público', $this->productId, $this->categoryId, 'Resumo encontrável exclusivo',
            '<h2>Primeira etapa</h2><p>Conteúdo seguro.</p><script>alert(1)</script>', $this->userId,
            $this->prefix . '-publicado', null, 'Título SEO público', 'Descrição SEO pública', $this->prefix,
            'https://example.test/guia', false, true, [$this->prefix . ' Tag'], 'published'
        );
        $draft = $articles->create(
            $this->prefix . ' Rascunho', $this->productId, $this->categoryId, 'Segredo rascunho',
            '<p>Oculto</p>', $this->userId, $this->prefix . '-rascunho'
        );
        $trashed = $articles->create(
            $this->prefix . ' Excluído', $this->productId, $this->categoryId, 'Segredo lixeira',
            '<p>Oculto</p>', $this->userId, $this->prefix . '-lixeira', null, null, null, null, null, true, true, [], 'published'
        );
        $articles->trash((int) $trashed->id, $this->userId);

        $help = new HelpService();
        $page = $help->article((string) $published->slug);
        self::assertNotNull($page);
        self::assertSame('Título SEO público', $page['article']['meta_title']);
        self::assertSame('Descrição SEO pública', $page['article']['meta_description']);
        self::assertSame('https://example.test/guia', $page['article']['canonical_url']);
        self::assertSame(0, (int) $page['article']['robots_index']);
        self::assertStringNotContainsString('<script', $page['article']['rendered_content']);
        self::assertStringContainsString('id="primeira-etapa"', $page['article']['rendered_content']);
        self::assertSame('Primeira etapa', $page['toc'][0]['label']);
        self::assertSame(0, $page['feedback']['total']);
        self::assertNotEmpty($page['sectionArticles']);
        self::assertNull($help->article((string) $draft->slug));
        self::assertNull($help->article((string) $trashed->slug));
        self::assertNull($help->article($this->prefix . '-inexistente'));

        $search = $help->search('encontrável exclusivo');
        self::assertCount(1, $search['items']);
        self::assertSame((int) $published->id, (int) $search['items'][0]['id']);
        self::assertCount(0, $help->search('Segredo rascunho')['items']);
        self::assertCount(0, $help->search('Segredo lixeira')['items']);
        self::assertSame('alert(9)', $help->search('<script>alert(9)</script>')['query']);

        $product = $help->product((string) $this->productSlug());
        self::assertNotNull($product);
        self::assertCount(1, $product['articles']);
        $category = $help->category((string) $this->categorySlug());
        self::assertNotNull($category);
        self::assertCount(1, $category['articles']);
    }

    public function testFeedbackIsPersistedOncePerVisitorAndCanBeChanged(): void
    {
        $article = (new ArticleService())->create(
            $this->prefix . ' Feedback', $this->productId, $this->categoryId, 'Resumo',
            '<h2>Ajuda</h2><p>Conteúdo.</p>', $this->userId, $this->prefix . '-feedback',
            null, null, null, null, null, true, true, [], 'published'
        );
        $help = new HelpService();
        $visitor = hash('sha256', $this->prefix . '-visitor');
        $help->recordFeedback((int) $article->id, $visitor, true);
        $help->recordFeedback((int) $article->id, $visitor, false);

        $page = $help->article((string) $article->slug);
        self::assertNotNull($page);
        self::assertSame(['yes' => 0, 'no' => 1, 'total' => 1], $page['feedback']);
    }

    public function testPublicRoutesAreRegisteredWithoutAuthenticationMiddleware(): void
    {
        $routes = file_get_contents(dirname(__DIR__) . '/app/Boot/Routes.php');
        self::assertIsString($routes);
        foreach (['/help', '/help/search', '/help/products/{slug}', '/help/categories/{slug}', '/help/articles/{slug}'] as $route) {
            self::assertStringContainsString("get('{$route}'", $routes);
        }
        self::assertStringContainsString("post('/help/articles/{slug}/feedback'", $routes);
    }

    private function productSlug(): string
    {
        $statement = $this->pdo->prepare('SELECT slug FROM support_products WHERE id=?');
        $statement->execute([$this->productId]);
        return (string) $statement->fetchColumn();
    }

    private function categorySlug(): string
    {
        $statement = $this->pdo->prepare('SELECT slug FROM support_categories WHERE id=?');
        $statement->execute([$this->categoryId]);
        return (string) $statement->fetchColumn();
    }
}
