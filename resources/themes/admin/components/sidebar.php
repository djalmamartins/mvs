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
    <a class="studio-brand-v2" href="/admin"><img src="/themes/admin/images/studio-logo.svg" alt=""><span>MOVES<small>OS</small></span></a>
    <nav class="studio-nav-v2">
        <?php foreach ($groups as $group => $items): ?>
            <small><?= $this->e(mb_strtoupper($group, 'UTF-8')) ?></small>
            <?php foreach ($items as $item): ?>
                <a href="<?= $this->e($item['href']) ?>"<?= ($currentPage ?? null) === $item['key'] ? ' class="active" aria-current="page"' : '' ?> title="<?= $this->e($item['label']) ?>"><i class="studio-nav-icon" aria-hidden="true"><?= $this->e($item['icon']) ?></i><span><?= $this->e($item['label']) ?></span></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <div class="studio-sidebar-footer"><a href="/" target="_blank" rel="noopener"><i aria-hidden="true">↗</i><span>Visualizar site</span></a></div>
</aside>
