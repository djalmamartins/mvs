<?php

use Moves\Core\Auth;
use Moves\Core\NotificationCounter;

/** Moves Studio | Reusable top bar. */

$user = Auth::user();
$unreadNotifications = $user ? NotificationCounter::unreadFor((int) $user->id) : 0;
$recentNotifications = $user ? NotificationCounter::recentFor((int) $user->id) : [];
?>
<header class="studio-topbar studio-topbar-v2">
    <div class="studio-app-launcher">
        <button class="studio-app-launcher-trigger" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="studio-app-launcher-menu" aria-label="Abrir aplicativos Moves"><?= studio_icon('apps') ?></button>
        <div class="studio-app-launcher-menu" id="studio-app-launcher-menu" role="menu" aria-label="Aplicativos Moves">
            <header><strong>Moves</strong><small>Aplicativos</small></header>
            <nav>
                <a role="menuitem" href="/app"><?= studio_icon('calendar') ?><span>Meu Dia</span></a>
                <a role="menuitem" href="/talk"><?= studio_icon('message-square') ?><span>Talk</span></a>
                <a role="menuitem" href="/support"><?= studio_icon('headphones') ?><span>Suporte</span></a>
                <a role="menuitem" href="/erp"><?= studio_icon('briefcase') ?><span>ERP</span></a>
                <a role="menuitem" href="/studio" aria-current="page"><?= studio_icon('sparkles') ?><span>Studio</span></a>
            </nav>
        </div>
    </div>
    <button class="studio-menu" type="button" aria-expanded="true" aria-controls="studio-sidebar" aria-label="Recolher ou expandir menu do produto"><?= studio_icon('chevron-down') ?></button>
    <div class="studio-topbar-context"><small>Moves Studio</small><strong><?= $this->e($title) ?></strong></div>
    <form class="studio-global-search" method="get" action="/studio/search" role="search">
        <label class="sr-only" for="studio-global-search">Buscar no Studio</label>
        <?= studio_icon('search') ?>
        <input id="studio-global-search" type="search" name="q" minlength="2" maxlength="100" placeholder="Buscar conteúdo, usuário ou proposta">
    </form>
    <nav class="studio-header-actions" aria-label="Atalhos do painel">
        <button class="studio-theme-toggle" type="button" title="Alternar tema" aria-label="Ativar modo escuro" aria-pressed="false"><?= studio_icon('moon') ?></button>
        <a href="/studio/diagnostics" title="Ajuda e diagnóstico" aria-label="Ajuda e diagnóstico"><?= studio_icon('circle-help') ?></a>
        <a href="/" target="_blank" rel="noopener" title="Visualizar site" aria-label="Visualizar site"><?= studio_icon('globe') ?></a>
        <div class="studio-notifications">
            <button class="studio-notification-toggle" type="button" aria-haspopup="dialog" aria-expanded="false" aria-controls="studio-notification-panel" title="Notificações" aria-label="Abrir notificações<?= $unreadNotifications ? ': '.$unreadNotifications.' não lidas' : '' ?>"><?= studio_icon('bell') ?><?php if ($unreadNotifications): ?><small><?= min(99,$unreadNotifications) ?></small><?php endif; ?></button>
            <section class="studio-notification-panel" id="studio-notification-panel" role="dialog" aria-label="Notificações">
                <header><div><strong>Notificações</strong><small><?= $unreadNotifications ? $unreadNotifications.' não lidas' : 'Tudo em dia' ?></small></div><a href="/studio/notifications">Ver todas</a></header>
                <?php if ($recentNotifications): ?>
                    <div class="studio-notification-list">
                        <?php foreach ($recentNotifications as $notification): ?>
                            <?php $notificationUrl = (string) ($notification['action_url'] ?: $notification['link'] ?: '/studio/notifications'); ?>
                            <a class="<?= empty($notification['read_at']) ? 'unread' : '' ?>" href="<?= $this->e(str_starts_with($notificationUrl, '/') ? $notificationUrl : '/studio/notifications') ?>">
                                <span aria-hidden="true"><?= studio_icon('bell') ?></span>
                                <span><strong><?= $this->e((string) $notification['title']) ?></strong><small><?= $this->e(mb_strimwidth((string) $notification['message'], 0, 105, '…')) ?></small></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="studio-notification-empty"><span aria-hidden="true"><?= studio_icon('bell') ?></span><strong>Nenhuma notificação nova</strong><small>Novas atualizações aparecerão aqui.</small></div>
                <?php endif; ?>
            </section>
        </div>
        <?php if ($user !== null): ?><div class="studio-header-profile"><button class="studio-profile-trigger" type="button" aria-haspopup="menu" aria-expanded="false"><span class="studio-profile-avatar" aria-hidden="true"><?= $this->e(strtoupper(substr((string) $user->name, 0, 1))) ?></span><span><strong><?= $this->e((string) $user->name) ?></strong></span><?= studio_icon('chevron-down') ?></button><div class="studio-profile-menu" role="menu"><header><span class="studio-profile-avatar" aria-hidden="true"><?= $this->e(strtoupper(substr((string) $user->name, 0, 1))) ?></span><strong><?= $this->e((string) $user->name) ?></strong><small><?= $this->e((string) $user->email) ?></small></header><nav><a role="menuitem" href="/studio/users"><span>Perfil e usuários<small>Conta, dados e permissões</small></span></a><a role="menuitem" href="/studio/settings"><span>Configurações<small>Preferências da plataforma</small></span></a><a role="menuitem" href="/studio/settings#security"><span>Segurança<small>Senha e acesso</small></span></a></nav><footer><form method="post" action="/logout"><?= $this->csrf() ?><button type="submit">Sair</button></form><small>ID #<?= $this->e((string) $user->id) ?></small></footer></div></div><?php endif; ?>
    </nav>
</header>
