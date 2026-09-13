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
        'currentPage' => 'users',
    ]
);
?>

<section class="studio-page">
    <header class="studio-page-heading"><div><p class="studio-eyebrow">GESTÃO</p><h2>Usuários</h2><p>Contas cadastradas e seus níveis de acesso atuais.</p></div></header>

    <?php if ($users === []): ?>
        <p>Nenhum usuário cadastrado.</p>
    <?php else: ?>
        <div class="studio-table-wrap"><table>
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
        </table></div>
        <?php if ($pagination !== null): ?>
            <?= $pagination ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
