<?php

use Moves\Core\Auth;
use Moves\Core\NotificationCounter;
use Moves\Boot\Connection;
use Moves\Services\Platform\ProductEntitlement;
use Moves\Services\Platform\TenantContext;

/**
 * Moves Platform | Application Shell
 *
 * Shell global da plataforma Moves.
 */

$user = Auth::user();

$unreadNotifications = $user
    ? NotificationCounter::unreadFor((int) $user->id)
    : 0;
$talkUnreadNotifications = 0;
if ($user !== null && ($activeProduct ?? '') === 'talk') {
    $talkUnreadNotifications = (new \Moves\Services\Talk\TalkNotificationService())->unreadCount((int) $user->id);
}

$activeProduct = $activeProduct ?? 'studio';
$productName   = $productName ?? 'Studio';
$availableTenants = [];
$currentTenantId = null;
$enabledProducts = array_fill_keys(ProductEntitlement::PRODUCTS, true);
if ($user !== null) {
    try {
        $pdo = Connection::getInstance();
        $tenantContext = new TenantContext($pdo);
        $currentTenantId = $tenantContext->currentId((int) $user->id);
        $availableTenants = $tenantContext->available((int) $user->id);
        $enabledProducts = (new ProductEntitlement($pdo))->all($currentTenantId);
    } catch (Throwable) {
        $availableTenants = [];
    }
}

$userName = $user !== null
    ? (string) $user->name
    : 'Usuário';

$userInitials = '';

foreach (preg_split('/\s+/', trim($userName)) ?: [] as $namePart) {
    if ($namePart !== '') {
        $userInitials .= mb_strtoupper(
            mb_substr($namePart, 0, 1, 'UTF-8'),
            'UTF-8'
        );
    }

    if (mb_strlen($userInitials, 'UTF-8') >= 2) {
        break;
    }
}

$userInitials = $userInitials !== '' ? $userInitials : 'M';

$apps = [
    [
        'key'   => 'day',
        'label' => 'Meu Dia',
        'icon'  => 'icon-sunny-outline',
        'href'  => '/day',
    ],
    [
        'key'   => 'talk',
        'label' => 'Talk',
        'icon'  => 'icon-talk',
        'href'  => '/talk',
    ],
    [
        'key'   => 'support',
        'label' => 'Support',
        'icon'  => 'icon-helpdesk',
        'href'  => '/support',
    ],
    [
        'key'   => 'erp',
        'label' => 'ERP',
        'icon'  => 'icon-business',
        'href'  => '/erp',
    ],
    [
        'key'   => 'studio',
        'label' => 'Studio',
        'icon'  => 'icon-flask-outline',
        'href'  => '/studio',
    ],
];
$apps = array_values(array_filter($apps, static fn (array $app): bool => $app['key'] === 'day' || ($enabledProducts[$app['key']] ?? false)));

?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#ffffff">

    <title><?= $this->e($title ?? 'Dashboard') ?> — Moves</title>

    <!-- Moves Application Shell — CSS oficial -->
    <link
            rel="stylesheet"
            href="<?= $this->e($this->asset('css/moves-fonts.css')) ?>"
    >

    <link
            rel="stylesheet"
            href="<?= $this->e($this->asset('css/moves-icons.css')) ?>"
    >

    <link
            rel="stylesheet"
            href="<?= $this->e($this->asset('css/application-shell.css')) ?>"
    >

    <link
            rel="stylesheet"
            href="<?= $this->e($this->asset('css/moves-sidebar.css')) ?>"
    >

    <link
            rel="stylesheet"
            href="<?= $this->e($this->asset('css/moves-form.css')) ?>"
    >
    <link rel="stylesheet" href="<?= $this->e($this->asset('css/platform.css')) ?>">
    <?php if (($activeProduct ?? '') === 'talk'): ?>
        <link rel="stylesheet" href="<?= $this->e($this->asset('css/talk.css')) ?>">
    <?php endif; ?>
    <?php if (($activeProduct ?? '') === 'erp'): ?>
        <link rel="stylesheet" href="<?= $this->e($this->asset('css/erp.css')) ?>">
    <?php endif; ?>

    <?php if (!empty($hasMovesEditor)): ?>
        <link
                rel="stylesheet"
                href="<?= $this->e($this->asset('css/moves-editor.css')) ?>"
        >
        <link
                rel="stylesheet"
                href="<?= $this->e($this->asset('css/moves-editor-adapter.css')) ?>"
        >
    <?php endif; ?>

    <script
            src="<?= $this->e($this->asset('js/app.js')) ?>"
            defer
    ></script>

    <script
            src="<?= $this->e($this->asset('js/application-shell.js')) ?>"
            defer
    ></script>
    <?php if (($activeProduct ?? '') === 'talk'): ?>
        <script src="<?= $this->e($this->asset('js/talk.js')) ?>" defer></script>
    <?php endif; ?>

    <?php if (!empty($hasMovesEditor)): ?>
        <script
                src="<?= $this->e($this->asset('js/moves-editor.js')) ?>"
                defer
        ></script>
        <script
                src="<?= $this->e($this->asset('js/moves-editor-adapter.js')) ?>"
                defer
        ></script>
        <script
                src="<?= $this->e($this->asset('js/cms-editor.js')) ?>"
                defer
        ></script>
    <?php endif; ?>
