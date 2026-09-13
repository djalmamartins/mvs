<?php

/** Moves | Public home page. */

$highlights = $highlights ?? [];
$testimonials = $testimonials ?? [];

$this->layout('layouts/default', [
    'title' => $title,
    'description' => $description,
    'canonical' => $canonical ?? null,
    'robots' => $robots ?? 'noindex, nofollow',
    'showIntro' => true,
    'currentPage' => 'home',
]);
?>

<section class="reference-hero">
    <img class="reference-hero-image" src="<?= $this->e($this->asset('images/home/hero.jpg')) ?>" alt="Profissional usando um notebook" width="1400" height="933" fetchpriority="high">
    <div class="section-shell reference-hero-content reveal">
        <div class="hero-index" aria-hidden="true">01</div>
        <div><p class="eyebrow">MOVES · ESTRATÉGIA, DESIGN E TECNOLOGIA</p><h1>Sua próxima ideia.<br>Uma nova <span class="brand-gradient">presença digital.</span></h1><a class="hero-portfolio-link" href="#projects">EXPLORE AS POSSIBILIDADES <span aria-hidden="true">↗</span></a></div>
    </div>
</section>

<?php if($highlights!==[]):?><section class="section-shell block public-highlights" aria-label="Destaques"><?php foreach($highlights as $highlight):?><article class="public-highlight align-<?= $this->e($highlight['meta']['alignment']??'left') ?>"><?php if($highlight['media_id']):?><img src="/media/<?= (int)$highlight['media_id'] ?>" alt="<?= $this->e($highlight['alt_text']?:$highlight['title']) ?>"><?php endif;?><div><p class="eyebrow">DESTAQUE</p><h2><?= $this->e($highlight['title']) ?></h2><p><?= $this->e($highlight['excerpt']??'') ?></p><?php if(($highlight['meta']['cta_label']??'')&&($highlight['meta']['cta_url']??'')):?><a class="button button-primary" href="<?= $this->e($highlight['meta']['cta_url']) ?>"><?= $this->e($highlight['meta']['cta_label']) ?> ↗</a><?php endif;?></div></article><?php endforeach;?></section><?php endif;?>

<section class="reference-overlap">
    <div><span class="reference-symbol" aria-hidden="true">↗</span><p>Design que conecta.<br>Tecnologia que faz acontecer.</p></div>
    <div><p class="eyebrow">SITES COM PROPÓSITO</p><p>Uma experiência clara para apresentar seu negócio e iniciar novas conversas.</p></div>
</section>

<section class="stats band stats-results" aria-label="Moves em números">
    <div><strong data-count="150" data-prefix="+" data-suffix="" aria-label="+0">+0</strong><span>projetos entregues</span></div>
    <div><strong data-count="12" data-prefix="+" data-suffix="" aria-label="+0">+0</strong><span>anos de experiência</span></div>
    <div><strong data-count="60" data-prefix="+" data-suffix="" aria-label="+0">+0</strong><span>clientes atendidos</span></div>
    <div><strong data-count="98" data-prefix="" data-suffix="%" aria-label="0%">0%</strong><span>de satisfação</span></div>
    <div class="territory"><p>Soluções<br>em todo<br>o Brasil</p><span class="brazil-reference" aria-hidden="true"></span></div>
</section>

<section class="section-shell block" id="services">
    <div class="section-heading reveal"><div><p class="eyebrow">NOSSOS SERVIÇOS</p><h2>Soluções completas<br>para o seu <span class="brand-gradient">próximo passo.</span></h2></div><p>Do planejamento ao crescimento, entregamos soluções digitais que geram resultados reais para o seu negócio.</p></div>
    <div class="services-grid">
        <article class="service-card card-1"><span class="service-icon">▣</span><h3>Criação de Sites</h3><p>Sites institucionais e experiências digitais que geram resultados.</p><a href="#contact" aria-label="Conversar sobre criação de sites">↗</a></article>
        <article class="service-card card-2"><span class="service-icon">◇</span><h3>Sistemas Web</h3><p>Plataformas e integrações sob medida para o seu negócio.</p><a href="#contact" aria-label="Conversar sobre sistemas web">↗</a></article>
        <article class="service-card card-apps"><span class="service-icon">05</span><h3>Aplicativos</h3><p>Planejamos experiências para dispositivos móveis com foco nos fluxos que o seu público precisa realizar.</p><a href="#contact" aria-label="Conversar sobre aplicativos">↗</a></article>
        <article class="service-card card-hosting"><span class="service-icon">06</span><h3>Hospedagem</h3><p>Planejamos o ambiente de hospedagem de acordo com as necessidades técnicas do seu site e da sua operação.</p><a href="#contact" aria-label="Conversar sobre hospedagem">↗</a></article>
    </div>
</section>

