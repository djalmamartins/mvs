<?php

/** Moves | Public about page. */

$this->layout('layouts/default', [
    'title' => $title,
    'description' => $description,
    'canonical' => $canonical ?? null,
    'robots' => $robots ?? 'noindex, nofollow',
    'currentPage' => 'about',
]);
?>

<section class="section-shell about-editorial about-page">
    <div class="about-manifesto">MAIS QUE PROJETOS.<br>CONSTRUÍMOS<br>PARCERIAS.<span></span></div>
    <div class="about-editorial-image"><img src="<?= $this->e($this->asset('images/home/team.jpg')) ?>" alt="Profissionais colaborando em um projeto digital" width="1400" height="2099" fetchpriority="high"></div>
    <div class="about-editorial-copy"><p class="eyebrow">SOMOS A MOVES</p><h1>Uma equipe que conecta ideias e <span class="brand-gradient">ama</span> o que faz.</h1><p>Unimos estratégia, design e tecnologia para construir soluções com você, do primeiro desafio à próxima evolução.</p><a class="text-link" href="/#contact">Converse com a Moves →</a></div>
</section>

<section class="cta"><div class="cta-inner reveal"><div><p class="eyebrow">SEU PRÓXIMO MOVIMENTO</p><h2>Vamos tirar sua<br>ideia do papel?</h2><p>Conte seu desafio. Construímos o próximo passo juntos.</p></div><a href="/#contact" class="button button-primary large">Solicitar orçamento ↗</a></div></section>
