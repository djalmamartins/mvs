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
<section class="studio-metrics" aria-label="Indicadores atuais">
    <article><span class="metric-icon" aria-hidden="true">◎</span><div><small>USUÁRIOS CADASTRADOS</small><strong><?= $this->e((string) $userCount) ?></strong><a href="/admin/users">Gerenciar usuários →</a></div></article>
    <article><span class="metric-icon healthy" aria-hidden="true">✓</span><div><small>SAÚDE DA APLICAÇÃO</small><strong><?= $this->e((string) $healthyChecks) ?>/<?= $this->e((string) $totalChecks) ?></strong><a href="/admin/diagnostics">Abrir diagnóstico →</a></div></article>
    <article><span class="metric-icon" aria-hidden="true">⚙</span><div><small>APLICAÇÃO</small><strong class="metric-name"><?= $this->e($appName) ?></strong><a href="/admin/settings">Editar configuração →</a></div></article>
</section>
<section class="studio-panel-grid">
    <article class="studio-panel"><div class="studio-panel-heading"><div><p class="studio-eyebrow">CONTEÚDO</p><h2>CMS em construção</h2></div><span class="studio-badge">Fundação</span></div><p>O shell do Studio está pronto para receber os módulos editoriais sem antecipar dados ou fluxos inexistentes.</p><ul class="studio-module-list"><li><span>Páginas</span><small>Não iniciado</small></li><li><span>Artigos</span><small>Não iniciado</small></li><li><span>Mídia</span><small>Não iniciado</small></li></ul></article>
    <article class="studio-panel"><div class="studio-panel-heading"><div><p class="studio-eyebrow">ACESSOS RÁPIDOS</p><h2>Ferramentas ativas</h2></div></div><div class="studio-quick-links"><a href="/admin/users"><span aria-hidden="true">◎</span><div><strong>Usuários</strong><small>Listagem e permissões atuais</small></div><b aria-hidden="true">→</b></a><a href="/admin/settings"><span aria-hidden="true">⚙</span><div><strong>Configurações</strong><small>Nome da aplicação</small></div><b aria-hidden="true">→</b></a><a href="/admin/diagnostics"><span aria-hidden="true">✓</span><div><strong>Diagnóstico</strong><small>Ambiente e dependências</small></div><b aria-hidden="true">→</b></a></div></article>
</section>
