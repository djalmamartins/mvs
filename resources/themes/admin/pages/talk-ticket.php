<?php
declare(strict_types=1);
$this->layout('layouts/default', [
    'title'=>$title,'productName'=>'Talk','activeProduct'=>'talk','currentPage'=>$currentPage,
]);
?>
<section class="studio-page talk-page">
<header class="knowledge-header"><div><p class="studio-eyebrow">MOVES TALK</p><h1><?= $this->e((string)$ticket['contact_name']) ?></h1><p><?= $this->e((string)$ticket['protocol']) ?> · <?= $this->e((string)$ticket['queue_name']) ?> · <?= $this->e((string)$ticket['status']) ?></p></div>
<div class="knowledge-header-actions"><a class="studio-btn" href="/talk/queue">Voltar à fila</a></div></header>
<div class="support-editor-layout">
<main class="support-editor-main">
<section class="studio-panel"><div class="studio-panel-body">
<h2>Conversa</h2>
<?php if(($ticket['messages']??[])===[]): ?><div class="knowledge-empty"><strong>Sem mensagens</strong></div><?php else: ?>
<div class="talk-thread">
<?php foreach($ticket['messages'] as $message): ?>
<article class="talk-message talk-message-<?= $this->e((string)$message['direction']) ?>">
<small><?= $this->e((string)($message['sender_name'] ?? ($message['sender_type']==='contact' ? $ticket['contact_name'] : 'Sistema'))) ?> · <?= $this->e((string)$message['sent_at']) ?></small>
<p><?= nl2br($this->e((string)$message['body'])) ?></p>
</article>
<?php endforeach; ?>
</div><?php endif; ?>
<?php if($ticket['source']==='simulation' && $ticket['status']==='assigned'): ?>
<form method="post" action="/talk/tickets/<?= (int)$ticket['id'] ?>">
<?= $this->csrf() ?><input type="hidden" name="action" value="send">
<div class="studio-field"><label for="talk-body">Mensagem de simulação</label><textarea id="talk-body" name="body" rows="3" maxlength="4000" required></textarea></div>
<button class="studio-btn studio-btn-primary" type="submit">Enviar</button>
</form>
<?php endif; ?>
</div></section>
</main>
<aside class="support-editor-sidebar">
<section class="studio-panel"><div class="studio-panel-body"><h3>Atendimento</h3>
<p><strong>Contato</strong><br><?= $this->e((string)$ticket['contact_name']) ?></p>
<p><strong>Telefone</strong><br><?= $this->e((string)($ticket['contact_phone']??'—')) ?></p>
<p><strong>Canal</strong><br><?= $this->e((string)$ticket['channel']) ?></p>
<p><strong>Prioridade</strong><br><?= $this->e((string)$ticket['priority']) ?></p>
<p><strong>Atendente</strong><br><?= $this->e((string)($ticket['assigned_name']??'Não atribuído')) ?></p>
</div></section>
<section class="studio-panel"><div class="studio-panel-body"><h3>Timeline</h3>
<?php foreach(($ticket['events']??[]) as $event): ?><p><small><?= $this->e((string)$event['created_at']) ?></small><br><?= $this->e((string)$event['event_type']) ?></p><?php endforeach; ?>
</div></section>
</aside>
</div>
</section>
