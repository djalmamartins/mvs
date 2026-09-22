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
        'currentPage' => 'profile',
    ]
);
?>

<section class="customer-page-heading"><div><p class="customer-eyebrow">CONTA</p><h2>Meu perfil</h2><p>Dados básicos da sua conta de acesso.</p></div></section><section class="customer-profile-card"><div class="customer-avatar" aria-hidden="true"><?= $this->e(strtoupper(substr((string)$user->name,0,1))) ?></div><dl><div><dt>Nome</dt><dd><?= $this->e($user->name) ?></dd></div><div><dt>E-mail</dt><dd><?= $this->e($user->email) ?></dd></div><div><dt>Status</dt><dd><span class="customer-status"><?= $this->e($user->status) ?></span></dd></div></dl></section>

<section class="customer-profile-card" id="security" aria-labelledby="profile-security-title">
<div><p class="customer-eyebrow">SEGURANÇA</p><h2 id="profile-security-title">Alterar senha</h2><p>Confirme sua senha atual antes de definir uma nova.</p></div>
<form method="post" action="/app/profile/security" class="customer-profile-security-form">
<?= $this->csrf() ?>
<label>Senha atual<input type="password" name="current_password" autocomplete="current-password" required></label>
<label>Nova senha<input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
<label>Confirmar nova senha<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label>
<button type="submit">Atualizar senha</button>
</form>
</section>
