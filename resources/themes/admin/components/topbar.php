<?php

use Moves\Core\Auth;
use Moves\Core\NotificationCounter;

/** Moves Studio | Reusable top bar. */

$user = Auth::user();
$unreadNotifications = $user ? NotificationCounter::unreadFor((int) $user->id) : 0;
?>
<header class="studio-topbar studio-topbar-v2">
    <button class="studio-menu" type="button" aria-expanded="true" aria-controls="studio-sidebar" aria-label="Alternar menu"><span aria-hidden="true">☷</span></button>
    <form class="studio-global-search" action="/admin/search" method="get"><span aria-hidden="true">⌕</span><input type="search" name="q" value="<?= $this->e((string) ($_GET['q'] ?? '')) ?>" placeholder="Buscar conteúdo, usuários ou propostas..." aria-label="Buscar no Studio" autocomplete="off"></form>
    <nav class="studio-header-actions" aria-label="Atalhos do painel">
        <button class="studio-theme-toggle" type="button" title="Alternar tema" aria-label="Ativar modo escuro" aria-pressed="false"><span aria-hidden="true">◐</span></button>
        <a class="studio-notification-link" href="/admin/notifications" title="Notificações" aria-label="Abrir notificações<?= $unreadNotifications ? ': '.$unreadNotifications.' não lidas' : '' ?>"><span aria-hidden="true">◉</span><?php if($unreadNotifications):?><b><?= min(99,$unreadNotifications) ?></b><?php endif;?></a>
        <a href="/" target="_blank" rel="noopener" title="Visualizar site" aria-label="Visualizar site"><span aria-hidden="true">◎</span></a>
        <?php if ($user !== null): ?><div class="studio-header-profile" role="button" tabindex="0" aria-haspopup="menu" aria-expanded="false"><span class="studio-profile-avatar" aria-hidden="true"><?= $this->e(strtoupper(substr((string) $user->name, 0, 1))) ?></span><span><strong><?= $this->e((string) $user->name) ?></strong><small>Administrador</small></span><b aria-hidden="true">⌄</b><div class="studio-profile-menu" role="menu"><header><span class="studio-profile-avatar" aria-hidden="true"><?= $this->e(strtoupper(substr((string) $user->name, 0, 1))) ?></span><strong><?= $this->e((string) $user->name) ?></strong><small><?= $this->e((string) $user->email) ?></small></header><nav><a role="menuitem" href="/admin/users"><span>Usuários<small>Contas e permissões</small></span></a><a role="menuitem" href="/admin/settings"><span>Configurações<small>Preferências do MovesOS</small></span></a></nav><footer><form method="post" action="/logout"><?= $this->csrf() ?><button type="submit">Sair</button></form><small>ID #<?= $this->e((string) $user->id) ?></small></footer></div></div><?php endif; ?>
    </nav>
</header>
