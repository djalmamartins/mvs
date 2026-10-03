<?php

declare(strict_types=1);

$this->layout('layouts/default', ['title' => $title, 'productName' => 'Meu Dia', 'activeProduct' => 'day', 'currentPage' => 'security']);
?>
<section class="platform-page platform-security-page">
    <header class="studio-page-head"><div><p class="studio-eyebrow">PERFIL · SEGURANÇA</p><h1 class="studio-page-title">Verificação em duas etapas</h1><p class="studio-page-description">Um segundo fator reduz o risco de acesso indevido mesmo se sua senha for descoberta.</p></div></header>
    <section class="platform-panel platform-security-card">
        <?php if (!$available): ?><div class="platform-state warning"><strong>2FA indisponível neste ambiente</strong><p>A chave de criptografia precisa ser configurada pelo administrador. Nenhum segredo foi criado.</p></div>
        <?php elseif ($recoveryCodes !== []): ?><div class="platform-state success"><strong>Status: Ativado</strong><p>O código será solicitado nos próximos logins. Estes recovery codes aparecem uma única vez; guarde-os em local seguro.</p></div><ul class="platform-recovery-codes" aria-label="Códigos de recuperação"><?php foreach ($recoveryCodes as $recoveryCode): ?><li><code><?= $this->e((string) $recoveryCode) ?></code></li><?php endforeach; ?></ul><a class="studio-btn primary" href="/profile">Concluir</a>
        <?php elseif ($enabled): ?><div class="platform-state success"><strong>Status: Ativado</strong><p>A verificação em duas etapas será obrigatória nos próximos logins. Seus códigos de recuperação continuam válidos até serem usados.</p></div><a class="studio-btn" href="/profile">Voltar ao perfil</a>
        <?php elseif (is_array($setup)): ?><div class="platform-mfa-setup"><div><h2>1. Leia o QR Code</h2><p>Use um aplicativo autenticador compatível com TOTP.</p><img class="platform-mfa-qr" src="<?= $this->e((string) $setup['qr']) ?>" alt="QR Code para configurar o autenticador"><details><summary>Usar chave manual</summary><p>Digite esta chave no autenticador:</p><code class="platform-mfa-secret"><?= $this->e((string) $setup['secret']) ?></code></details></div><form method="post" action="/profile/security/2fa/confirm" class="platform-form"><?= $this->csrf() ?><h2>2. Confirme o código</h2><label>Código TOTP de 6 dígitos<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus></label><button class="studio-btn primary" type="submit">Ativar verificação</button></form></div>
        <?php else: ?><div class="platform-state"><strong>Status: Desativado</strong><p>Você continuará acessando normalmente. O QR Code só será gerado depois que você solicitar a ativação.</p></div><form method="post" action="/profile/security/2fa/start" class="platform-form platform-form-inline"><?= $this->csrf() ?><button class="studio-btn primary" type="submit">Ativar verificação em duas etapas</button></form><?php endif; ?>
    </section>
</section>
