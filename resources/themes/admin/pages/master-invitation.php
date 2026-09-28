<?php
declare(strict_types=1);
$this->layout('layouts/auth',['title'=>$title]);
?>
<section class="auth-card">
    <header>
        <span class="kicker">MOVES PLATFORM</span>
        <h1>Ativar acesso</h1>
        <p>Defina sua senha para concluir o convite. Este link é pessoal, expira e só pode ser usado uma vez.</p>
    </header>
    <form method="post" action="/invite/accept/<?= $this->e((string)$token) ?>">
        <?= $this->csrf() ?>
        <label>Nova senha<input type="password" name="password" minlength="12" autocomplete="new-password" required></label>
        <label>Confirmar senha<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label>
        <button class="primary-btn" type="submit">Ativar acesso</button>
    </form>
</section>
