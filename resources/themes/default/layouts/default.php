<?php

/**
 * Moves | Default Layout
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

    <title><?= $this->e($title ?? 'Moves') ?></title>

    <link
            rel="stylesheet"
            href="<?= $this->e($this->asset('css/app.css')) ?>"
    >
</head>

<body>

<?php $this->insert('components/header', [
    'title' => $title ?? 'Moves',
]); ?>

<?= $this->section('content') ?>

</body>
</html>