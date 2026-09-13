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
        <?php if ($articles === []): ?><p class="page-lead">Novos conteúdos estão sendo preparados.</p><?php endif; ?>
        <?php foreach ($articles as $article): ?><article class="article-card"><div class="article-image article-1" role="img" aria-label="<?= $this->e($article['title']) ?>"></div><div><small>CONTEÚDO · MOVES</small><h2><?= $this->e($article['title']) ?></h2><p><?= $this->e($article['excerpt'] ?? '') ?></p><a href="/conteudo/<?= $this->e($article['slug']) ?>">Ler conteúdo ↗</a></div></article><?php endforeach; ?>
    </div>
</section>

<section class="cta"><div class="cta-inner reveal"><div><p class="eyebrow">SEU PRÓXIMO MOVIMENTO</p><h2>Vamos tirar sua<br>ideia do papel?</h2><p>Conte seu desafio. Construímos o próximo passo juntos.</p></div><a class="button button-primary large" href="/contato">Conversar sobre meu projeto ↗</a></div></section>
