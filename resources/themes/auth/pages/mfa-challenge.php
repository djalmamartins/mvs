<?php
declare(strict_types=1);

$this->layout('layouts/default', ['title' => $title]);
?>
<main class="auth-shell">
    <section class="auth-panel" aria-labelledby="mfa-title">
        <div class="auth-card">
            <img class="auth-logo" src="/themes/site/images/brand/moves-logo.svg" alt="Moves" width="194" height="28">
            <p class="auth-eyebrow">Acesso seguro</p>
            <h1 id="mfa-title"><?= $this->e($title) ?></h1>
            <?php if (is_array($setup ?? null)): ?>
                <p class="auth-lead">Configure o autenticador antes de entrar. Leia o QR Code abaixo e informe o primeiro código de 6 dígitos.</p>
                <div class="auth-mfa-setup">
                    <img src="<?= $this->e((string) $setup['qr']) ?>" alt="QR Code para configurar o autenticador Moves">
                    <p>Se preferir, use esta chave manual:</p>
                    <code><?= $this->e((string) $setup['secret']) ?></code>
                </div>
            <?php else: ?>
                <p class="auth-lead">Confirme sua identidade com o código do autenticador ou use um dos seus códigos de recuperação.</p>
            <?php endif; ?>

            <form id="auth-form" class="auth-form" method="post" action="/login/2fa" novalidate>
                <?= $this->csrf() ?>
                <label for="code">Código de verificação</label>
                <input type="text" id="code" name="code" autocomplete="one-time-code" inputmode="text" maxlength="14" required autofocus aria-describedby="mfa-help">
                <small id="mfa-help"><?= is_array($setup ?? null)
                    ? 'Digite os 6 números gerados pelo autenticador após ler o QR Code.'
                    : 'Digite os 6 números do autenticador ou um recovery code no formato XXXX-XXXX-XXXX.' ?></small>
                <button type="submit">Verificar e entrar</button>
            </form>

            <p class="auth-back"><a href="/login">← Voltar ao login</a></p>
            <p class="auth-meta">O desafio expira em 5 minutos · Moves <?= $this->e((string) $version) ?></p>
        </div>
    </section>
    <aside class="auth-visual" aria-label="Verificação em duas etapas Moves">
        <div>
            <p class="auth-visual-kicker">SEGURANÇA MOVES</p>
            <h2>Sua senha é apenas a primeira etapa.</h2>
            <p>A segunda verificação protege contas com acesso sensível mesmo quando uma senha é comprometida.</p>
            <ul>
                <li>Challenge temporário</li>
                <li>Sessão liberada somente após validação</li>
                <li>Recovery codes de uso único</li>
            </ul>
        </div>
    </aside>
</main>
