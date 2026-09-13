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
    <link rel="icon" href="/themes/site/images/brand/favicon.png" type="image/png">
    <link rel="stylesheet" href="<?= $this->e($this->asset('css/app.css')) ?>">
    <script src="<?= $this->e($this->asset('js/app.js')) ?>" defer></script>
</head>

<body class="studio-body">
<div class="studio-shell">
    <?php $this->insert('components/sidebar', ['currentPage' => $currentPage ?? null]); ?>
    <div class="studio-workspace">
        <?php $this->insert('components/topbar', ['title' => $title ?? 'Dashboard']); ?>
        <?php $this->insert('components/flash'); ?>
        <main class="studio-content">
            <?= $this->section('content') ?>
        </main>
    </div>
</div>
</body>
</html>
