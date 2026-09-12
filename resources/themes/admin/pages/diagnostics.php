<?php

declare(strict_types=1);

/**
 * Moves | Diagnostics Page
 *
 * Apresenta o estado dos requisitos essenciais da plataforma.
 *
 * @author Djalma Martins
 */

$this->layout('layouts/default', ['title' => $title]);
?>

<main>
    <h1><?= $this->e($title) ?></h1>

    <ul>
        <?php foreach ($checks as $check): ?>
            <li>
                <strong><?= $check['ok'] ? 'OK' : 'FALHA' ?>:</strong>
                <?= $this->e($check['message']) ?>
            </li>
        <?php endforeach; ?>
    </ul>
</main>
