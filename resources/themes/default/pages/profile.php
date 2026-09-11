<?php

declare(strict_types=1);

/**
 * Moves | Profile Page
 *
 * Exibe os dados básicos do usuário autenticado.
 *
 * @author Djalma Martins
 */
?>

<?php
$this->layout(
    'layouts/default',
    [
        'title' => $title,
    ]
);
?>

<section>
    <h1>Meu perfil</h1>

    <p>
        <strong>Nome:</strong>
        <?= $this->e($user->name) ?>
    </p>

    <p>
        <strong>E-mail:</strong>
        <?= $this->e($user->email) ?>
    </p>

    <p>
        <strong>Status:</strong>
        <?= $this->e($user->status) ?>
    </p>
</section>