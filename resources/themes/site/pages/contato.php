<?php

/** Moves | Public commercial contact page. */

$this->layout('layouts/default', [
    'title' => $title,
    'description' => $description,
    'canonical' => $canonical ?? null,
    'robots' => $robots ?? 'noindex, nofollow',
    'currentPage' => 'contact',
]);
?>

<header class="page-hero section-shell">
    <p class="eyebrow">VAMOS CONVERSAR</p>
    <h1>Todo movimento<br><span class="brand-gradient">começa com uma ideia.</span></h1>
    <p class="page-lead">Conte o que você quer construir. Vamos entender o desafio e conversar sobre os próximos passos.</p>
</header>

<section class="section-shell contact-layout">
    <aside>
        <p class="eyebrow">CONTATO DIRETO</p>
        <h2>Qual é o seu<br>próximo passo?</h2>
        <a class="contact-email" href="mailto:contato@moves.com.br">contato@moves.com.br ↗</a>
        <p>Conselheiro Lafaiete · MG<br>Projetos digitais, de onde você estiver.</p>
        <div class="contact-note">Prefere escrever com calma? Preencha o resumo ao lado para preparar uma mensagem no seu aplicativo de e-mail.</div>
    </aside>
    <form id="contact-form" method="post" action="/contato">
        <?= $this->csrf() ?>
        <div class="form-row">
            <label>Seu nome<input name="nome" autocomplete="name" required maxlength="100" placeholder="Como podemos chamar você?"></label>
            <label>E-mail<input type="email" name="email" autocomplete="email" required maxlength="200" placeholder="voce@empresa.com.br"></label>
        </div>
        <div class="form-row">
            <label>Empresa <span>(opcional)</span><input name="empresa" autocomplete="organization" maxlength="120" placeholder="Nome da sua empresa"></label>
            <label>Qual serviço você procura?<select name="servico" required><option value="">Selecione um serviço</option><option value="criacao-de-sites">Criação de Sites</option><option value="sistemas-web">Sistemas Web</option><option value="identidade-e-design">Identidade e Design</option><option value="automacao-e-ia">Automação e IA</option><option value="aplicativos">Aplicativos</option><option value="hospedagem">Hospedagem</option><option value="outro">Quero conversar sobre uma ideia</option></select></label>
        </div>
        <label>Conte sobre o projeto<textarea name="mensagem" rows="6" required minlength="20" maxlength="5000" placeholder="O que você precisa resolver? Tem um prazo ou alguma referência?"></textarea></label>
        <label class="checkbox-label"><input type="checkbox" required> <span>Revisei as informações e quero preparar este contato comercial.</span></label>
        <button class="button button-primary" type="submit">Enviar proposta ↗</button>
        <p class="form-help">A solicitação será enviada com segurança ao Moves Studio.</p>
    </form>
</section>