</head>

<body
        class="studio-body studio-v2 moves-platform"
        data-editor-upload="/studio/media"
        data-editor-library="/studio/media/library"
        data-editor-user="<?= (int) ($user?->id ?? 0) ?>"
>

<a class="studio-skip-link" href="#main-content">
    Ir para o conteúdo
</a>

<div class="app-shell">

    <aside class="app-rail" aria-label="Aplicativos Moves">

        <button
                class="moves-logo"
                id="launcherBtn"
                type="button"
                aria-label="Abrir aplicativos Moves"
                title="Aplicativos"
        >
            <img
                    src="<?= $this->e($this->asset('images/moves-favicon.svg')) ?>"
                    alt="Moves"
            >
        </button>

        <nav class="rail-apps" aria-label="Aplicativos Moves">

            <?php foreach ($apps as $app): ?>
                <?php $isActive = $activeProduct === $app['key']; ?>

                <a
                        class="rail-app<?= $isActive ? ' active' : '' ?>"
                        href="<?= $this->e($app['href']) ?>"
                        title="<?= $this->e($app['label']) ?>"
                        data-app="<?= $this->e($app['key']) ?>"
                    <?= $isActive ? 'aria-current="page"' : '' ?>
                >
                    <i class="<?= $this->e($app['icon']) ?>"></i>
                    <span><?= $this->e($app['label']) ?></span>
                </a>
            <?php endforeach; ?>

        </nav>

        <div class="rail-bottom">

            <a
                    class="rail-app"
                    href="/studio/settings"
                    title="Configurações"
            >
                <i class="icon-settings-outline"></i>
                <span>Configurações</span>
            </a>

            <a
                    class="rail-avatar"
                    href="/profile"
                    title="<?= $this->e($userName) ?>"
                    aria-label="Perfil de <?= $this->e($userName) ?>"
            >
                <?= $this->e($userInitials) ?>
            </a>

        </div>

    </aside>

    <aside
            class="product-sidebar"
            id="productSidebar"
            aria-label="Navegação do produto"
    >
        <header class="product-head">

            <div>
                <span class="product-eyebrow">MOVES</span>
                <strong id="productName">
                    <?= $this->e($productName) ?>
                </strong>
            </div>

            <button
                    class="icon-btn"
                    id="collapseBtn"
                    type="button"
                    title="Recolher menu"
                    aria-label="Recolher menu"
            >
                <i class="icon-chevron-back"></i>
            </button>

        </header>

        <?php $this->insert('components/product-sidebar', [
            'activeProduct' => $activeProduct,
            'currentPage'   => $currentPage ?? null,
        ]); ?>

    </aside>

    <main class="workspace">

        <header class="topbar">

            <button
                    class="icon-btn sidebar-open"
                    id="openSidebar"
                    type="button"
                    title="Abrir menu"
                    aria-label="Abrir menu"
            >
                <i class="icon-apps"></i>
            </button>

            <div class="breadcrumbs">
                <span><?= $this->e($productName) ?></span>
                <i class="icon-chevron-forward"></i>
                <strong><?= $this->e($title ?? 'Dashboard') ?></strong>
            </div>

            <div class="top-actions">

                <?php if (count($availableTenants) > 1): ?>
                    <form method="post" action="/tenant/switch" class="tenant-switcher">
                        <?= $this->csrf() ?>
                        <label><span class="sr-only">Administradora ativa</span><select name="tenant_id" onchange="this.form.submit()">
                            <?php foreach ($availableTenants as $tenant): ?><option value="<?= $tenant['id'] ?>" <?= $tenant['id']===$currentTenantId?'selected':'' ?>><?= $this->e($tenant['name']) ?></option><?php endforeach; ?>
                        </select></label>
                    </form>
                <?php endif; ?>

                <form
                        class="search"
                        method="get"
                        action="/studio/search"
                        role="search"
                >
                    <i class="icon-search-outline"></i>

                    <input
                            type="search"
                            name="q"
                            minlength="2"
                            maxlength="100"
                            placeholder="Buscar no Moves"
                            aria-label="Buscar no Moves"
                    >
                </form>

                <a
                        class="icon-btn"
                        href="<?= $activeProduct === 'talk' ? '/talk/notifications' : '/studio/notifications' ?>"
                        title="Notificações"
                        aria-label="Notificações<?= ($activeProduct === 'talk' ? $talkUnreadNotifications : $unreadNotifications) ? ': ' . ($activeProduct === 'talk' ? $talkUnreadNotifications : $unreadNotifications) . ' não lidas' : '' ?>"
                >
                    <i class="icon-notifications-outline"></i>
                    <?php if ($activeProduct === 'talk'): ?><span data-talk-notification-count<?= $talkUnreadNotifications ? '' : ' hidden' ?>><?= $talkUnreadNotifications ?: '' ?></span><?php endif; ?>
                </a>

                <?php if ($user !== null): ?>
                    <div class="platform-user-menu"><button
                            class="user-chip"
                            data-user-menu-trigger
                            type="button"
                            aria-expanded="false"
                            title="<?= $this->e($userName) ?>"
                    >
                        <span class="mini-avatar">
                            <?= $this->e($userInitials) ?>
                        </span>

                        <span><?= $this->e($userName) ?></span>

                        <i class="icon-chevron-down"></i>
                    </button><div class="platform-user-dropdown" data-user-menu hidden>
                        <strong><?= $this->e($userName) ?></strong><small><?= $this->e((string)$user->email) ?></small>
                        <a href="/profile">Meu perfil</a><a href="/settings">Administradora e produtos</a><a href="/studio/settings">Preferências do site</a>
                        <form method="post" action="/logout"><?= $this->csrf() ?><button type="submit">Sair</button></form>
                    </div></div>
                <?php endif; ?>

            </div>

        </header>

        <?php $this->insert('components/flash'); ?>

        <section
                class="page studio-page"
                id="main-content"
                tabindex="-1"
        >
            <?= $this->section('content') ?>
        </section>

        <footer class="workspace-footer">
            <span>Copyright © <?= date('Y') ?> Moves. Todos os direitos reservados.</span>
            <span>Moves 0.0.1</span>
        </footer>

    </main>

</div>

<div
        class="launcher"
        id="launcher"
        aria-hidden="true"
>
    <div class="launcher-head">

        <div>
            <span class="product-eyebrow">MOVES</span>
            <h2>Aplicativos</h2>
        </div>

        <button
                class="icon-btn"
                id="launcherClose"
                type="button"
                aria-label="Fechar aplicativos"
        >
            <i class="icon-close"></i>
        </button>

    </div>

    <label class="launcher-search">
        <i class="icon-search-outline"></i>

        <input
                id="appSearch"
                type="search"
                placeholder="Buscar aplicativos Moves"
        >
    </label>

    <div class="app-grid" id="appGrid">

        <?php foreach ($apps as $app): ?>
            <a
                    href="<?= $this->e($app['href']) ?>"
                    data-app="<?= $this->e($app['key']) ?>"
            >
                <i class="<?= $this->e($app['icon']) ?>"></i>
                <span><?= $this->e($app['label']) ?></span>
            </a>
        <?php endforeach; ?>

    </div>

    <div class="launcher-foot">
        <span>Moves Application Shell</span>
    </div>
</div>

<div class="overlay" id="overlay"></div>

</body>
</html>
