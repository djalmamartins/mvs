<?php

/** Moves Studio | Primary navigation. */

$groups = [
    'Visão geral' => [
        ['label' => 'Dashboard', 'icon' => '⌂', 'href' => '/admin', 'key' => 'dashboard'],
        ['label' => 'Relatórios', 'icon' => '↗'],
        ['label' => 'Notificações', 'icon' => '•'],
        ['label' => 'Propostas', 'icon' => '◇'],
    ],
    'Conteúdo' => [
        ['label' => 'Páginas', 'icon' => '▤'],
        ['label' => 'Artigos', 'icon' => '¶'],
        ['label' => 'Mídia', 'icon' => '▧'],
        ['label' => 'Destaques', 'icon' => '✦'],
        ['label' => 'Depoimentos', 'icon' => '“'],
        ['label' => 'FAQ', 'icon' => '?'],
    ],
    'Gestão' => [
        ['label' => 'Usuários', 'icon' => '◎', 'href' => '/admin/users', 'key' => 'users'],
        ['label' => 'Configurações', 'icon' => '⚙', 'href' => '/admin/settings', 'key' => 'settings'],
        ['label' => 'Versões', 'icon' => '◫'],
        ['label' => 'Log', 'icon' => '≡'],
    ],
];
?>
<aside class="studio-sidebar" id="studio-sidebar" aria-label="Navegação do Moves Studio">
    <a class="studio-brand" href="/admin"><span class="studio-mark" aria-hidden="true">M</span><span>Moves <strong>Studio</strong></span></a>
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
