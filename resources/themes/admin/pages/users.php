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
    <header class="studio-page-heading"><div><p class="studio-eyebrow">GESTÃO</p><h2>Usuários</h2><p>Gerencie contas, perfis e acesso ao Moves.</p></div><a class="studio-btn primary" href="/admin/users/create">Novo usuário</a></header>
    <section class="studio-user-stats"><article><i aria-hidden="true">◎</i><div><span>Total</span><strong><?= $this->e((string) ($stats['total'] ?? 0)) ?></strong><small>contas cadastradas</small></div></article><article><i aria-hidden="true">✓</i><div><span>Ativos</span><strong><?= $this->e((string) ($stats['active'] ?? 0)) ?></strong><small>com acesso</small></div></article><article><i aria-hidden="true">○</i><div><span>Inativos</span><strong><?= $this->e((string) ($stats['inactive'] ?? 0)) ?></strong><small>sem acesso</small></div></article><article><i aria-hidden="true">◇</i><div><span>Administradores</span><strong><?= $this->e((string) ($stats['admins'] ?? 0)) ?></strong><small>gestores</small></div></article></section>
    <form class="studio-filter-bar" method="get"><label><span>Buscar</span><input type="search" name="q" value="<?= $this->e($search) ?>" placeholder="Nome ou e-mail"></label><label><span>Status</span><select name="status"><option value="">Todos</option><option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Ativo</option><option value="inactive"<?= $status === 'inactive' ? ' selected' : '' ?>>Inativo</option></select></label><label><span>Perfil</span><select name="role"><option value="">Todos</option><option value="admin"<?= $role === 'admin' ? ' selected' : '' ?>>Administrador</option><option value="user"<?= $role === 'user' ? ' selected' : '' ?>>Usuário</option></select></label><button type="submit">Filtrar</button><a href="/admin/users">Limpar</a></form>

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
                <th>Ações</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $this->e($user['name']) ?></td>
                    <td><?= $this->e($user['email']) ?></td>
                    <td><?= $this->e($user['role'] === 'admin' ? 'Administrador' : 'Usuário') ?></td>
                    <td><span class="studio-status studio-status-<?= $user['status'] === 'active' ? 'info' : 'warning' ?>"><?= $this->e($user['status'] === 'active' ? 'Ativo' : 'Inativo') ?></span></td>
                    <td><div class="studio-row-actions"><a href="/admin/users/edit/<?= $this->e((string) $user['id']) ?>" aria-label="Editar <?= $this->e($user['name']) ?>">Editar</a><?php if ((int) $user['id'] !== 1): ?><form method="post" action="/admin/users/action"><?= $this->csrf() ?><input type="hidden" name="id" value="<?= $this->e((string) $user['id']) ?>"><input type="hidden" name="action" value="<?= $user['status'] === 'active' ? 'deactivate' : 'activate' ?>"><button type="submit"><?= $user['status'] === 'active' ? 'Desativar' : 'Ativar' ?></button></form><?php endif; ?></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php if ($pagination !== null): ?>
            <?= $pagination ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
