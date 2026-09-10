<?php

/**
 * Moves | Home Page
 *
 * Exibe a página inicial do tema padrão.
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

    <title><?= $this->e($title) ?></title>

    <link
            rel="stylesheet"
            href="<?= $this->e($this->asset('css/app.css')) ?>"
    >
</head>

<body>

<main>
    <h1><?= $this->e($title) ?></h1>

    <p><?= $this->e($description) ?></p>

    <small>
        Tema: <?= $this->e($theme) ?>
    </small>
</main>

</body>
</html>