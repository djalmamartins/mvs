<?php

/**
 * Moves | Studio Layout
 *
 * Define a estrutura HTML principal do tema padrão.
 *
 * @author Djalma Martins
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#ffffff">
    <title><?= $this->e($title ?? 'Dashboard') ?> — Moves Studio</title>
    <link rel="icon" href="/themes/admin/images/favicon.png" type="image/png">
    <link rel="stylesheet" href="<?= $this->e($this->asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= $this->e($this->asset('css/studio-reference.css')) ?>">
    <link rel="stylesheet" href="<?= $this->e($this->asset('css/compat.css')) ?>">
    <script src="<?= $this->e($this->asset('js/app.js')) ?>" defer></script>
</head>

<body class="studio-body studio-v2">
<a class="studio-skip-link" href="#main-content">Ir para o conteúdo</a>
<div class="studio-shell">
    <?php $this->insert('components/sidebar', ['currentPage' => $currentPage ?? null]); ?>
    <button class="studio-sidebar-backdrop" type="button" aria-label="Fechar menu"></button>
    <div class="studio-main">
        <?php $this->insert('components/topbar', ['title' => $title ?? 'Dashboard']); ?>
        <?php $this->insert('components/flash'); ?>
        <main class="studio-content" id="main-content" tabindex="-1">
            <?= $this->section('content') ?>
        </main>
        <footer class="studio-footer"><span>Copyright © <?= date('Y') ?> MovesOS. Todos os direitos reservados.</span><a class="studio-footer-version" href="/admin/versions">Studio v1.0.0</a></footer>
    </div>
</div>
</body>
</html>
