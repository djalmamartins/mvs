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
                <label class="mfa-fallback-label" for="code-fallback">Código de verificação</label>
                <input class="mfa-fallback" type="text" id="code-fallback" name="code" autocomplete="one-time-code" inputmode="text" maxlength="14" required aria-describedby="mfa-help" autofocus>
                <fieldset class="mfa-code-group" aria-describedby="mfa-help" hidden>
                    <legend>Código de verificação</legend>
                    <div class="mfa-digits" role="group" aria-label="Código de 6 dígitos">
                        <?php for ($digit = 1; $digit <= 6; $digit++): ?>
                            <input class="mfa-digit" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1" autocomplete="<?= $digit === 1 ? 'one-time-code' : 'off' ?>" aria-label="Dígito <?= $digit ?> de 6" required>
                        <?php endfor; ?>
                    </div>
                </fieldset>
                <input type="hidden" id="code" value="" disabled>
                <div class="mfa-recovery" hidden>
                    <label for="recovery-code">Código de recuperação</label>
                    <input type="text" id="recovery-code" autocomplete="off" maxlength="14" pattern="[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}" aria-describedby="mfa-help" disabled>
                </div>
                <small id="mfa-help"><?= is_array($setup ?? null)
                    ? 'Digite os 6 números gerados pelo autenticador após ler o QR Code.'
                    : 'Digite os 6 números do autenticador.' ?></small>
                <?php if (!is_array($setup ?? null)): ?><noscript><small>Códigos de recuperação também são aceitos no formato XXXX-XXXX-XXXX.</small></noscript><?php endif; ?>
                <?php if (!is_array($setup ?? null)): ?><button class="mfa-mode-toggle" type="button" aria-expanded="false" hidden>Usar código de recuperação</button><?php endif; ?>
                <button type="submit">Verificar e entrar</button>
            </form>
            <script src="/themes/auth/js/mfa-challenge.js" defer></script>

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
