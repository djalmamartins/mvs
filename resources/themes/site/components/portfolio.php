<?php

/** @var list<array<string, mixed>> $projects */
$projects = $projects ?? [];
$variant = $variant ?? 'showcase';
$assetPath = static function (mixed $value): ?string {
    $path = is_string($value) ? trim($value) : '';
    return preg_match('~^images/[a-zA-Z0-9/_\-.]+$~', $path) === 1 ? $path : null;
};
?>
<?php if ($projects === []): ?>
    <div class="portfolio-empty"><p>Nenhum projeto publicado neste momento.</p><a class="text-link" href="/contato">Começar um projeto ↗</a></div>
<?php elseif ($variant === 'cards'): ?>
    <div class="projects-grid portfolio-grid" data-portfolio>
        <?php foreach ($projects as $index => $project): $meta=$project['meta']??[]; $image=$assetPath($meta['backdrop']??$meta['image']??null); ?>
            <a class="project-card editorial-project portfolio-card-db" href="/projetos#projeto-<?= (int)$project['id'] ?>"<?= $image ? ' style="--portfolio-image:url(\''.$this->e($this->asset($image)).'\')"' : '' ?>>
                <div class="project-content"><span aria-hidden="true"><?= str_pad((string)($index+1),2,'0',STR_PAD_LEFT) ?></span><p class="project-kind"><?= $this->e($meta['kind']??$project['category_name']??'Projeto digital') ?></p><h3><?= $this->e($project['title']) ?></h3><p class="project-summary"><?= $this->e($project['excerpt']??'') ?></p></div>
            </a>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="parallax-gallery" data-portfolio>
        <?php foreach ($projects as $project): $meta=$project['meta']??[]; $image=$assetPath($meta['image']??null); $backdrop=$assetPath($meta['backdrop']??null); $url=trim((string)($meta['project_url']??'')); $external=filter_var($url,FILTER_VALIDATE_URL)!==false&&in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true); $internal=str_starts_with($url,'/')&&!str_starts_with($url,'//'); if(!$external&&!$internal){$url='';} ?>
            <section id="projeto-<?= (int)$project['id'] ?>" class="project-showcase" data-category="<?= $this->e($project['category_name']??'Outros') ?>">
                <?php if($backdrop):?><img class="showcase-backdrop" src="<?= $this->e($this->asset($backdrop)) ?>" alt="" aria-hidden="true" loading="lazy"><?php elseif($project['media_id']):?><img class="showcase-backdrop" src="/media/<?= (int)$project['media_id'] ?>" alt="" aria-hidden="true" loading="lazy"><?php endif;?>
                <div class="section-shell showcase-content">
                    <div class="showcase-caption"><div><span><?= $this->e(mb_strtoupper((string)($meta['kind']??$project['category_name']??'PROJETO'),'UTF-8')) ?></span><h2><?= $this->e($project['title']) ?></h2><p><?= $this->e($project['excerpt']??'') ?></p></div><?php if($url!==''):?><a class="text-link" href="<?= $this->e($url) ?>"<?= $external?' target="_blank" rel="noopener noreferrer"':'' ?>>Ver projeto ↗</a><?php endif;?></div>
                    <?php if($image||$project['media_id']):?><div class="showcase-preview"><?php if($image):?><img src="<?= $this->e($this->asset($image)) ?>" alt="Apresentação de <?= $this->e($project['title']) ?>" loading="lazy"><?php else:?><img src="/media/<?= (int)$project['media_id'] ?>" alt="<?= $this->e($project['alt_text']?:$project['title']) ?>" loading="lazy"><?php endif;?></div><?php endif;?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
