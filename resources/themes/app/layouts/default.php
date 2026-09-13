<?php

/**
 * Moves | Customer Area Layout
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

    <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
    >

    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#ffffff">
    <title><?= $this->e($title ?? 'Início') ?> — Área do Cliente</title>
    <link rel="icon" href="/themes/site/images/brand/favicon.png" type="image/png">

    <link
            rel="stylesheet"
            href="<?= $this->e($this->asset('css/app.css')) ?>"
    >
    <link rel="stylesheet" href="<?= $this->e($this->asset('css/live.css')) ?>">
</head>

<body class="customer-body">
<a class="customer-skip-link" href="#main">Pular para o conteúdo</a>
<div class="customer-shell">
    <?php $this->insert('components/sidebar', ['currentPage' => $currentPage ?? null]); ?>
    <div class="customer-workspace">
        <?php $this->insert('components/topbar', ['title' => $title ?? 'Início']); ?>
        <?php $this->insert('components/flash'); ?>
        <main id="main" class="customer-content" tabindex="-1"><?= $this->section('content') ?></main>
    </div>
</div>
<script src="<?= $this->e($this->asset('js/app.js')) ?>" defer></script>
</body>
</html>
