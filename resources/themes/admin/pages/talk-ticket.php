<?php
declare(strict_types=1);
$this->layout('layouts/default',['title'=>$title,'productName'=>'Talk','activeProduct'=>'talk','currentPage'=>$currentPage]);
$closed=$ticket['status']==='closed';
$queuedAt=new DateTimeImmutable((string)($ticket['queued_at']??$ticket['created_at']));
$firstDue=!empty($ticket['sla_due_at'])?new DateTimeImmutable((string)$ticket['sla_due_at']):$queuedAt->modify('+15 minutes');
$resolveDue=$queuedAt->modify('+240 minutes');
$now=new DateTimeImmutable();
$firstResponseAt=!empty($ticket['first_response_at'])?new DateTimeImmutable((string)$ticket['first_response_at']):null;
$closedAt=!empty($ticket['closed_at'])?new DateTimeImmutable((string)$ticket['closed_at']):null;
$firstBreached=$firstResponseAt ? $firstResponseAt>$firstDue : (!$closed && $now>$firstDue);
$resolveBreached=$closedAt ? $closedAt>$resolveDue : (!$closed && $now>$resolveDue);
$firstLabel=$firstResponseAt ? ($firstBreached?'Respondido fora do SLA':'Primeira resposta no SLA') : ($firstBreached?'Primeira resposta vencida':'Aguardando primeira resposta');
$resolveLabel=$closed ? ($resolveBreached?'Finalizado fora do SLA':'Finalizado no SLA') : ($resolveBreached?'SLA de resolução vencido':'SLA de resolução em andamento');
?>
<section class="studio-page talk-page">
<header class="knowledge-header"><div><p class="studio-eyebrow">MOVES TALK</p><h1><?= $this->e((string)$ticket['contact_name']) ?></h1><p><?= $this->e((string)$ticket['protocol']) ?> · <?= $this->e((string)($ticket['queue_name']??'Sem fila')) ?> · <?= $this->e((string)$ticket['status']) ?></p></div>
<div class="knowledge-header-actions"><a class="studio-btn" href="<?= $closed?'/talk/history':'/talk/my-tickets' ?>">Voltar</a></div></header>
<div class="studio-dashboard-kpis">
<article><span>Prioridade</span><strong><?= $this->e(ucfirst((string)$ticket['priority'])) ?></strong></article>
<article><span>Primeira resposta</span><strong><?= $this->e($firstLabel) ?></strong><small>Limite <?= $this->e($firstDue->format('d/m H:i')) ?></small></article>
<article><span>Resolução</span><strong><?= $this->e($resolveLabel) ?></strong><small>Limite <?= $this->e($resolveDue->format('d/m H:i')) ?></small></article>
<article><span>Última atividade</span><strong><?= $this->e((string)($ticket['last_activity_at']??$ticket['updated_at'])) ?></strong></article>
</div>
<div class="support-editor-layout">
<main class="support-editor-main">
<section class="studio-panel"><div class="studio-panel-body"><h2>Conversa</h2>
<div class="talk-thread">
<?php foreach(($ticket['messages']??[]) as $message): ?><article class="talk-message talk-message-<?= $this->e((string)$message['direction']) ?>"><small><?= $this->e((string)($message['sender_name']??($message['sender_type']==='contact'?$ticket['contact_name']:'Sistema'))) ?> · <?= $this->e((string)$message['sent_at']) ?></small><p><?= nl2br($this->e((string)$message['body'])) ?></p></article><?php endforeach; ?>
</div>
<?php if(!$closed && $ticket['source']==='simulation' && $ticket['status']==='assigned'): ?><form method="post" action="/talk/tickets/<?= (int)$ticket['id'] ?>"><?= $this->csrf() ?><input type="hidden" name="action" value="send"><div class="studio-field"><label for="talk-body">Mensagem</label><textarea id="talk-body" name="body" rows="3" maxlength="4000" required></textarea></div><button class="studio-btn studio-btn-primary" type="submit">Enviar</button></form><?php endif; ?>
</div></section>
<section class="studio-panel"><div class="studio-panel-body"><h2>Notas internas</h2>
<?php foreach(($ticket['notes']??[]) as $note): ?><p><small><?= $this->e((string)$note['user_name']) ?> · <?= $this->e((string)$note['created_at']) ?></small><br><?= nl2br($this->e((string)$note['body'])) ?></p><?php endforeach; ?>
<?php if(!$closed): ?><form method="post" action="/talk/tickets/<?= (int)$ticket['id'] ?>"><?= $this->csrf() ?><input type="hidden" name="action" value="note"><div class="studio-field"><label for="note-body">Adicionar nota</label><textarea id="note-body" name="body" rows="2" maxlength="4000" required></textarea></div><button class="studio-btn" type="submit">Adicionar nota</button></form><?php endif; ?>
</div></section>
</main>
<aside class="support-editor-sidebar">
<section class="studio-panel"><div class="studio-panel-body"><h3>Atendimento</h3><p><strong>Contato</strong><br><?= $this->e((string)$ticket['contact_name']) ?></p><p><strong>Telefone</strong><br><?= $this->e((string)($ticket['contact_phone']??'—')) ?></p><p><strong>Canal</strong><br><?= $this->e((string)$ticket['channel']) ?></p><p><strong>Prioridade</strong><br><i class="icon-pricetag-outline"></i> <?= $this->e((string)$ticket['priority']) ?></p><p><strong>Atendente</strong><br><?= $this->e((string)($ticket['assigned_name']??'Não atribuído')) ?></p></div></section>
<section class="studio-panel"><div class="studio-panel-body"><h3><i class="icon-stats-chart-outline"></i> SLA</h3><p><strong>Primeira resposta</strong><br><?= $this->e($firstLabel) ?><br><small><?= $firstResponseAt?$this->e($firstResponseAt->format('d/m/Y H:i')):'Limite '.$this->e($firstDue->format('d/m/Y H:i')) ?></small></p><p><strong>Resolução</strong><br><?= $this->e($resolveLabel) ?><br><small><?= $closedAt?$this->e($closedAt->format('d/m/Y H:i')):'Limite '.$this->e($resolveDue->format('d/m/Y H:i')) ?></small></p></div></section>
<?php if(!$closed): ?><section class="studio-panel"><div class="studio-panel-body"><h3>Transferir</h3><form method="post" action="/talk/tickets/<?= (int)$ticket['id'] ?>"><?= $this->csrf() ?><input type="hidden" name="action" value="transfer"><div class="studio-field"><label>Fila</label><select name="to_queue_id"><option value="">Manter fila</option><?php foreach(($ticket['queues']??[]) as $q): ?><option value="<?= (int)$q['id'] ?>"><?= $this->e((string)$q['name']) ?></option><?php endforeach; ?></select></div><div class="studio-field"><label>Atendente</label><select name="to_user_id"><option value="">Somente fila</option><?php foreach(($ticket['eligible_users']??[]) as $u): ?><option value="<?= (int)$u['id'] ?>"><?= $this->e((string)$u['name']) ?></option><?php endforeach; ?></select></div><div class="studio-field"><label>Motivo</label><input name="reason" maxlength="500"></div><button class="studio-btn" type="submit">Transferir</button></form></div></section>
<section class="studio-panel"><div class="studio-panel-body"><h3>Ações</h3><form method="post" action="/talk/tickets/<?= (int)$ticket['id'] ?>"><?= $this->csrf() ?><input type="hidden" name="action" value="return_queue"><button class="studio-btn" type="submit">Devolver para fila</button></form><form method="post" action="/talk/tickets/<?= (int)$ticket['id'] ?>"><?= $this->csrf() ?><input type="hidden" name="action" value="close"><button class="studio-btn studio-btn-primary" type="submit">Finalizar atendimento</button></form></div></section>
<?php else: ?><section class="studio-panel"><div class="studio-panel-body"><h3>Ações</h3><form method="post" action="/talk/tickets/<?= (int)$ticket['id'] ?>"><?= $this->csrf() ?><input type="hidden" name="action" value="reopen"><button class="studio-btn studio-btn-primary" type="submit">Reabrir atendimento</button></form></div></section><?php endif; ?>
<section class="studio-panel"><div class="studio-panel-body"><h3>Timeline</h3><?php foreach(($ticket['events']??[]) as $event): ?><p><small><?= $this->e((string)$event['created_at']) ?></small><br><?= $this->e((string)$event['event_type']) ?></p><?php endforeach; ?></div></section>
</aside></div></section>