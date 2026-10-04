<?php declare(strict_types=1);
$this->layout('layouts/default',['title'=>$title,'productName'=>'Suporte','activeProduct'=>'support','currentPage'=>'tickets']);
$labels=['open'=>'Aberto','in_progress'=>'Em andamento','waiting'=>'Aguardando','resolved'=>'Resolvido','closed'=>'Fechado'];
$priorities=['low'=>'Baixa','normal'=>'Normal','high'=>'Alta','urgent'=>'Urgente'];
?>
<section class="studio-page">
<header class="studio-page-header"><div><span><?= $this->e((string)$ticket['protocol']) ?></span><h1><?= $this->e((string)$ticket['subject']) ?></h1><p>Criado em <?= $this->e(date('d/m/Y H:i',strtotime((string)$ticket['created_at']))) ?></p></div><a class="studio-btn" href="/support/my-tickets">Voltar</a></header>
<section class="studio-card"><div class="support-ticket-meta"><div><small>Status</small><strong><?= $this->e($labels[(string)$ticket['status']]??(string)$ticket['status']) ?></strong></div><div><small>Prioridade</small><strong><?= $this->e($priorities[(string)$ticket['priority']]??(string)$ticket['priority']) ?></strong></div><?php if(!empty($ticket['due_at'])): ?><div><small>Prazo</small><strong><?= $this->e(date('d/m/Y H:i',strtotime((string)$ticket['due_at']))) ?></strong></div><?php endif; ?></div><hr><h2>Descrição</h2><p><?= nl2br($this->e((string)$ticket['description'])) ?></p></section>
</section>