<section class="section-shell projects-block" id="projects">
    <div class="projects-intro reveal"><p class="eyebrow">IDEIAS EM MOVIMENTO</p><h2>Possibilidades<br>em <span class="brand-gradient">movimento.</span></h2><p>Conceitos de soluções para explorar novas possibilidades em diferentes setores.</p></div>
    <div class="projects-grid portfolio-grid">
        <article class="project-card editorial-project project-a"><div class="project-content"><span aria-hidden="true">01</span><p class="project-kind">Plataforma digital · conceito</p><h3>Condomínios</h3><p class="project-summary">Portal para administradoras.</p></div></article>
        <article class="project-card editorial-project project-office"><div class="project-content"><span aria-hidden="true">02</span><p class="project-kind">Site institucional · conceito</p><h3>Escritório virtual</h3><p class="project-summary">Presença digital para serviços profissionais.</p></div></article>
        <article class="project-card editorial-project project-fashion"><div class="project-content"><span aria-hidden="true">03</span><p class="project-kind">E-commerce · conceito</p><h3>Moda e lifestyle</h3><p class="project-summary">Uma vitrine para explorar coleções.</p></div></article>
        <article class="project-card editorial-project project-c"><div class="project-content"><span aria-hidden="true">04</span><p class="project-kind">Identidade visual · conceito</p><h3>Gastronomia</h3><p class="project-summary">Design e identidade para a marca.</p></div></article>
        <article class="project-card editorial-project project-b"><div class="project-content"><span aria-hidden="true">05</span><p class="project-kind">Aplicativo · conceito</p><h3>Saúde</h3><p class="project-summary">Experiência digital para pacientes.</p></div></article>
        <article class="project-card editorial-project project-d"><div class="project-content"><span aria-hidden="true">06</span><p class="project-kind">Plataforma de aprendizagem · conceito</p><h3>Educação</h3><p class="project-summary">Um ambiente para aprender online.</p></div></article>
    </div>
</section>

<section class="section-shell process block">
    <div class="section-heading reveal"><div><p class="eyebrow">NOSSO PROCESSO</p><h2>Da ideia ao resultado,<br>sem perder o <span class="brand-gradient">movimento.</span></h2></div><p>Um processo colaborativo, transparente e focado em resultados reais.</p></div>
    <div class="timeline reveal">
        <article><span>01</span><h3>Descobrir</h3><p>Entendemos seu desafio, mercado e oportunidades.</p></article><article><span>02</span><h3>Estruturar</h3><p>Definimos a estratégia e o melhor caminho.</p></article><article><span>03</span><h3>Criar</h3><p>Transformamos ideias em experiências.</p></article><article><span>04</span><h3>Desenvolver</h3><p>Damos vida ao projeto com tecnologia.</p></article><article><span>05</span><h3>Evoluir</h3><p>Acompanhamos, otimizamos e geramos resultados.</p></article>
    </div>
</section>

<section class="section-shell about-editorial" id="about">
    <div class="about-manifesto">MAIS QUE PROJETOS.<br>CONSTRUÍMOS<br>PARCERIAS.<span></span></div>
    <div class="about-editorial-image"><img src="<?= $this->e($this->asset('images/home/team.png')) ?>" alt="Profissionais colaborando em um projeto digital" width="1670" height="941" loading="lazy"></div>
    <div class="about-editorial-copy"><p class="eyebrow">SOMOS A MOVES</p><h2>Uma equipe que conecta ideias e <span class="brand-gradient">ama</span> o que faz.</h2><p>Unimos estratégia, design e tecnologia para construir soluções com você, do primeiro desafio à próxima evolução.</p><a class="text-link" href="/contato">Converse com a Moves →</a></div>
</section>

<section class="section-shell block" id="content">
    <div class="section-heading reveal"><div><p class="eyebrow">CONTEÚDO</p><h2>Insights para<br>você ir <span class="brand-gradient">mais longe.</span></h2></div></div>
    <div class="articles-grid">
        <article class="article-card"><div class="article-image article-1"></div><div><small>ESTRATÉGIA · GUIA MOVES</small><h3>Como preparar o conteúdo do seu novo site</h3><p>Um roteiro para organizar objetivos, páginas e materiais antes de começar.</p></div></article>
        <article class="article-card"><div class="article-image article-2"></div><div><small>TECNOLOGIA · GUIA MOVES</small><h3>Por onde começar a automatizar processos</h3><p>Observe as tarefas repetitivas antes de escolher uma ferramenta.</p></div></article>
        <article class="article-card"><div class="article-image article-3"></div><div><small>PRODUTO · GUIA MOVES</small><h3>Como validar uma ideia antes de desenvolver um aplicativo</h3><p>Transforme suposições em perguntas que podem ser testadas.</p></div></article>
    </div>
</section>

<section class="cta" id="contact"><div class="cta-inner reveal"><div><p class="eyebrow">VAMOS CONVERSAR?</p><h2>Tem algo que precisa<br>sair do lugar?</h2><p>Vamos colocar sua ideia em movimento.</p></div><a href="/contato" class="button button-primary large">Solicitar orçamento <span aria-hidden="true">→</span></a></div></section>
<?php if($testimonials!==[]):?><section class="section-shell block public-testimonials"><div class="section-heading"><div><p class="eyebrow">DEPOIMENTOS</p><h2>Quem já colocou ideias em movimento.</h2></div></div><div><?php foreach($testimonials as $testimonial):?><blockquote><?php if($testimonial['media_id']):?><img src="/media/<?= (int)$testimonial['media_id'] ?>" alt="<?= $this->e($testimonial['alt_text']?:$testimonial['title']) ?>"><?php endif;?><p>“<?= $this->e($testimonial['excerpt']??'') ?>”</p><footer><strong><?= $this->e($testimonial['title']) ?></strong><span><?= $this->e(implode(' · ',array_filter([$testimonial['meta']['job_title']??'',$testimonial['meta']['company']??'']))) ?></span></footer></blockquote><?php endforeach;?></div></section><?php endif;?>
