<?php

/** Moves | Public projects page. */

$this->layout('layouts/default', [
    'title' => $title,
    'description' => $description,
    'canonical' => $canonical ?? null,
    'robots' => $robots ?? 'noindex, nofollow',
    'currentPage' => 'projects',
]);

$presentations = [
    ['slug' => 'fernandocaparroz', 'name' => 'Fernando Caparros', 'category' => 'Sites', 'url' => 'http://fernandocaparroz.web201.uni5.net/'],
    ['slug' => 'vestida-de-amor', 'name' => 'Vestida de Amor', 'category' => 'Sites', 'url' => 'https://vestida-de-amor.dynamicasoft.com/'],
    ['slug' => 'martins-vieira', 'name' => 'Martins Vieira Advogados', 'category' => 'Sites', 'url' => 'http://www.martinsvieira.adv.br/'],
    ['slug' => 'lucro-certo', 'name' => 'Aplicativo Lucro Certo', 'category' => 'Aplicativos', 'url' => 'https://applucrocerto.com.br/'],
    ['slug' => 'dynamicasoft', 'name' => 'Dynamica Soft', 'category' => 'Sistemas', 'url' => 'https://dynamicasoft.com/'],
    ['slug' => 'modaplusbrasil', 'name' => 'Moda Plus Brasil', 'category' => 'Sites', 'url' => 'https://modaplusbrasil.com.br/'],
    ['slug' => 'base-office', 'name' => 'Base Office', 'category' => 'Sites', 'url' => 'https://www.baseoffice.com.br/'],
    ['slug' => 'artesanidoces', 'name' => 'Artesani Doces', 'category' => 'Sites', 'url' => 'http://artesanidoces.web201.uni5.net'],
    ['slug' => 'atmosferainovacao', 'name' => 'Atmosfera Inovação', 'category' => 'Sites', 'url' => 'https://atmosferainovacao.com/'],
    ['slug' => 'emevil', 'name' => 'EMEVIL Engenharia & Construções', 'category' => 'Sites', 'url' => 'https://www.emevil.com.br/'],
    ['slug' => 'expandese', 'name' => 'ExpandeSe', 'category' => 'Sites', 'url' => 'https://www.expandese.com.br/'],
    ['slug' => 'casa-temporada', 'name' => 'Casa Temporada', 'category' => 'Sites', 'url' => 'https://casatemporada.com/'],
    ['slug' => 'karla', 'name' => 'Karla Trentini', 'category' => 'Sites', 'url' => 'https://karlatrentini.com.br/'],
    ['slug' => 'widetech', 'name' => 'WideTech Automação', 'category' => 'Sites', 'url' => 'https://widetechautomacao.com.br/'],
    ['slug' => 'studio-alta', 'name' => 'Studio Alta Arquitetura', 'category' => 'Sites', 'url' => 'https://studioalta.arq.br/'],
    ['slug' => 'paris-contabilidade', 'name' => 'Paris Contabilidade', 'category' => 'Sites', 'url' => 'https://pariscontabilidade.com.br/'],
    ['slug' => 'fortele', 'name' => 'Fortele Soluções Comerciais', 'category' => 'Sites', 'url' => 'https://www.fortele.com.br/'],
    ['slug' => 'unicalogomarcas', 'name' => 'Unica Logomarcas', 'category' => 'Sites', 'url' => 'https://www.unicalogomarcas.com.br/'],
    ['slug' => 'espaco-palavra', 'name' => 'Espaço Palavra', 'category' => 'Sites', 'url' => 'https://editoraespacopalavra.com.br/'],
    ['slug' => 'kasa-dos-reparos', 'name' => 'Kasa dos Reparos', 'category' => 'Sites', 'url' => 'https://kasadosreparos.com.br/'],
    ['slug' => 'unifiltra', 'name' => 'Unifiltra', 'category' => 'Sites', 'url' => 'https://unifiltra.com.br/'],
    ['slug' => 'roundover', 'name' => 'Roundover', 'category' => 'Sites', 'url' => 'https://approundover.com.br/'],
    ['slug' => 'procon', 'name' => 'Site ProconApp', 'category' => 'Sistemas', 'url' => 'https://proconapp.com.br/'],
    ['slug' => 'galdino', 'name' => 'Advocacia Galdino', 'category' => 'Sites', 'url' => 'http://galdino.adv.br/'],
];
?>

<section class="page-hero section-shell">
    <p class="eyebrow">IDEIAS EM MOVIMENTO</p>
    <h1>Novos contextos.<br><span class="brand-gradient">Novas possibilidades.</span></h1>
    <p class="page-lead">Explore as apresentações e navegue pelas categorias para encontrar referências de projetos digitais.</p>
</section>

<section class="section-shell portfolio-controls">
    <div class="filters" role="group" aria-label="Filtrar projetos">
        <button type="button" data-filter="Todos" aria-pressed="true">Todos</button>
        <button type="button" data-filter="Sites" aria-pressed="false">Sites</button>
        <button type="button" data-filter="Sistemas" aria-pressed="false">Sistemas</button>
        <button type="button" data-filter="Aplicativos" aria-pressed="false">Aplicativos</button>
    </div>
    <p class="portfolio-source-note">Imagens de referência do portfólio <a href="https://w3next.com/portfolio" target="_blank" rel="noopener noreferrer">W3NEXT</a>. Estes trabalhos não são apresentados como entregas da Moves.</p>
    <p id="filter-status" class="filter-status" aria-live="polite" data-singular="apresentação" data-plural="apresentações"><?= count($presentations) ?> apresentações</p>
</section>

<div class="parallax-gallery">
    <?php foreach ($presentations as $presentation): ?>
        <section class="project-showcase" data-category="<?= $this->e($presentation['category']) ?>">
            <img class="showcase-backdrop" src="<?= $this->e($this->asset('images/portfolio/' . $presentation['slug'] . '-bg.jpg')) ?>" alt="" aria-hidden="true">
            <div class="section-shell showcase-content">
                <a class="showcase-preview" href="<?= $this->e($presentation['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Visitar <?= $this->e($presentation['name']) ?>, referência W3NEXT">
                    <img src="<?= $this->e($this->asset('images/portfolio/' . $presentation['slug'] . '.png')) ?>" alt="Apresentação da página inicial de <?= $this->e($presentation['name']) ?>" loading="lazy">
                </a>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<section class="cta"><div class="cta-inner"><div><p class="eyebrow">SEU PRÓXIMO MOVIMENTO</p><h2>Vamos tirar sua<br>ideia do papel?</h2><p>Conte seu desafio. Construímos o próximo passo juntos.</p></div><a class="button button-primary large" href="mailto:contato@moves.com.br?subject=Novo%20projeto">Conversar sobre meu projeto ↗</a></div></section>
