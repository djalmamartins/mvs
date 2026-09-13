<?php

declare(strict_types=1);

use Moves\Core\Auth;

/** Moves Studio | Dashboard. */

$this->layout('layouts/default', ['title' => $title, 'currentPage' => 'dashboard']);
$totalChecks = count($checks);
$currentUser = Auth::user();
$firstName = trim(explode(' ', (string) ($currentUser?->name ?? ''))[0] ?? '');
?>
<section class="studio-page-head studio-dashboard-head">
    <div><p class="studio-eyebrow">Visão geral</p><h1 class="studio-page-title">Olá<?= $firstName !== '' ? ', '.$this->e($firstName) : '' ?>!</h1><p class="studio-page-description">Aqui está o resumo do seu site hoje.</p></div>
    <div class="actions"><a class="studio-btn outline" href="/" target="_blank" rel="noopener"><?= studio_icon('globe') ?> Visualizar site</a></div>
</section>
<section class="studio-dashboard-kpis" aria-label="Indicadores atuais">
    <a class="studio-panel" href="/admin/pages"><i aria-hidden="true"><?= studio_icon('copy') ?></i><div><span>Páginas</span><strong><?= $this->e((string) $contentCounts['page']) ?></strong><small>conteúdos cadastrados</small></div></a>
    <a class="studio-panel" href="/admin/projects"><i aria-hidden="true"><?= studio_icon('briefcase') ?></i><div><span>Projetos</span><strong><?= $this->e((string) $contentCounts['project']) ?></strong><small>itens de portfólio</small></div></a>
    <a class="studio-panel" href="/admin/articles"><i aria-hidden="true"><?= studio_icon('newspaper') ?></i><div><span>Artigos</span><strong><?= $this->e((string) $contentCounts['article']) ?></strong><small><?= $this->e((string) $publishedArticles) ?> publicado(s)</small></div></a>
    <a class="studio-panel" href="/admin/users"><i aria-hidden="true"><?= studio_icon('users') ?></i><div><span>Usuários</span><strong><?= $this->e((string) $userCount) ?></strong><small>contas cadastradas</small></div></a>
    <a class="studio-panel" href="/admin/diagnostics"><i aria-hidden="true"><?= studio_icon('sparkles') ?></i><div><span>Saúde</span><strong><?= $this->e((string) $healthyChecks) ?>/<?= $this->e((string) $totalChecks) ?></strong><small>verificações aprovadas</small></div></a>
    <a class="studio-panel studio-proposal-kpi" href="/admin/proposals"><i aria-hidden="true"><?= studio_icon('file-text') ?></i><div><span>Propostas</span><strong><?= $this->e((string) $proposalCount) ?></strong><small>acompanhe negociações</small></div></a>
</section>
<section class="studio-dashboard-actions studio-panel-body"><p class="studio-eyebrow">Ações rápidas</p><div class="studio-actions"><a class="studio-btn primary" href="/admin/pages?create=1">Nova página</a><a class="studio-btn" href="/admin/projects?create=1">Novo projeto</a><a class="studio-btn" href="/admin/articles?create=1">Novo artigo</a><a class="studio-btn" href="/admin/highlights?create=1">Novo destaque</a><a class="studio-btn" href="/admin/faq">Perguntas frequentes</a><a class="studio-btn" href="/admin/proposals">Propostas recebidas</a><a class="studio-btn" href="/admin/settings">Configurações</a></div></section>
