<?php

declare(strict_types=1);

/** Moves Studio | Dashboard. */

$this->layout('layouts/default', ['title' => $title, 'currentPage' => 'dashboard']);
$totalChecks = count($checks);
?>
<section class="studio-page-head studio-dashboard-head">
    <div><p class="studio-eyebrow">Visão geral</p><h1 class="studio-page-title">Olá, bem-vindo ao Studio!</h1><p class="studio-page-description">Aqui está o resumo do seu site hoje.</p></div>
    <div class="actions"><a class="studio-btn outline" href="/" target="_blank" rel="noopener"><span aria-hidden="true">◎</span> Visualizar site</a></div>
</section>
<section class="studio-dashboard-kpis" aria-label="Indicadores atuais">
    <a class="studio-panel" href="/admin/pages"><i aria-hidden="true">▤</i><div><span>Páginas</span><strong><?= $this->e((string) $contentCounts['page']) ?></strong><small>conteúdos cadastrados</small></div></a>
    <a class="studio-panel" href="/admin/articles"><i aria-hidden="true">¶</i><div><span>Artigos</span><strong><?= $this->e((string) $contentCounts['article']) ?></strong><small><?= $this->e((string) $publishedArticles) ?> publicado(s)</small></div></a>
    <a class="studio-panel" href="/admin/users"><i aria-hidden="true">◎</i><div><span>Usuários</span><strong><?= $this->e((string) $userCount) ?></strong><small>contas cadastradas</small></div></a>
    <a class="studio-panel" href="/admin/diagnostics"><i aria-hidden="true">✓</i><div><span>Saúde</span><strong><?= $this->e((string) $healthyChecks) ?>/<?= $this->e((string) $totalChecks) ?></strong><small>verificações aprovadas</small></div></a>
    <a class="studio-panel" href="/admin/proposals"><i aria-hidden="true">◇</i><div><span>Propostas</span><strong><?= $this->e((string) $proposalCount) ?></strong><small>recebidas pelo contato</small></div></a>
</section>
<section class="studio-dashboard-actions studio-panel-body"><p class="studio-eyebrow">Ações rápidas</p><div class="studio-actions"><a class="studio-btn primary" href="/admin/pages#editor">Nova página</a><a class="studio-btn" href="/admin/articles#editor">Novo artigo</a><a class="studio-btn" href="/admin/highlights#editor">Novo destaque</a><a class="studio-btn" href="/admin/faq">Perguntas frequentes</a><a class="studio-btn" href="/admin/proposals">Propostas recebidas</a><a class="studio-btn" href="/admin/settings">Configurações</a></div></section>
<section class="studio-panel-grid">
    <article class="studio-panel"><div class="studio-panel-heading"><div><p class="studio-eyebrow">CONTEÚDO</p><h2>CMS</h2></div><span class="studio-badge">Ativo</span></div><p>Conteúdo editorial e biblioteca de mídia conectados ao banco.</p><ul class="studio-module-list"><?php foreach(['page'=>['Páginas','pages'],'article'=>['Artigos','articles'],'media'=>['Mídia','media'],'highlight'=>['Destaques','highlights'],'testimonial'=>['Depoimentos','testimonials'],'faq'=>['FAQ','faq']] as $type=>[$label,$route]):?><li><a href="/admin/<?= $route ?>"><?= $label ?></a><small><?= $this->e((string)$contentCounts[$type]) ?> registro(s)</small></li><?php endforeach;?></ul></article>
    <article class="studio-panel"><div class="studio-panel-heading"><div><p class="studio-eyebrow">ACESSOS RÁPIDOS</p><h2>Ferramentas ativas</h2></div></div><div class="studio-quick-links"><a href="/admin/users"><span aria-hidden="true">◎</span><div><strong>Usuários</strong><small>Listagem e permissões atuais</small></div><b aria-hidden="true">→</b></a><a href="/admin/settings"><span aria-hidden="true">⚙</span><div><strong>Configurações</strong><small>Nome da aplicação</small></div><b aria-hidden="true">→</b></a><a href="/admin/diagnostics"><span aria-hidden="true">✓</span><div><strong>Diagnóstico</strong><small>Ambiente e dependências</small></div><b aria-hidden="true">→</b></a><a href="/admin/logs"><span aria-hidden="true">≡</span><div><strong>Log</strong><small>Atividade técnica sanitizada</small></div><b aria-hidden="true">→</b></a></div></article>
</section>
<section class="studio-panel studio-activity-panel">
    <div class="studio-panel-heading"><div><p class="studio-eyebrow">ATIVIDADE RECENTE</p><h2>Eventos da aplicação</h2></div><a class="studio-text-link" href="/admin/logs">Ver log completo →</a></div>
    <?php if ($activity === []): ?>
        <p class="studio-empty">Ainda não há eventos registrados.</p>
    <?php else: ?>
        <ul class="studio-activity-list">
            <?php foreach ($activity as $entry): ?>
                <li><span class="studio-status studio-status-<?= $this->e($entry['level']) ?>"><?= $this->e($entry['level']) ?></span><div><strong><?= $this->e($entry['message']) ?></strong><small><?= $this->e($entry['timestamp']) ?></small></div></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
