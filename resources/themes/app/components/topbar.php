<?php
use Moves\Core\Access;
use Moves\Core\Auth;
use Moves\Services\Talk\TalkNotificationService;
$currentUser=Auth::user();
$talkNotifications=[];
$talkUnread=0;
if($currentUser && Access::can('talk.access')){
    $notificationService=new TalkNotificationService();
    $talkNotifications=$notificationService->recent((int)$currentUser->id,8);
    $talkUnread=$notificationService->unreadCount((int)$currentUser->id);
}
?>
<header class="customer-topbar">
<div class="customer-title"><button class="customer-menu-toggle" type="button" aria-expanded="false" aria-controls="customer-sidebar"><span aria-hidden="true">☰</span><span class="sr-only">Abrir menu</span></button><div><span><?= Access::can('talk.access') && ($title??'')==='Talk' ? 'Moves · Atendimento' : 'Área do Cliente' ?></span><h1><?= $this->e($title) ?></h1></div></div>
<div class="customer-actions">
<?php if(Access::can('talk.access')): ?><div class="customer-popover-wrap"><button class="customer-icon-button" type="button" data-popover-button="talk-notifications" aria-expanded="false" aria-controls="talk-notifications" aria-label="<?= $talkUnread===1?'1 notificação não lida':$talkUnread.' notificações não lidas' ?>"><span aria-hidden="true">🔔</span><span class="customer-notification-count" data-talk-notification-count<?= $talkUnread===0?' hidden':'' ?>><?= $talkUnread>99?'99+':$talkUnread ?></span></button><div class="customer-popover customer-notification-popover" id="talk-notifications" data-popover hidden><header><strong>Notificações</strong><?php if($talkUnread>0): ?><form method="post" action="/talk/notifications/read"><?= $this->csrf() ?><input type="hidden" name="return_to" value="/talk"><button type="submit">Marcar todas como lidas</button></form><?php endif; ?></header><?php if(!$talkNotifications): ?><p class="customer-popover-empty">Nenhuma notificação.</p><?php else: foreach($talkNotifications as $notification): ?><article class="<?= empty($notification['read_at'])?'is-unread':'' ?>"><a href="<?= !empty($notification['ticket_id'])?'/talk/tickets/'.(int)$notification['ticket_id']:'/talk' ?>"><strong><?= $this->e((string)$notification['title']) ?></strong><?php if(!empty($notification['body'])): ?><small><?= $this->e((string)$notification['body']) ?></small><?php endif; ?></a><?php if(empty($notification['read_at'])): ?><form method="post" action="/talk/notifications/read"><?= $this->csrf() ?><input type="hidden" name="notification_id" value="<?= (int)$notification['id'] ?>"><input type="hidden" name="return_to" value="<?= !empty($notification['ticket_id'])?'/talk/tickets/'.(int)$notification['ticket_id']:'/talk' ?>"><button type="submit">Marcar lida</button></form><?php endif; ?></article><?php endforeach; endif; ?></div></div><?php endif; ?>
<span class="customer-live" data-live-status role="status"><i aria-hidden="true"></i><span>Conectado</span></span>
<?php if($currentUser):?><div class="customer-popover-wrap"><button class="customer-user customer-user-button" type="button" data-popover-button="customer-user-menu" aria-expanded="false" aria-controls="customer-user-menu"><span aria-hidden="true"><?= $this->e(strtoupper(substr((string)$currentUser->name,0,1))) ?></span><?= $this->e($currentUser->name) ?></button><div class="customer-popover customer-user-popover" id="customer-user-menu" data-popover hidden><a href="/app/profile">Meu perfil</a><?php if(Access::can('settings.manage')): ?><a href="/studio/settings">Configurações</a><?php endif; ?><form method="post" action="/logout"><?= $this->csrf() ?><button type="submit">Sair</button></form></div></div><?php endif;?>
</div></header>