<?php

/**
 * Moves Platform | Product Navigation
 */

$activeProduct = $activeProduct ?? 'cms';
$currentPage   = $currentPage ?? 'dashboard';

if ($activeProduct === 'support') {
    $ariaLabel = 'Navegação do Suporte';

    $groups = [
        'Principal' => [
            [
                'label' => 'Visão geral',
                'icon'  => 'icon-grid-outline',
                'href'  => '/support',
                'key'   => 'dashboard',
            ],
        ],

        'Base de conhecimento' => [
            [
                'label' => 'Artigos',
                'icon'  => 'icon-newspaper-outline',
                'href'  => '/support/articles',
                'key'   => 'articles',
            ],
            [
                'label' => 'Categorias',
                'icon'  => 'icon-folder-outline',
                'href'  => '/support/categories',
                'key'   => 'categories',
            ],
            [
                'label' => 'Produtos',
                'icon'  => 'icon-cube-outline',
                'href'  => '/support/products',
                'key'   => 'products',
            ],
              [
                  'label' => 'Tags',
                  'icon'  => 'icon-pricetag-outline',
                  'href'  => '/support/tags',
                  'key'   => 'tags',
              ],
            [
                'label' => 'Rascunhos',
                'icon'  => 'icon-document-text-outline',
                'href'  => '/support/drafts',
                'key'   => 'drafts',
            ],
            [
                'label' => 'Revisões',
                'icon'  => 'icon-time-outline',
                'href'  => '/support/revisions',
                'key'   => 'revisions',
            ],
            [
                'label' => 'Lixeira',
                'icon'  => 'icon-trash-outline',
                'href'  => '/support/trash',
                'key'   => 'trash',
            ],
        ],

        'Atendimento' => [
            [
                'label' => 'Caixa de entrada',
                'icon'  => 'icon-mail-outline',
                'href'  => '/support/inbox',
                'key'   => 'inbox',
            ],
            [
                'label' => 'Meus chamados',
                'icon'  => 'icon-headset-outline',
                'href'  => '/support/my-tickets',
                'key'   => 'my-tickets',
            ],
            [
                'label' => 'Todos',
                'icon'  => 'icon-chatbubbles-outline',
                'href'  => '/support/tickets',
                'key'   => 'tickets',
            ],
            [
                'label' => 'SLA',
                'icon'  => 'icon-timer-outline',
                'href'  => '/support/sla',
                'key'   => 'sla',
            ],
        ],

        'Gestão' => [
            [
                'label' => 'Usuários',
                'icon'  => 'icon-people-outline',
                'href'  => '/support/users',
                'key'   => 'users',
            ],
            [
                'label' => 'Relatórios',
                'icon'  => 'icon-stats-chart-outline',
                'href'  => '/support/reports',
                'key'   => 'reports',
            ],
            [
                'label' => 'Configurações',
                'icon'  => 'icon-settings-outline',
                'href'  => '/support/settings',
                'key'   => 'settings',
            ],
        ],
    ];
} elseif ($activeProduct === 'talk') {
    $ariaLabel = 'Navegação do Talk';

    $groups = [
        'Principal' => [
            ['label' => 'Visão geral', 'icon' => 'icon-grid-outline', 'href' => '/talk', 'key' => 'dashboard'],
            ['label' => 'Fila', 'icon' => 'icon-time-outline', 'href' => '/talk/queue', 'key' => 'queue'],
            ['label' => 'Conversas', 'icon' => 'icon-chatbubbles-outline', 'href' => '/talk/conversations', 'key' => 'conversations'],
            ['label' => 'Contatos', 'icon' => 'icon-people-outline', 'href' => '/talk/contacts', 'key' => 'contacts'],
        ],
        'Atendimento' => [
            ['label' => 'Meus chamados', 'icon' => 'icon-headset-outline', 'href' => '/talk/my-tickets', 'key' => 'my-tickets'],
            ['label' => 'Transferências', 'icon' => 'icon-swap-horizontal-outline', 'href' => '/talk/transfers', 'key' => 'transfers'],
            ['label' => 'Histórico', 'icon' => 'icon-time-outline', 'href' => '/talk/history', 'key' => 'history'],
        ],
        'Jack' => [
            ['label' => 'Interações', 'icon' => 'icon-sparkles-outline', 'href' => '/talk/jack', 'key' => 'jack'],
            ['label' => 'Configuração', 'icon' => 'icon-settings-outline', 'href' => '/talk/jack/settings', 'key' => 'jack-settings'],
        ],
        'Gestão' => [
            ['label' => 'Filas e departamentos', 'icon' => 'icon-git-branch-outline', 'href' => '/talk/queues', 'key' => 'queues'],
            ['label' => 'Usuários e permissões', 'icon' => 'icon-people-outline', 'href' => '/talk/users', 'key' => 'users'],
            ['label' => 'Relatórios', 'icon' => 'icon-stats-chart-outline', 'href' => '/talk/reports', 'key' => 'reports'],
            ['label' => 'Configurações', 'icon' => 'icon-settings-outline', 'href' => '/talk/settings', 'key' => 'settings'],
        ],
    ];
} else {
    $ariaLabel = 'Navegação do CMS';

    $groups = [
        'Visão geral' => [
            ['label' => 'Dashboard', 'icon' => 'icon-grid-outline', 'href' => '/studio', 'key' => 'dashboard'],
            ['label' => 'Criar', 'icon' => 'icon-add-outline', 'href' => '/studio/create', 'key' => 'create'],
            ['label' => 'Relatórios', 'icon' => 'icon-stats-chart-outline', 'href' => '/studio/reports', 'key' => 'reports'],
            ['label' => 'Notificações', 'icon' => 'icon-notifications-outline', 'href' => '/studio/notifications', 'key' => 'notifications'],
            ['label' => 'Propostas', 'icon' => 'icon-document-text-outline', 'href' => '/studio/proposals', 'key' => 'proposals'],
        ],

        'Conteúdo' => [
            ['label' => 'Páginas', 'icon' => 'icon-copy-outline', 'href' => '/studio/pages', 'key' => 'pages'],
            ['label' => 'Projetos', 'icon' => 'icon-briefcase-outline', 'href' => '/studio/projects', 'key' => 'projects'],
            ['label' => 'Artigos', 'icon' => 'icon-newspaper-outline', 'href' => '/studio/articles', 'key' => 'articles'],
            ['label' => 'Mídia', 'icon' => 'icon-image-outline', 'href' => '/studio/media', 'key' => 'media'],
            ['label' => 'Destaques', 'icon' => 'icon-star-outline', 'href' => '/studio/highlights', 'key' => 'highlights'],
            ['label' => 'Depoimentos', 'icon' => 'icon-chatbubble-outline', 'href' => '/studio/testimonials', 'key' => 'testimonials'],
            ['label' => 'FAQ', 'icon' => 'icon-help-circle-outline', 'href' => '/studio/faq', 'key' => 'faq'],
            ['label' => 'Categorias', 'icon' => 'icon-folder-outline', 'href' => '/studio/categories', 'key' => 'categories'],
            ['label' => 'Tags', 'icon' => 'icon-pricetag-outline', 'href' => '/studio/tags', 'key' => 'tags'],
            ['label' => 'Menus', 'icon' => 'icon-menu-outline', 'href' => '/studio/menus', 'key' => 'menus'],
            ['label' => 'Lixeira', 'icon' => 'icon-trash-outline', 'href' => '/studio/trash', 'key' => 'trash'],
        ],

        'Gestão' => [
            ['label' => 'Usuários', 'icon' => 'icon-people-outline', 'href' => '/studio/users', 'key' => 'users'],
            ['label' => 'Configurações', 'icon' => 'icon-settings-outline', 'href' => '/studio/settings', 'key' => 'settings'],
            ['label' => 'Versões', 'icon' => 'icon-pricetag-outline', 'href' => '/studio/versions', 'key' => 'versions'],
            ['label' => 'Log', 'icon' => 'icon-bug-outline', 'href' => '/studio/logs', 'key' => 'logs'],
        ],
    ];
}

?>

<nav class="product-nav" aria-label="<?= $this->e($ariaLabel) ?>">

    <?php foreach ($groups as $group => $items): ?>

        <div class="nav-section">
            <?= $this->e($group) ?>
        </div>

        <?php foreach ($items as $item): ?>
            <?php $active = $currentPage === $item['key']; ?>

            <a
                href="<?= $this->e($item['href']) ?>"
                class="<?= $active ? 'active' : '' ?>"
                <?= $active ? 'aria-current="page"' : '' ?>
            >
                <i class="<?= $this->e($item['icon']) ?>"></i>
                <span><?= $this->e($item['label']) ?></span>
            </a>

        <?php endforeach; ?>

    <?php endforeach; ?>

</nav>
