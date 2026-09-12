<?php

declare(strict_types=1);

/**
 * Moves | Users Page
 *
 * Exibe a listagem administrativa
 * de usuários cadastrados.
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
    <h1>Usuários</h1>

    <?php if ($users === []): ?>
        <p>Nenhum usuário cadastrado.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Papel</th>
                <th>Status</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $this->e($user->name) ?></td>
                    <td><?= $this->e($user->email) ?></td>
                    <td><?= $this->e($user->role) ?></td>
                    <td><?= $this->e($user->status) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>