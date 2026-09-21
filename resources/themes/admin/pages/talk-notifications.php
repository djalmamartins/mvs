<?php
declare(strict_types=1);
$this->layout('layouts/default',['title'=>$title,'productName'=>'Talk','activeProduct'=>'talk','currentPage'=>'notifications']);
$notifications=$notifications??[];$notificationCount=(int)($notificationCount??0);
?>
<section class="studio-page talk-page">
<header class="knowledge-header"><div><p class="studio-eyebrow">MOVES TALK</p><h1><?= $this->e($title) ?></h1><p>Acompanhe transferências, alterações e eventos que exigem sua atenção.</p></div><?php if($notificationCount>0): ?><form method="post" action="/talk/notifications"><?= $this->csrf() ?><input type="hidden" name="action" value="read_all"><button class="studio-btn" type="submit"><i class="icon-checkmark-outline"></i> Marcar todas como lidas</button></form><?php endif; ?></header>
<div class="studio-dashboard-kpis"><article><span>Não lidas</span><strong><?= $notificationCount ?></strong></article></div>
<section class="studio-panel"><div class="studio-panel-body">
<?php if($notifications===[]): ?><div class="knowledge-empty"><i class="icon-notifications-outline"></i><strong>Tudo em dia</strong><span>Você não possui notificações pendentes.</span></div>
<?php else: ?><div class="studio-table-wrap"><table class="studio-table"><thead><tr><th>Evento</th><th>Atendimento</th><th>Detalhes</th><th>Data</th><th>Ação</th></tr></thead><tbody><?php foreach($notifications as $row): ?><tr><td><strong><?= $this->e((string)$row['title']) ?></strong><br><small><?= $this->e((string)$row['type']) ?></small></td><td><?php if($row['ticket_id']!==null): ?><a href="/talk/tickets/<?= (int)$row['ticket_id'] ?>"><?= $this->e((string)($row['protocol']??'#'.(int)$row['ticket_id'])) ?></a><?php else: ?>—<?php endif; ?></td><td><?= $this->e((string)($row['body']??'—')) ?></td><td><?= $this->e((string)$row['created_at']) ?></td><td><form method="post" action="/talk/notifications"><?= $this->csrf() ?><input type="hidden" name="action" value="read"><input type="hidden" name="notification_id" value="<?= (int)$row['id'] ?>"><button class="studio-btn" type="submit"><i class="icon-checkmark-outline"></i> Lida</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</div></section></section>
