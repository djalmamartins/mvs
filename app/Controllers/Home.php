<?php

declare(strict_types=1);

namespace Moves\Controllers;

use Moves\Core\Config;
use Moves\Core\Controller;
use Moves\Boot\Connection;
use Moves\Core\Csrf;
use Moves\Core\Auth;
use Moves\Core\Flash;
use Moves\Core\Request;
use Moves\Core\Response;
use Moves\Core\Validator;
use PDO;

/**
 * Moves | Home Controller
 *
 * Gerencia a página inicial da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Controllers
 */
final class Home extends Controller
{
    public function index(): void
    {
        echo $this->view->render('pages/home', [
            'title' => 'Estratégia, Design e Tecnologia — MOVES',
            'description' => 'Moves: sites, sistemas web, identidade visual e automação para colocar ideias em movimento.',
            ...$this->pageMetadata('/'),
        ]);
    }

    public function services(): void
    {
        echo $this->view->render('pages/servicos', [
            'title' => 'Serviços — MOVES',
            'description' => 'Sites, sistemas web, aplicativos e hospedagem: conheça as quatro soluções da Moves.',
            ...$this->pageMetadata('/servicos'),
        ]);
    }

    public function projects(): void
    {
        echo $this->view->render('pages/projetos', [
            'title' => 'Projetos — MOVES',
            'description' => 'Apresentações de sites e projetos digitais com filtros por categoria.',
            ...$this->pageMetadata('/projetos'),
        ]);
    }

    public function about(): void
    {
        echo $this->view->render('pages/sobre', [
            'title' => 'Sobre a Moves — Estratégia, Design e Tecnologia',
            'description' => 'Conheça a Moves, uma equipe que conecta estratégia, design e tecnologia para transformar ideias em soluções digitais.',
            ...$this->pageMetadata('/sobre'),
        ]);
    }

    public function content(): void
    {
        $statement = Connection::getInstance()->query("SELECT title,slug,excerpt,image_path,published_at FROM studio_content WHERE type='article' AND status='published' ORDER BY published_at DESC,id DESC LIMIT 50");
        echo $this->view->render('pages/conteudo', [
            'title' => 'Conteúdo — MOVES',
            'description' => 'Guias sobre planejamento de sites, automação e produtos digitais.',
            ...$this->pageMetadata('/conteudo'),
            'articles' => $statement->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    /** @param array<string,string> $data */
    public function article(array $data): void
    {
        $statement = Connection::getInstance()->prepare("SELECT * FROM studio_content WHERE type='article' AND status='published' AND slug=? LIMIT 1");
        $statement->execute([$data['slug'] ?? '']);
        $article = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$article) { http_response_code(404); echo $this->view->render('pages/error', ['title'=>'Conteúdo não encontrado','code'=>404,'message'=>'Este conteúdo não está disponível.']); return; }
        echo $this->view->render('pages/article', ['title'=>$article['title'].' — MOVES','description'=>$article['excerpt'] ?: $article['title'],'article'=>$article,...$this->pageMetadata('/conteudo/'.$article['slug'])]);
    }

    public function contact(): void
    {
        echo $this->view->render('pages/contato', [
            'title' => 'Contato e solicitação de orçamento — MOVES',
            'description' => 'Conte seu projeto para a Moves e prepare sua solicitação de proposta em design e tecnologia.',
            ...$this->pageMetadata('/contato'),
        ]);
    }

    public function contactSubmit(): void
    {
        if (!Csrf::validate(is_string(Request::post('_token')) ? Request::post('_token') : null)) { Flash::set('error','Sessão expirada. Atualize a página.'); Response::to('/contato'); }
        $name=trim(strip_tags((string)Request::post('nome',''))); $email=trim((string)Request::post('email','')); $service=trim(strip_tags((string)Request::post('servico',''))); $message=trim(strip_tags((string)Request::post('mensagem','')));
        $validator=(new Validator())->required('nome',$name)->email('email',$email)->required('servico',$service)->min('mensagem',$message,20)->max('mensagem',$message,5000);
        if ($validator->fails()) { Flash::set('error','Revise os campos obrigatórios da proposta.'); Response::to('/contato'); }
        $pdo=Connection::getInstance(); $stmt=$pdo->prepare('INSERT INTO proposals(name,email,company,service,message) VALUES(?,?,?,?,?)'); $stmt->execute([$name,$email,mb_substr(trim(strip_tags((string)Request::post('empresa',''))),0,160) ?: null,$service,$message]);
        $id=(int)$pdo->lastInsertId(); $notify=$pdo->prepare('INSERT INTO notifications(title,message,link) VALUES(?,?,?)'); $notify->execute(['Nova proposta recebida','Proposta de '.$name.' para '.$service.'.','/admin/proposals']);
        Flash::set('success','Proposta recebida. Entraremos em contato em breve.'); Response::to('/contato');
    }

    /**
     * Exibe o painel inicial do usuário autenticado.
     */
    public function app(): void
    {
        $user = Auth::user();
        echo $this->view->render('pages/home', [
            'title' => 'Início',
            'description' => 'Acompanhe sua relação com a Moves.',
            'user' => $user,
            'summary' => ['services' => 0, 'projects' => 0, 'tickets' => 0, 'invoices' => 0],
        ]);
    }

    /**
     * Retorna somente o estado real disponível para o cliente autenticado.
     */
    public function appStatus(): never
    {
        $user = Auth::user();

        Response::json([
            'user' => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
            ],
            'summary' => [
                'services' => 0,
                'projects' => 0,
                'tickets' => 0,
                'invoices' => 0,
            ],
            'updatedAt' => date(DATE_ATOM),
        ]);
    }

    /**
     * @return array{canonical: ?string, robots: string}
     */
    private function pageMetadata(string $path): array
    {
        $applicationUrl = rtrim((string) Config::get('APP_URL', ''), '/');
        $canonical = null;

        if (Config::isProduction() && $applicationUrl !== '') {
            $canonical = $applicationUrl . ($path === '/' ? '/' : $path);
        }

        return [
            'canonical' => $canonical,
            'robots' => Config::isProduction() ? 'index, follow' : 'noindex, nofollow',
        ];
    }
}
