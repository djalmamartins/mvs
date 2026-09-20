<?php $this->layout('layouts/default', compact('title', 'description', 'canonical', 'robots', 'ogUrl', 'ogType', 'ogImage')); ?>
<div class="help-shell help-article-shell">
    <?php $this->insert('components/breadcrumb', ['items' => [
        ['label' => $article['product_name'], 'url' => '/help/products/' . rawurlencode((string) $article['product_slug'])],
        ['label' => $article['category_name'], 'url' => '/help/categories/' . rawurlencode((string) $article['category_slug'])],
        ['label' => $article['title']],
    ]]); ?>

    <div class="help-article-layout">
        <main class="help-article-main">
            <article class="help-article">
                <header>
                    <p class="help-eyebrow"><?= $this->e((string) $article['product_name']) ?> · <?= $this->e((string) $article['category_name']) ?></p>
                    <h1><?= $this->e((string) $article['title']) ?></h1>
                    <?php if (!empty($article['excerpt'])): ?>
                        <p class="article-lead"><?= $this->e((string) $article['excerpt']) ?></p>
                    <?php endif; ?>
                    <div class="article-meta">
                        <span><i class="icon-time-outline" aria-hidden="true"></i> <?= max(1, (int) $article['reading_time']) ?> min de leitura</span>
                        <span>Atualizado em <?= $this->e(date('d/m/Y', strtotime((string) $article['updated_at']))) ?></span>
                    </div>
                    <?php if (!empty($article['cover_media_id'])): ?>
                        <figure class="article-cover"><img src="/media/<?= (int) $article['cover_media_id'] ?>" alt="<?= $this->e((string) ($article['cover_alt'] ?: $article['title'])) ?>" loading="eager"></figure>
                    <?php endif; ?>
                </header>
                <div class="help-rich-content"><?= $article['rendered_content'] ?></div>
                <?php if ($tags !== []): ?>
                    <footer class="article-tags" aria-label="Tags">
                        <?php foreach ($tags as $tag): ?><span><?= $this->e((string) $tag['name']) ?></span><?php endforeach; ?>
                    </footer>
                <?php endif; ?>
            </article>

            <section class="help-feedback" id="article-feedback" aria-labelledby="feedback-title">
                <?php if (($_GET['feedback'] ?? '') === 'thanks'): ?>
                    <i class="icon-checkmark-circle-outline" aria-hidden="true"></i>
                    <div><h2 id="feedback-title">Obrigado pelo feedback.</h2><p>Sua resposta ajuda a melhorar a documentação do Moves.</p></div>
                <?php else: ?>
                    <div><h2 id="feedback-title">Esse artigo foi útil?</h2><p><?= (int) $feedback['total'] ?> resposta(s) registrada(s)</p></div>
                    <form method="post" action="/help/articles/<?= $this->e(rawurlencode((string) $article['slug'])) ?>/feedback">
                        <?= $this->csrf() ?>
                        <button type="submit" name="helpful" value="yes"><i class="icon-thumbs-up-outline" aria-hidden="true"></i> Sim</button>
                        <button type="submit" name="helpful" value="no"><i class="icon-thumbs-down-outline" aria-hidden="true"></i> Não</button>
                    </form>
                <?php endif; ?>
            </section>
        </main>

        <aside class="help-article-aside">
            <?php if ($toc !== []): ?>
                <nav class="help-toc" aria-label="Neste artigo">
                    <strong>Neste artigo</strong>
                    <?php foreach ($toc as $entry): ?><a class="level-<?= (int) $entry['level'] ?>" href="#<?= $this->e($entry['id']) ?>"><?= $this->e($entry['label']) ?></a><?php endforeach; ?>
                </nav>
            <?php endif; ?>
            <?php if ($sectionArticles !== []): ?>
                <nav class="help-section-articles" aria-label="Artigos nesta seção">
                    <strong>Artigos nesta seção</strong>
                    <?php foreach ($sectionArticles as $sectionArticle): ?>
                        <a class="<?= (int) $sectionArticle['id'] === (int) $article['id'] ? 'current' : '' ?>" href="/help/articles/<?= $this->e(rawurlencode((string) $sectionArticle['slug'])) ?>"<?= (int) $sectionArticle['id'] === (int) $article['id'] ? ' aria-current="page"' : '' ?>><?= $this->e((string) $sectionArticle['title']) ?></a>
                    <?php endforeach; ?>
                    <a class="view-all" href="/help/categories/<?= $this->e(rawurlencode((string) $article['category_slug'])) ?>">Ver todos</a>
                </nav>
            <?php endif; ?>
        </aside>
    </div>

    <?php if ($related !== []): ?>
        <section class="help-related">
            <header class="section-heading"><div><p class="help-eyebrow">CONTINUE EXPLORANDO</p><h2>Artigos relacionados</h2></div></header>
            <?php $this->insert('components/article-list', ['articles' => $related]); ?>
        </section>
    <?php endif; ?>
</div>

<dialog class="help-lightbox" aria-label="Visualização ampliada">
    <button type="button" aria-label="Fechar imagem"><i class="icon-close-outline" aria-hidden="true"></i></button>
    <figure><img alt=""><figcaption></figcaption></figure>
</dialog>
