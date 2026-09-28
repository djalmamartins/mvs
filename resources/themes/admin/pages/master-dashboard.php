<?php
declare(strict_types=1);
$this->layout('layouts/default',['title'=>$title,'productName'=>'Master','activeProduct'=>'master','currentPage'=>'dashboard']);
?>
<header class="page-heading"><div><span class="kicker">MOVES MASTER</span><h1>Visão geral</h1><p>Administração central das empresas que operam na Moves Platform.</p></div><a class="primary-btn" href="/master/administrators/create"><i class="icon-add-outline"></i>Nova administradora</a></header>
<div class="metrics">
<article><span>Administradoras</span><strong><?= (int)$stats['total'] ?></strong><small>cadastradas na plataforma</small></article>
<article><span>Ativas</span><strong><?= (int)$stats['active'] ?></strong><small>operando normalmente</small></article>
<article><span>Inativas / suspensas</span><strong><?= (int)$stats['inactive'] ?></strong><small>com acesso operacional bloqueado</small></article>
<article><span>Governança</span><strong>100%</strong><small>alterações MST auditadas</small></article>
</div>
<section class="surface mst-home"><div class="surface-head"><div><h2>Administração da plataforma</h2><p>Acesso rápido ao ciclo de vida das administradoras.</p></div><a class="text-btn" href="/master/administrators">Ver todas</a></div>
<div class="mst-actions"><a href="/master/administrators"><i class="icon-business"></i><span><strong>Administradoras</strong><small>Pesquisar, visualizar e controlar status.</small></span></a><a href="/master/administrators/create"><i class="icon-add-outline"></i><span><strong>Novo cadastro</strong><small>Criar tenant e dados empresariais em uma única operação.</small></span></a></div></section>