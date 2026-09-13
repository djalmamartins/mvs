<?php

declare(strict_types=1);

/** Moves Studio | Dashboard. */

$this->layout('layouts/default', ['title' => $title, 'currentPage' => 'dashboard']);
$totalChecks = count($checks);
?>
<section class="studio-page-heading">
    <div><p class="studio-eyebrow">VISÃO GERAL</p><h2>Olá, bem-vindo ao Studio.</h2><p>Acompanhe o que já está ativo e prepare os próximos movimentos da plataforma.</p></div>
    <a class="studio-button" href="/admin/diagnostics">Ver saúde do sistema</a>
</section>
<section class="studio-metrics studio-metrics-four" aria-label="Indicadores atuais">
    <article><span class="metric-icon" aria-hidden="true">◎</span><div><small>USUÁRIOS CADASTRADOS</small><strong><?= $this->e((string) $userCount) ?></strong><a href="/admin/users">Gerenciar usuários →</a></div></article>
    <article><span class="metric-icon healthy" aria-hidden="true">✓</span><div><small>SAÚDE DA APLICAÇÃO</small><strong><?= $this->e((string) $healthyChecks) ?>/<?= $this->e((string) $totalChecks) ?></strong><a href="/admin/diagnostics">Abrir diagnóstico →</a></div></article>
    <article><span class="metric-icon" aria-hidden="true">⚙</span><div><small>APLICAÇÃO</small><strong class="metric-name"><?= $this->e($appName) ?></strong><a href="/admin/settings">Editar configuração →</a></div></article>
    <article><span class="metric-icon" aria-hidden="true">◫</span><div><small>AMBIENTE</small><strong class="metric-name"><?= $this->e($environment) ?></strong><a href="/admin/versions">Moves <?= $this->e($version) ?> →</a></div></article>
</section>
<section class="studio-panel-grid">
    <article class="studio-panel"><div class="studio-panel-heading"><div><p class="studio-eyebrow">CONTEÚDO</p><h2>CMS</h2></div><span class="studio-badge">Ativo</span></div><p>Conteúdo editorial e biblioteca de mídia conectados ao banco.</p><ul class="studio-module-list"><li><a href="/admin/pages">Páginas</a><small><?= $this->e((string)$contentCounts['page']) ?> registro(s)</small></li><li><a href="/admin/articles">Artigos</a><small><?= $this->e((string)$contentCounts['article']) ?> registro(s)</small></li><li><a href="/admin/media">Mídia</a><small><?= $this->e((string)$contentCounts['media']) ?> arquivo(s)</small></li></ul></article>
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
