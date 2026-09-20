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
use Moves\Core\Seo;
use Moves\Core\HtmlSanitizer;
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
        $pdo=Connection::getInstance();
        $highlights=$pdo->query("SELECT c.*,m.alt_text FROM studio_content c LEFT JOIN studio_media m ON m.id=c.media_id WHERE c.type='highlight' AND c.status='published' AND c.deleted_at IS NULL AND (c.starts_at IS NULL OR c.starts_at<=NOW()) AND (c.ends_at IS NULL OR c.ends_at>=NOW()) ORDER BY c.position,c.id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
        $testimonials=$pdo->query("SELECT c.*,m.alt_text FROM studio_content c LEFT JOIN studio_media m ON m.id=c.media_id WHERE c.type='testimonial' AND c.status='published' AND c.deleted_at IS NULL ORDER BY c.position,c.id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
        foreach($highlights as &$highlight){$highlight['meta']=json_decode((string)($highlight['meta_json']??''),true)?:[];}unset($highlight);
        foreach($testimonials as &$testimonial){$testimonial['meta']=json_decode((string)($testimonial['meta_json']??''),true)?:[];}unset($testimonial);
        echo $this->view->render('pages/home', [
            'title' => 'Estratégia, Design e Tecnologia — MOVES',
            'description' => 'Moves: sites, sistemas web, identidade visual e automação para colocar ideias em movimento.',
            ...$this->pageMetadata('/'),
            'highlights'=>$highlights,
            'testimonials'=>$testimonials,
            'projects'=>$this->publishedProjects(6),
        ]);
    }

    /** @param array<string,string> $data */
    public function dynamicPage(array $data): void
    {
        $statement=Connection::getInstance()->prepare("SELECT c.*,m.alt_text FROM studio_content c LEFT JOIN studio_media m ON m.id=c.media_id WHERE c.type='page' AND c.status='published' AND c.deleted_at IS NULL AND c.slug=? LIMIT 1");$statement->execute([$data['slug']??'']);$page=$statement->fetch(PDO::FETCH_ASSOC);
        if(!$page){http_response_code(404);echo $this->view->render('pages/error',['title'=>'Página não encontrada','code'=>404,'message'=>'Esta página não está disponível.']);return;}
        $title = ($page['seo_title'] ?: $page['title']).' — MOVES';
        $description = $page['seo_description'] ?: ($page['excerpt'] ?: $page['title']);
        $page['rendered_content'] = HtmlSanitizer::clean((string)($page['content']??''));
        $metadata=Seo::metadata('/pagina/'.$page['slug'],$title,$description,'website',$page['media_id'] ? (int)$page['media_id'] : null);if(!empty($page['canonical_url'])){$metadata['canonical']=$page['canonical_url'];}$metadata['robots']=((int)$page['robots_index']===1?'index':'noindex').', '.((int)$page['robots_follow']===1?'follow':'nofollow');
        echo $this->view->render('pages/dynamic-page',['title'=>$title,'description'=>$description,'page'=>$page,...$metadata]);
    }

    public function faq(): void
    {
        $statement=Connection::getInstance()->query("SELECT c.title,c.content,t.name category_name FROM studio_content c LEFT JOIN studio_taxonomies t ON t.id=c.category_id WHERE c.type='faq' AND c.status='published' AND c.deleted_at IS NULL ORDER BY t.name,c.position,c.id");
        $items = array_map(static function (array $item): array { $item['rendered_content'] = HtmlSanitizer::clean((string) ($item['content'] ?? '')); return $item; }, $statement->fetchAll(PDO::FETCH_ASSOC));
        echo $this->view->render('pages/faq',['title'=>'Perguntas frequentes — MOVES','description'=>'Respostas para perguntas frequentes sobre os serviços da Moves.','items'=>$items,...$this->pageMetadata('/faq')]);
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
            'projects' => $this->publishedProjects(),
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
        $statement = Connection::getInstance()->query("SELECT c.title,c.slug,c.excerpt,c.media_id,c.published_at,t.name category_name,m.alt_text FROM studio_content c LEFT JOIN studio_taxonomies t ON t.id=c.category_id LEFT JOIN studio_media m ON m.id=c.media_id WHERE c.type='article' AND c.status='published' AND c.deleted_at IS NULL AND (c.published_at IS NULL OR c.published_at<=NOW()) ORDER BY c.published_at DESC,c.id DESC LIMIT 50");
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
        $statement = Connection::getInstance()->prepare("SELECT * FROM studio_content WHERE type='article' AND status='published' AND deleted_at IS NULL AND slug=? LIMIT 1");
        $statement->execute([$data['slug'] ?? '']);
        $article = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$article) { http_response_code(404); echo $this->view->render('pages/error', ['title'=>'Conteúdo não encontrado','code'=>404,'message'=>'Este conteúdo não está disponível.']); return; }
        $title = ($article['seo_title'] ?: $article['title']).' — MOVES';
        $description = $article['seo_description'] ?: ($article['excerpt'] ?: $article['title']);
        $article['rendered_content'] = HtmlSanitizer::clean((string)($article['content']??''));
        $metadata=Seo::metadata('/conteudo/'.$article['slug'],$title,$description,'article',$article['media_id'] ? (int)$article['media_id'] : null,$article['published_at']);if(!empty($article['canonical_url'])){$metadata['canonical']=$article['canonical_url'];}$metadata['robots']=((int)$article['robots_index']===1?'index':'noindex').', '.((int)$article['robots_follow']===1?'follow':'nofollow');
        echo $this->view->render('pages/article', ['title'=>$title,'description'=>$description,'article'=>$article,...$metadata]);
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
        $id=(int)$pdo->lastInsertId(); $notify=$pdo->prepare('INSERT INTO notifications(title,message,source_type,source_id,action_url,link) VALUES(?,?,?,?,?,?)'); $notify->execute(['Nova proposta recebida','Proposta de '.$name.' para '.$service.'.','proposal',$id,'/studio/proposals?view='.$id,'/studio/proposals?view='.$id]);
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

    /** @return list<array<string, mixed>> */
    private function publishedProjects(int $limit = 100): array
    {
        $limit = max(1, min(100, $limit));
        $statement = Connection::getInstance()->query(
            "SELECT c.*,t.name category_name,m.alt_text FROM studio_content c LEFT JOIN studio_taxonomies t ON t.id=c.category_id LEFT JOIN studio_media m ON m.id=c.media_id WHERE c.type='project' AND c.status='published' AND c.deleted_at IS NULL AND (c.published_at IS NULL OR c.published_at<=NOW()) ORDER BY c.position,c.id DESC LIMIT {$limit}"
        );
        $projects = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($projects as &$project) {
            $project['meta'] = json_decode((string) ($project['meta_json'] ?? ''), true) ?: [];
        }
        unset($project);
        return $projects;
    }
}
