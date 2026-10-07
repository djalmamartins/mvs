<?php declare(strict_types=1); $this->layout('layouts/default', ['title' => $title, 'currentPage' => 'users']); $editing = is_array($user); $protected = $editing && (int) $user['id'] === 1; ?>
<section class="studio-page">
    <header class="studio-page-heading">
        <div>
            <p class="studio-eyebrow">GESTÃO DE ACESSO</p>
            <h2><?= $this->e($title) ?></h2>
            <p><?= $editing ? 'Altere o papel e o acesso desta pessoa nesta administradora.' : 'Envie um convite para a administradora ativa. O usuário definirá a própria senha.' ?></p>
        </div>
        <a class="studio-btn" href="/studio/users">Voltar</a>
    </header>

    <form class="studio-form-card studio-user-form" method="post" action="/studio/users/save">
        <?= $this->csrf() ?>
        <input type="hidden" name="id" value="<?= $this->e((string) ($user['id'] ?? 0)) ?>">
        <div class="studio-form-grid">
            <?php if ($editing): ?>
                <div class="studio-field"><span>Nome</span><p><?= $this->e((string) $user['name']) ?></p></div>
                <div class="studio-field"><span>E-mail</span><p><?= $this->e((string) $user['email']) ?></p></div>
            <?php else: ?>
                <div class="studio-field"><label for="name">Nome</label><input id="name" name="name" minlength="2" maxlength="120" required></div>
                <div class="studio-field"><label for="email">E-mail</label><input id="email" type="email" name="email" maxlength="190" required></div>
            <?php endif; ?>
            <div class="studio-field">
                <label for="role_id">Papel nesta administradora</label>
                <select id="role_id" name="role_id" required<?= $protected ? ' disabled' : '' ?>>
                    <option value="">Selecione</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $this->e((string) $role['id']) ?>"<?= $editing && (int) $user['role_id'] === (int) $role['id'] ? ' selected' : '' ?>><?= $this->e((string) $role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($editing): ?>
                <div class="studio-field">
                    <label for="status">Acesso nesta administradora</label>
                    <select id="status" name="status"<?= $protected ? ' disabled' : '' ?>>
                        <option value="active"<?= $user['status'] === 'active' ? ' selected' : '' ?>>Ativo</option>
                        <option value="inactive"<?= $user['status'] === 'inactive' ? ' selected' : '' ?>>Inativo</option>
                    </select>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($editing): ?>
            <p>O nome, o e-mail, a senha e o estado global da conta não são alterados por esta administradora.</p>
        <?php endif; ?>
        <?php if (!$protected): ?>
            <button type="submit"><?= $editing ? 'Salvar acesso' : 'Enviar convite' ?></button>
        <?php else: ?>
            <p>O administrador principal é protegido e não pode ser alterado nesta tela.</p>
        <?php endif; ?>
    </form>

    <?php if ($editing && (int) $user['id'] !== 1): ?>
        <form class="studio-danger-zone" method="post" action="/studio/users/action" data-confirm-submit="Remover o acesso desta administradora?">
            <?= $this->csrf() ?>
            <input type="hidden" name="id" value="<?= $this->e((string) $user['id']) ?>">
            <input type="hidden" name="action" value="delete">
            <button class="studio-btn danger" type="submit">Remover acesso desta administradora</button>
        </form>
    <?php endif; ?>
</section>
