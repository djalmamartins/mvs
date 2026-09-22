<?php
use Moves\Core\Access;

/** Moves | Global rail + contextual navigation. */
$apps = [
    ['label'=>'Meu Dia','href'=>'/app','show'=>true],
    ['label'=>'Talk','href'=>'/talk','show'=>Access::can('talk.access')],
    ['label'=>'Studio','href'=>'/studio','show'=>Access::can('studio.dashboard')],
];
$talk = ($currentPage ?? null) === 'talk';
?>
<div class="moves-global-rail" aria-label="Aplicativos Moves">
    <button class="moves-app-launcher" type="button" aria-expanded="false" aria-controls="moves-app-menu" aria-label="Abrir aplicativos Moves"><span aria-hidden="true" class="moves-nine-dots"><?php for($i=0;$i<9;$i++): ?><i></i><?php endfor; ?></span></button>
    <div class="moves-app-menu" id="moves-app-menu" hidden role="menu">
        <?php foreach($apps as $app): if(!$app['show']) continue; ?><a role="menuitem" href="<?= $this->e($app['href']) ?>"><?= $this->e($app['label']) ?></a><?php endforeach; ?>
    </div>
</div>
<aside class="customer-sidebar" id="customer-sidebar" aria-label="<?= $talk ? 'Navegação do Talk' : 'Navegação da Área do Cliente' ?>">
    <div class="customer-brand"><a href="<?= $talk ? '/talk' : '/app' ?>"><img src="/themes/site/images/brand/moves-logo.svg" alt="MOVES" width="116" height="17"></a><button class="customer-nav-collapse" type="button" aria-expanded="true" aria-controls="customer-sidebar-nav" aria-label="Recolher navegação">‹</button></div>
    <nav id="customer-sidebar-nav">
    <?php if($talk): ?>
        <section class="customer-nav-group"><h2>Talk</h2><a href="/talk" class="active" aria-current="page"><span aria-hidden="true">◌</span><span>Conversas</span></a><a href="/talk/queue"><span aria-hidden="true">≋</span><span>Fila</span></a></section>
    <?php else: ?>
        <section class="customer-nav-group"><h2>Visão geral</h2><a href="/app"<?= ($currentPage??null)==='dashboard'?' class="active" aria-current="page"':'' ?>><span aria-hidden="true">⌂</span><span>Meu Dia</span></a></section>
        <section class="customer-nav-group"><h2>Conta</h2><a href="/app/profile"<?= ($currentPage??null)==='profile'?' class="active" aria-current="page"':'' ?>><span aria-hidden="true">○</span><span>Meu perfil</span></a></section>
    <?php endif; ?>
    </nav>
</aside><button class="customer-backdrop" type="button" aria-label="Fechar menu" tabindex="-1"></button>
