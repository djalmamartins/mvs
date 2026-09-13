<?php

/** Moves Studio | Primary navigation. */

$groups = [
    'Visão geral' => [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => '/studio', 'key' => 'dashboard'],
        ['label' => 'Relatórios', 'icon' => 'chart', 'href' => '/studio/reports', 'key' => 'reports'],
        ['label' => 'Notificações', 'icon' => 'bell', 'href' => '/studio/notifications', 'key' => 'notifications'],
        ['label' => 'Propostas', 'icon' => 'file-text', 'href' => '/studio/proposals', 'key' => 'proposals'],
    ],
    'Conteúdo' => [
        ['label' => 'Páginas', 'icon' => 'copy', 'href' => '/studio/pages', 'key' => 'pages'],
        ['label' => 'Projetos', 'icon' => 'briefcase', 'href' => '/studio/projects', 'key' => 'projects'],
        ['label' => 'Artigos', 'icon' => 'newspaper', 'href' => '/studio/articles', 'key' => 'articles'],
        ['label' => 'Mídia', 'icon' => 'image', 'href' => '/studio/media', 'key' => 'media'],
        ['label' => 'Destaques', 'icon' => 'archive', 'href' => '/studio/highlights', 'key' => 'highlights'],
        ['label' => 'Depoimentos', 'icon' => 'message-square', 'href' => '/studio/testimonials', 'key' => 'testimonials'],
        ['label' => 'FAQ', 'icon' => 'circle-help', 'href' => '/studio/faq', 'key' => 'faq'],
    ],
    'Gestão' => [
        ['label' => 'Usuários', 'icon' => 'users', 'href' => '/studio/users', 'key' => 'users'],
        ['label' => 'Configurações', 'icon' => 'settings', 'href' => '/studio/settings', 'key' => 'settings'],
        ['label' => 'Versões', 'icon' => 'tag', 'href' => '/studio/versions', 'key' => 'versions'],
        ['label' => 'Log', 'icon' => 'bug', 'href' => '/studio/logs', 'key' => 'logs'],
    ],
];
?>
<aside class="studio-sidebar" id="studio-sidebar" aria-label="Navegação do Moves Studio">
    <a class="studio-brand-v2" href="/studio"><img src="/themes/admin/images/studio-logo.svg" alt=""><span><strong>MOVES</strong><small>Studio</small></span><?= studio_icon('chevron-down', 'studio-brand-chevron') ?></a>
    <nav class="studio-nav-v2">
        <?php foreach ($groups as $group => $items): ?>
            <small><?= $this->e(mb_strtoupper($group, 'UTF-8')) ?></small>
            <?php foreach ($items as $item): ?>
                <a href="<?= $this->e($item['href']) ?>"<?= ($currentPage ?? null) === $item['key'] ? ' class="active" aria-current="page"' : '' ?> title="<?= $this->e($item['label']) ?>"><i class="studio-nav-icon" aria-hidden="true"><?= studio_icon($item['icon']) ?></i><span><?= $this->e($item['label']) ?></span></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <div class="studio-sidebar-footer"><a href="/" target="_blank" rel="noopener"><?= studio_icon('external-link') ?><span>Visualizar site</span></a><form method="post" action="/logout"><?= $this->csrf() ?><button type="submit"><?= studio_icon('log-out') ?><span>Sair</span></button></form></div>
</aside>
