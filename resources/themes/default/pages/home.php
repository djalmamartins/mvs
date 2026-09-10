<?php

/**
 * Moves | Home Page
 *
 * Exibe a página inicial do tema padrão.
 *
 * @author Djalma Martins
 */

$this->layout('layouts/default', [
    'title' => $title,
]);
?>

<main>
    <h1><?= $this->e($title) ?></h1>

    <p><?= $this->e($description) ?></p>

    <small>
        Tema: <?= $this->e($theme) ?>
    </small>
</main>