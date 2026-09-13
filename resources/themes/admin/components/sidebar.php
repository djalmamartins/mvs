<?php

/** Moves Studio | Primary navigation. */

$groups = [
    'Visão geral' => [
        ['label' => 'Dashboard', 'icon' => '⌂', 'href' => '/admin', 'key' => 'dashboard'],
        ['label' => 'Relatórios', 'icon' => '↗', 'href' => '/admin/reports', 'key' => 'reports'],
        ['label' => 'Notificações', 'icon' => '•', 'href' => '/admin/notifications', 'key' => 'notifications'],
        ['label' => 'Propostas', 'icon' => '◇', 'href' => '/admin/proposals', 'key' => 'proposals'],
    ],
    'Conteúdo' => [
        ['label' => 'Páginas', 'icon' => '▤', 'href' => '/admin/pages', 'key' => 'pages'],
        ['label' => 'Artigos', 'icon' => '¶', 'href' => '/admin/articles', 'key' => 'articles'],
        ['label' => 'Mídia', 'icon' => '▧', 'href' => '/admin/media', 'key' => 'media'],
        ['label' => 'Destaques', 'icon' => '✦', 'href' => '/admin/highlights', 'key' => 'highlights'],
        ['label' => 'Depoimentos', 'icon' => '“', 'href' => '/admin/testimonials', 'key' => 'testimonials'],
        ['label' => 'FAQ', 'icon' => '?', 'href' => '/admin/faq', 'key' => 'faq'],
    ],
    'Gestão' => [
        ['label' => 'Usuários', 'icon' => '◎', 'href' => '/admin/users', 'key' => 'users'],
        ['label' => 'Configurações', 'icon' => '⚙', 'href' => '/admin/settings', 'key' => 'settings'],
        ['label' => 'Versões', 'icon' => '◫', 'href' => '/admin/versions', 'key' => 'versions'],
        ['label' => 'Log', 'icon' => '≡', 'href' => '/admin/logs', 'key' => 'logs'],
    ],
];
?>
<aside class="studio-sidebar" id="studio-sidebar" aria-label="Navegação do Moves Studio">
    <a class="studio-brand" href="/admin"><img src="/themes/site/images/brand/moves-logo.svg" alt="MOVES" width="116" height="17"><small>Studio</small></a>
    <nav>
        <?php foreach ($groups as $group => $items): ?>
            <section class="studio-nav-group" aria-labelledby="nav-<?= $this->e(strtolower(str_replace(' ', '-', $group))) ?>">
                <h2 id="nav-<?= $this->e(strtolower(str_replace(' ', '-', $group))) ?>"><?= $this->e($group) ?></h2>
                <?php foreach ($items as $item): ?>
                    <?php if (isset($item['href'])): ?>
                        <a href="<?= $this->e($item['href']) ?>"<?= ($currentPage ?? null) === $item['key'] ? ' class="active" aria-current="page"' : '' ?>><span aria-hidden="true"><?= $this->e($item['icon']) ?></span><?= $this->e($item['label']) ?></a>
                    <?php else: ?>
                        <span class="studio-nav-disabled" aria-disabled="true" title="Módulo ainda não iniciado"><span aria-hidden="true"><?= $this->e($item['icon']) ?></span><?= $this->e($item['label']) ?><small>Em breve</small></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </nav>
</aside>
<button class="studio-backdrop" type="button" aria-label="Fechar menu" tabindex="-1"></button>
