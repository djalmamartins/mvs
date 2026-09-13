<?php

use Moves\Core\Auth;

/** Moves Studio | Reusable top bar. */

$user = Auth::user();
?>
<header class="studio-topbar">
    <div class="studio-topbar-title">
        <button class="studio-menu-toggle" type="button" aria-expanded="false" aria-controls="studio-sidebar"><span aria-hidden="true">☰</span><span class="sr-only">Abrir menu</span></button>
        <div><span>Moves Studio</span><h1><?= $this->e($title) ?></h1></div>
    </div>
    <div class="studio-topbar-actions">
        <a href="/" target="_blank" rel="noopener">Visualizar site ↗</a>
        <?php if ($user !== null): ?><span class="studio-user"><span aria-hidden="true"><?= $this->e(strtoupper(substr((string) $user->name, 0, 1))) ?></span><?= $this->e((string) $user->name) ?></span><?php endif; ?>
        <form method="post" action="/logout"><?= $this->csrf() ?><button type="submit">Sair</button></form>
    </div>
</header>
