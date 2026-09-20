<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title ?? 'Central de Ajuda — Moves') ?></title>
    <meta name="description" content="<?= $this->e($description ?? '') ?>">
    <meta name="robots" content="<?= $this->e($robots ?? 'index, follow') ?>">
    <meta name="theme-color" content="#6e00b3">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="Central de Ajuda Moves">
    <meta property="og:type" content="<?= $this->e($ogType ?? 'website') ?>">
    <meta property="og:title" content="<?= $this->e($title ?? 'Central de Ajuda — Moves') ?>">
    <meta property="og:description" content="<?= $this->e($description ?? '') ?>">
    <?php if (!empty($canonical)): ?><link rel="canonical" href="<?= $this->e($canonical) ?>"><?php endif; ?>
    <?php if (!empty($ogUrl)): ?><meta property="og:url" content="<?= $this->e($ogUrl) ?>"><?php endif; ?>
    <?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= $this->e($ogImage) ?>"><meta name="twitter:card" content="summary_large_image"><?php endif; ?>
    <link rel="icon" href="/themes/site/images/brand/favicon.png" type="image/png">
    <link rel="stylesheet" href="/themes/admin/css/moves-icons.css">
    <link rel="stylesheet" href="<?= $this->e($this->asset('css/help.css')) ?>">
</head>
<body>
    <a class="skip-link" href="#help-main">Pular para o conteúdo</a>
    <?php $this->insert('components/header'); ?>
    <main id="help-main"><?= $this->section('content') ?></main>
    <?php $this->insert('components/footer'); ?>
</body>
</html>
