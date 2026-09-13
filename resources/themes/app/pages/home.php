<?php

/**
 * Moves | Home Page
 *
 * Exibe a página inicial do tema padrão.
 *
 * @author Djalma Martins
 */

$this->layout('layouts/default', [
    'title' => $title,
    'currentPage' => 'dashboard',
]);
?>
<section class="customer-page-heading"><div><p class="customer-eyebrow">VISÃO GERAL</p><h2>Olá, <?= $this->e(explode(' ',trim((string)$user->name))[0] ?: 'cliente') ?>.</h2><p>Aqui você acompanha seus serviços, projetos e atendimentos com a Moves.</p></div></section>
<section class="customer-metrics" aria-label="Resumo da conta"><?php foreach([['Serviços',$summary['services'],'◇'],['Projetos',$summary['projects'],'▧'],['Chamados',$summary['tickets'],'◌'],['Faturas',$summary['invoices'],'▤']] as [$label,$value,$icon]):?><article><span aria-hidden="true"><?= $icon ?></span><div><strong><?= $this->e((string)$value) ?></strong><small><?= $this->e($label) ?></small></div></article><?php endforeach;?></section>
<section class="customer-grid"><article class="customer-card"><p class="customer-eyebrow">PROJETOS RECENTES</p><h2>Seus projetos</h2><div class="customer-empty"><strong>Você ainda não possui projetos em andamento.</strong><p>Quando um projeto for vinculado à sua conta, ele aparecerá aqui.</p></div></article><article class="customer-card"><p class="customer-eyebrow">PRÓXIMAS AÇÕES</p><h2>Tudo em dia</h2><div class="customer-empty"><strong>Nenhuma ação pendente.</strong><p>Novas solicitações e atualizações serão apresentadas nesta área.</p></div></article></section>
<section class="customer-card customer-activity"><p class="customer-eyebrow">ATIVIDADE RECENTE</p><h2>Histórico da conta</h2><div class="customer-empty"><strong>Nenhuma atividade registrada.</strong><p>Somente eventos reais relacionados à sua conta serão exibidos.</p></div></section>
