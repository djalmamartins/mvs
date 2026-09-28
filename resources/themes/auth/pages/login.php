<?php
declare(strict_types=1);

$this->layout('layouts/default', ['title' => $title]);
?>
<main class="auth-shell">
    <section class="auth-panel" aria-labelledby="login-title">
        <div class="auth-card">
            <img class="auth-logo" src="/themes/site/images/brand/moves-logo.svg" alt="Moves" width="194" height="28">
            <p class="auth-eyebrow">Acesso seguro</p>
            <h1 id="login-title"><?= $this->e($title) ?></h1>
            <p class="auth-lead">Use sua identidade corporativa para acessar sua administradora e os produtos Moves.</p>

            <form id="auth-form" class="auth-form" method="post" action="/login" novalidate>
                <?= $this->csrf() ?>
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" autocomplete="email" inputmode="email" required autofocus aria-describedby="email-hint">
                <small id="email-hint">Informe o e-mail vinculado à sua conta.</small>

                <div class="password-label">
                    <label for="password">Senha</label>
                    <a href="/forgot-password">Esqueci minha senha</a>
                </div>
                <input type="password" id="password" name="password" autocomplete="current-password" required minlength="8">

                <button type="submit">Entrar</button>
            </form>

            <p class="auth-meta">Ambiente seguro · Moves <?= $this->e((string) $version) ?></p>
        </div>
    </section>
    <aside class="auth-visual" aria-label="Moves Platform">
        <div>
            <p class="auth-visual-kicker">MOVES PLATFORM</p>
            <h2>Uma identidade para toda a sua operação.</h2>
            <p>Acesse atendimento, gestão e produtividade com as políticas da sua organização.</p>
            <ul>
                <li>Sessões protegidas</li>
                <li>Acesso por administradora</li>
                <li>Permissões por função</li>
            </ul>
        </div>
    </aside>
</main>
