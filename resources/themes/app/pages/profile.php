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
