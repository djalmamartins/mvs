<?php

/** Moves | Public editorial index. */

$this->layout('layouts/default', [
    'title' => $title,
    'description' => $description,
    'canonical' => $canonical ?? null,
    'robots' => $robots ?? 'noindex, nofollow',
    'currentPage' => 'content',
]);
?>

<header class="page-hero section-shell">
    <p class="eyebrow">PARA SEU PRÓXIMO PASSO</p>
    <h1>Ideias para pensar.<br><span class="brand-gradient">Clareza para agir.</span></h1>
    <p class="page-lead">Leituras sobre estratégia, tecnologia e criação de experiências digitais.</p>
</header>

<section class="section-shell page-section" aria-label="Conteúdos da Moves">
    <div class="articles-grid">
        <article class="article-card"><div class="article-image article-1" role="img" aria-label="Planejamento de conteúdo para sites"></div><div><small>ESTRATÉGIA · GUIA MOVES</small><h2>Como preparar o conteúdo do seu novo site</h2><p>Um roteiro para organizar objetivos, páginas e materiais antes de começar.</p><span>Em breve</span></div></article>
        <article class="article-card"><div class="article-image article-2" role="img" aria-label="Automação de processos"></div><div><small>TECNOLOGIA · GUIA MOVES</small><h2>Por onde começar a automatizar processos</h2><p>Observe as tarefas repetitivas antes de escolher uma ferramenta.</p><span>Em breve</span></div></article>
        <article class="article-card"><div class="article-image article-3" role="img" aria-label="Validação de produto digital"></div><div><small>PRODUTO · GUIA MOVES</small><h2>Como validar uma ideia antes de desenvolver um aplicativo</h2><p>Transforme suposições em perguntas que podem ser testadas.</p><span>Em breve</span></div></article>
    </div>
</section>

<section class="cta"><div class="cta-inner reveal"><div><p class="eyebrow">SEU PRÓXIMO MOVIMENTO</p><h2>Vamos tirar sua<br>ideia do papel?</h2><p>Conte seu desafio. Construímos o próximo passo juntos.</p></div><a class="button button-primary large" href="/contato">Conversar sobre meu projeto ↗</a></div></section>
