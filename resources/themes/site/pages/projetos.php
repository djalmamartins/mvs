<?php

/** Moves | Public projects page. */

$projects = $projects ?? [];
$categories = array_values(array_unique(array_filter(array_map(
    static fn (array $project): string => (string) ($project['category_name'] ?? ''),
    $projects
))));

$this->layout('layouts/default', [
    'title' => $title,
    'description' => $description,
    'canonical' => $canonical ?? null,
    'robots' => $robots ?? 'noindex, nofollow',
    'currentPage' => 'projects',
]);
?>

<section class="page-hero section-shell">
    <p class="eyebrow">IDEIAS EM MOVIMENTO</p>
    <h1>Novos contextos.<br><span class="brand-gradient">Novas possibilidades.</span></h1>
    <p class="page-lead">Explore projetos publicados pelo Moves Studio e encontre referências para o seu próximo movimento.</p>
</section>

<section class="section-shell portfolio-controls">
    <div class="filters" role="group" aria-label="Filtrar projetos">
        <button type="button" data-filter="Todos" aria-pressed="true">Todos</button>
        <?php foreach($categories as $category):?><button type="button" data-filter="<?= $this->e($category) ?>" aria-pressed="false"><?= $this->e($category) ?></button><?php endforeach;?>
    </div>
    <p id="filter-status" class="filter-status" aria-live="polite" data-singular="projeto" data-plural="projetos"><?= count($projects) ?> projetos</p>
</section>

<?php $this->insert('components/portfolio',['projects'=>$projects,'variant'=>'showcase']); ?>

<section class="cta"><div class="cta-inner"><div><p class="eyebrow">SEU PRÓXIMO MOVIMENTO</p><h2>Vamos tirar sua<br>ideia do papel?</h2><p>Conte seu desafio. Construímos o próximo passo juntos.</p></div><a class="button button-primary large" href="/contato">Conversar sobre meu projeto ↗</a></div></section>
