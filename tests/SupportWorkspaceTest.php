<?php

declare(strict_types=1);

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Services\Support\WorkspaceService;
use MovesCode\Model\Connection as ModelConnection;
use PHPUnit\Framework\TestCase;

final class SupportWorkspaceTest extends TestCase
{
    private PDO $pdo;
    private string $prefix;
    private int $activeUserId;
    private int $inactiveUserId;
    private int $articleId;
    private int $trashedArticleId;

    protected function setUp(): void
    {
        Environment::load(dirname(__DIR__));
        $this->pdo = Connection::getInstance();
        ModelConnection::configure($this->pdo);
        $this->prefix = 'workspace-' . bin2hex(random_bytes(5));
        $insertUser = $this->pdo->prepare('INSERT INTO users (name,email,password,role,status) VALUES (?,?,?,?,?)');
        $insertUser->execute([$this->prefix . ' Active', $this->prefix . '-active@example.test', 'x', 'admin', 'active']);
        $this->activeUserId = (int) $this->pdo->lastInsertId();
        $insertUser->execute([$this->prefix . ' Inactive', $this->prefix . '-inactive@example.test', 'x', 'user', 'inactive']);
        $this->inactiveUserId = (int) $this->pdo->lastInsertId();
        $insertArticle = $this->pdo->prepare('INSERT INTO support_articles (title,slug,status,author_id,deleted_at) VALUES (?,?,?,?,?)');
        $insertArticle->execute([$this->prefix . ' Active article', $this->prefix . '-active', 'published', $this->activeUserId, null]);
        $this->articleId = (int) $this->pdo->lastInsertId();
        $insertArticle->execute([$this->prefix . ' Trashed article', $this->prefix . '-trashed', 'published', $this->activeUserId, date('Y-m-d H:i:s')]);
        $this->trashedArticleId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM support_articles WHERE id IN (?,?)')->execute([$this->articleId, $this->trashedArticleId]);
        $this->pdo->prepare('DELETE FROM users WHERE id IN (?,?)')->execute([$this->activeUserId, $this->inactiveUserId]);
    }

    public function testDashboardAndReportsExcludeTrashedArticles(): void
    {
        $service = new WorkspaceService();
        $dashboard = $service->dashboard();
        self::assertTrue(in_array($this->prefix . ' Active article', array_column($dashboard['recent'], 'title'), true));
        self::assertFalse(in_array($this->prefix . ' Trashed article', array_column($dashboard['recent'], 'title'), true));
        $reports = $service->reports();
        self::assertSame($dashboard['counts']['articles'], $reports['summary']['articles']);
        self::assertGreaterThanOrEqual(1, $reports['summary']['published']);
    }

    public function testUsersApplySearchRoleAndStatusFilters(): void
    {
        $service = new WorkspaceService();
        self::assertCount(2, $service->users($this->prefix));
        $filtered = $service->users($this->prefix, 'admin', 'active');
        self::assertCount(1, $filtered);
        self::assertSame($this->activeUserId, (int) $filtered[0]['id']);
        self::assertSame([], $service->users($this->prefix, 'user', 'active'));
    }

    public function testStructuralSupportRoutesAndViewsAreRegistered(): void
    {
        $routes = file_get_contents(dirname(__DIR__) . '/app/Boot/Routes.php');
        self::assertIsString($routes);
        foreach (['inbox', 'my-tickets', 'tickets', 'sla', 'users', 'reports', 'settings'] as $route) {
            self::assertStringContainsString("'/support/{$route}'", $routes);
        }
        foreach (['support-dashboard', 'support-structural', 'support-users', 'support-reports', 'support-settings'] as $view) {
            self::assertFileIsReadable(dirname(__DIR__) . '/resources/themes/admin/pages/' . $view . '.php');
        }
    }
}
