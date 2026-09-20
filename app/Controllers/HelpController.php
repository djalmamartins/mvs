<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Config;
use Moves\Core\Controller;
use Moves\Core\Request;
use Moves\Core\Csrf;
use Moves\Core\Response;
use Moves\Services\HelpService;

final class HelpController extends Controller
{
    private HelpService $help;

    public function __construct(\MovesCode\Router\Router $router)
    {
        parent::__construct($router);
        $this->help = new HelpService();
    }

    public function index(): void
    {
        echo $this->view->render('pages/home', ['title' => 'Central de Ajuda — Moves', 'description' => 'Documentação oficial dos produtos Moves.', ...$this->help->home(), ...$this->metadata('/help')]);
    }

    public function search(): void
    {
        $result = $this->help->search((string) Request::get('q', ''));
        $title = $result['query'] === '' ? 'Buscar — Central de Ajuda' : 'Busca por ' . $result['query'] . ' — Central de Ajuda';
        echo $this->view->render('pages/search', ['title' => $title, 'description' => 'Resultados da busca na documentação oficial do Moves.', ...$result, ...$this->metadata('/help/search')]);
    }

    /** @param array<string,string> $data */
    public function product(array $data): void
    {
        $result = $this->help->product((string) ($data['slug'] ?? ''));
        if ($result === null) { $this->notFound(); return; }
        echo $this->view->render('pages/product', ['title' => $result['product']['name'] . ' — Central de Ajuda', 'description' => $result['product']['description'] ?: 'Documentação do produto.', ...$result, ...$this->metadata('/help/products/' . $result['product']['slug'])]);
    }

    /** @param array<string,string> $data */
    public function category(array $data): void
    {
        $result = $this->help->category((string) ($data['slug'] ?? ''));
        if ($result === null) { $this->notFound(); return; }
        echo $this->view->render('pages/category', ['title' => $result['category']['name'] . ' — Central de Ajuda', 'description' => $result['category']['description'] ?: 'Artigos desta categoria.', ...$result, ...$this->metadata('/help/categories/' . $result['category']['slug'])]);
    }

    /** @param array<string,string> $data */
    public function article(array $data): void
    {
        $result = $this->help->article((string) ($data['slug'] ?? ''));
        if ($result === null) { $this->notFound(); return; }
        $article = $result['article'];
        $title = (string) ($article['meta_title'] ?: $article['title']);
        $description = (string) ($article['meta_description'] ?: $article['excerpt'] ?: $article['title']);
        $path = '/help/articles/' . $article['slug'];
        $metadata = $this->metadata($path, $article['canonical_url'] ?: null);
        $metadata['robots'] = ((int) $article['robots_index'] === 1 ? 'index' : 'noindex') . ', ' . ((int) $article['robots_follow'] === 1 ? 'follow' : 'nofollow');
        $metadata['ogType'] = 'article';
        $metadata['ogImage'] = $article['cover_media_id'] ? '/media/' . (int) $article['cover_media_id'] : null;
        echo $this->view->render('pages/article', ['title' => $title, 'description' => $description, ...$result, ...$metadata]);
    }

    /** @param array<string,string> $data */
    public function feedback(array $data): never
    {
        $slug = (string) ($data['slug'] ?? '');
        $result = $this->help->article($slug);
        $token = Request::post('_token');
        $vote = (string) Request::post('helpful', '');
        if ($result === null || !is_string($token) || !Csrf::validate($token) || !in_array($vote, ['yes', 'no'], true)) {
            Response::to('/help/articles/' . rawurlencode($slug));
        }
        $visitor = (string) ($_COOKIE['moves_help_visitor'] ?? '');
        if (preg_match('/^[a-f0-9]{32}$/', $visitor) !== 1) {
            $visitor = bin2hex(random_bytes(16));
            setcookie('moves_help_visitor', $visitor, ['expires' => time() + 31536000, 'path' => '/help', 'httponly' => true, 'samesite' => 'Lax']);
        }
        $this->help->recordFeedback((int) $result['article']['id'], hash('sha256', $visitor), $vote === 'yes');
        Response::to('/help/articles/' . rawurlencode($slug) . '?feedback=thanks#article-feedback');
    }

    private function notFound(): void
    {
        http_response_code(404);
        echo $this->view->render('pages/error', ['title' => 'Conteúdo não encontrado — Central de Ajuda', 'description' => 'O conteúdo solicitado não está disponível.', 'code' => 404, ...$this->metadata('/help')]);
    }

    /** @return array{canonical:string,robots:string,ogUrl:string,ogType:string,ogImage:null} */
    private function metadata(string $path, ?string $canonical = null): array
    {
        $base = rtrim((string) Config::get('APP_URL', ''), '/');
        $fallback = $base . $path;
        if ($canonical === null || filter_var($canonical, FILTER_VALIDATE_URL) === false || !preg_match('#^https?://#i', $canonical)) {
            $canonical = $fallback;
        }
        return ['canonical' => $canonical, 'robots' => 'index, follow', 'ogUrl' => $canonical, 'ogType' => 'website', 'ogImage' => null];
    }
}
