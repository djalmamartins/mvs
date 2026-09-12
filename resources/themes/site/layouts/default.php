<?php

/**
 * Moves | Site Layout
 *
 * Define a estrutura HTML principal do tema público.
 *
 * @author Djalma Martins
 */
?>
<!DOCTYPE html>
<html lang="pt-BR"<?= !empty($showIntro) ? ' class="intro-pending"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title ?? 'Moves') ?></title>
    <meta name="description" content="<?= $this->e($description ?? '') ?>">
    <meta name="robots" content="<?= $this->e($robots ?? 'noindex, nofollow') ?>">
    <meta name="theme-color" content="#090b0e">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="Moves">
    <meta property="og:title" content="<?= $this->e($title ?? 'Moves') ?>">
    <meta property="og:description" content="<?= $this->e($description ?? '') ?>">
    <?php if (!empty($canonical)): ?>
        <link rel="canonical" href="<?= $this->e($canonical) ?>">
        <meta property="og:url" content="<?= $this->e($canonical) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= $this->e($title ?? 'Moves') ?>">
    <meta name="twitter:description" content="<?= $this->e($description ?? '') ?>">
    <link rel="icon" href="<?= $this->e($this->asset('images/brand/favicon.png')) ?>" type="image/png">
    <link rel="stylesheet" href="<?= $this->e($this->asset('css/app.css')) ?>">
    <script src="<?= $this->e($this->asset('js/app.js')) ?>" defer></script>
</head>
<body>
    <?php if (!empty($showIntro)): ?>
        <div class="brand-intro" aria-label="Abertura Moves" hidden>
            <div class="intro-orbit" aria-hidden="true"></div>
            <div class="intro-logo-stage">
                <img class="intro-logo" src="<?= $this->e($this->asset('images/brand/moves-logo.svg')) ?>" alt="MOVES" width="772" height="112" fetchpriority="high">
                <div class="intro-shine" aria-hidden="true"></div>
            </div>
            <span class="intro-caption">IDEIAS EM MOVIMENTO</span>
            <button class="intro-skip" type="button">Pular abertura ↗</button>
        </div>
    <?php endif; ?>
    <div class="noise" aria-hidden="true"></div>
    <div class="cursor-glow" aria-hidden="true"></div>
    <a class="skip-link" href="#main">Pular para o conteúdo</a>
    <?php $this->insert('components/header', [
        'currentPage' => $currentPage ?? null,
    ]); ?>
    <?php $this->insert('components/flash'); ?>
    <main id="main">
        <?= $this->section('content') ?>
    </main>
    <?php $this->insert('components/footer'); ?>
</body>
</html>
