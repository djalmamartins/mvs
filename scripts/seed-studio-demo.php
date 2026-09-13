<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Core\Config;

Environment::load(dirname(__DIR__));

$base = rtrim((string) Config::get('APP_URL', ''), '/');
$host = (string) parse_url($base, PHP_URL_HOST);
if (!in_array($host, ['localhost', '127.0.0.1', 'mvs.lab'], true) || Config::isProduction()) {
    throw new RuntimeException('O seed de demonstração só pode ser executado no ambiente local não produtivo.');
}

$pdo = Connection::getInstance();
$authorId = $pdo->query("SELECT id FROM users WHERE status='active' ORDER BY (role='admin') DESC,id LIMIT 1")->fetchColumn() ?: null;

$taxonomy = static function (string $type, string $name, string $slug) use ($pdo): int {
    $find = $pdo->prepare('SELECT id FROM studio_taxonomies WHERE type=? AND slug=?');
    $find->execute([$type, $slug]);
    $id = (int) $find->fetchColumn();
    if ($id > 0) { return $id; }
    $pdo->prepare('INSERT INTO studio_taxonomies(type,name,slug) VALUES(?,?,?)')->execute([$type, $name, $slug]);
    return (int) $pdo->lastInsertId();
};

$saveContent = static function (array $record) use ($pdo, $authorId): void {
    $sql = 'INSERT INTO studio_content(type,title,slug,excerpt,content,seo_title,seo_description,media_id,category_id,template,meta_json,status,position,starts_at,ends_at,published_at,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),excerpt=VALUES(excerpt),content=VALUES(content),seo_title=VALUES(seo_title),seo_description=VALUES(seo_description),category_id=VALUES(category_id),template=VALUES(template),meta_json=VALUES(meta_json),status=VALUES(status),position=VALUES(position),starts_at=VALUES(starts_at),ends_at=VALUES(ends_at),published_at=VALUES(published_at)';
    $pdo->prepare($sql)->execute([
        $record['type'], $record['title'], $record['slug'], $record['excerpt'] ?? null,
        $record['content'] ?? null, $record['seo_title'] ?? $record['title'],
        $record['seo_description'] ?? $record['excerpt'] ?? $record['title'], null,
        $record['category_id'] ?? null, $record['template'] ?? null,
        json_encode($record['meta'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'published', $record['position'] ?? 0, $record['starts_at'] ?? null,
        $record['ends_at'] ?? null, date('Y-m-d H:i:s'), $authorId,
    ]);
};

$pdo->beginTransaction();
try {
    $articleCategory = $taxonomy('article_category', 'Tecnologia', 'tecnologia');
    $faqCategory = $taxonomy('faq_category', 'Projetos digitais', 'projetos-digitais');
    $siteCategory = $taxonomy('project_category', 'Sites', 'sites');
    $systemCategory = $taxonomy('project_category', 'Sistemas', 'sistemas');
    $appCategory = $taxonomy('project_category', 'Aplicativos', 'aplicativos');

    $records = [
        ['type'=>'page','title'=>'Como trabalhamos','slug'=>'como-trabalhamos','excerpt'=>'Conheça as etapas que transformam uma ideia em produto digital.','content'=>'<h2>Estratégia antes da execução</h2><p>Descoberta, direção visual, desenvolvimento, validação e evolução contínua.</p>','template'=>'wide','position'=>10],
        ['type'=>'article','title'=>'Como preparar um projeto digital','slug'=>'como-preparar-um-projeto-digital','excerpt'=>'Um roteiro prático para organizar objetivos, conteúdo e prioridades.','content'=>'<h2>Comece pelo objetivo</h2><p>Defina público, problema e resultado esperado antes de escolher ferramentas.</p>','category_id'=>$articleCategory,'meta'=>['video'=>''],'position'=>10],
        ['type'=>'highlight','title'=>'Seu próximo produto começa com clareza','slug'=>'produto-com-clareza','excerpt'=>'Planejamento, design e tecnologia trabalhando no mesmo ritmo.','meta'=>['cta_label'=>'Conversar com a Moves','cta_url'=>'/contato','alignment'=>'left'],'position'=>10],
        ['type'=>'testimonial','title'=>'Marina Costa','slug'=>'marina-costa','excerpt'=>'A Moves organizou nossa visão e entregou uma experiência simples de usar e fácil de evoluir.','meta'=>['company'=>'Horizonte','job_title'=>'Diretora de produto'],'position'=>10],
        ['type'=>'faq','title'=>'Quanto tempo leva um projeto?','slug'=>'quanto-tempo-leva-um-projeto','content'=>'O prazo depende do escopo. Depois da descoberta, entregamos um cronograma claro com etapas e responsáveis.','category_id'=>$faqCategory,'position'=>10],
        ['type'=>'project','title'=>'Connect Condomínios','slug'=>'connect-condominios','excerpt'=>'Portal e plataforma digital para aproximar administradoras, síndicos e moradores.','category_id'=>$systemCategory,'meta'=>['client'=>'Connect Condomínios','kind'=>'Plataforma digital','project_url'=>'/contato?servico=sistemas-web','image'=>'images/portfolio/dynamicasoft.png','backdrop'=>'images/portfolio/dynamicasoft-bg.jpg'],'position'=>10],
        ['type'=>'project','title'=>'Studio Alta','slug'=>'studio-alta','excerpt'=>'Presença digital editorial para arquitetura e projetos autorais.','category_id'=>$siteCategory,'meta'=>['client'=>'Studio Alta','kind'=>'Site institucional','project_url'=>'https://studioalta.arq.br/','image'=>'images/portfolio/studio-alta.png','backdrop'=>'images/portfolio/studio-alta-bg.jpg'],'position'=>20],
        ['type'=>'project','title'=>'Lucro Certo','slug'=>'lucro-certo','excerpt'=>'Experiência de produto para acompanhar decisões financeiras no celular.','category_id'=>$appCategory,'meta'=>['client'=>'Lucro Certo','kind'=>'Aplicativo','project_url'=>'https://applucrocerto.com.br/','image'=>'images/portfolio/lucro-certo.png','backdrop'=>'images/portfolio/lucro-certo-bg.jpg'],'position'=>30],
        ['type'=>'project','title'=>'Atmosfera Inovação','slug'=>'atmosfera-inovacao','excerpt'=>'Site que apresenta serviços, conhecimento e oportunidades de inovação.','category_id'=>$siteCategory,'meta'=>['client'=>'Atmosfera Inovação','kind'=>'Site institucional','project_url'=>'https://atmosferainovacao.com/','image'=>'images/portfolio/atmosferainovacao.png','backdrop'=>'images/portfolio/atmosferainovacao-bg.jpg'],'position'=>40],
    ];
    foreach ($records as $record) { $saveContent($record); }

    $proposalEmail = 'portfolio-demo@moves.local';
    $proposal = $pdo->prepare('SELECT id FROM proposals WHERE email=? AND service=? LIMIT 1');
    $proposal->execute([$proposalEmail, 'site']);
    if (!$proposal->fetchColumn()) {
        $pdo->prepare('INSERT INTO proposals(name,email,company,service,message,status) VALUES(?,?,?,?,?,?)')->execute(['Cliente demonstração',$proposalEmail,'Empresa Exemplo','site','Gostaria de apresentar um novo projeto no portfólio da Moves.','new']);
    }

    $notificationTitle = 'Conteúdo de demonstração disponível';
    $notification = $pdo->prepare('SELECT id FROM notifications WHERE title=? AND source_type=? LIMIT 1');
    $notification->execute([$notificationTitle, 'demo']);
    if (!$notification->fetchColumn()) {
        $pdo->prepare('INSERT INTO notifications(title,message,recipient_id,source_type,action_url,link) VALUES(?,?,?,?,?,?)')->execute([$notificationTitle,'Páginas, artigos, projetos e componentes foram cadastrados para validação.',null,'demo','/studio/projects','/studio/projects']);
    }

    $pdo->commit();
    echo 'OK: dados de demonstração cadastrados ou atualizados (9 conteúdos, 1 proposta e 1 notificação).' . PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    throw $exception;
}
