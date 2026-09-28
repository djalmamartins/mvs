<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title ?? 'Moves') ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#6e00b3">
    <link rel="icon" href="/themes/site/images/brand/favicon.png" type="image/png">
    <link rel="stylesheet" href="<?= $this->e($this->asset('css/auth.css')) ?>">
    <link rel="stylesheet" href="<?= $this->e($this->asset('css/recovery.css')) ?>">
</head>
<body>
    <a class="skip-link" href="#auth-form">Ir para o formulário</a>
    <?php $this->insert('components/flash'); ?>
    <?= $this->section('content') ?>
</body>
</html>
