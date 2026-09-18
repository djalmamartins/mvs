<?php

declare(strict_types=1);

$article = $article ?? null;
$id = (int) ($article?->id ?? 0);
$isEdit = $id > 0;

$titleValue = (string) ($article?->title ?? '');
$slugValue = (string) ($article?->slug ?? '');
$excerptValue = (string) ($article?->excerpt ?? '');
$contentValue = (string) ($article?->content ?? '');
$statusValue = (string) ($article?->status ?? 'draft');

$productId = $article?->product_id !== null
    ? (int) $article->product_id
    : null;

$categoryId = $article?->category_id !== null
    ? (int) $article->category_id
    : null;

$coverMediaId = $article?->cover_media_id !== null
    ? (int) $article->cover_media_id
    : null;

$metaTitle = (string) ($article?->meta_title ?? '');
$metaDescription = (string) ($article?->meta_description ?? '');
$focusKeyword = (string) ($article?->focus_keyword ?? '');
$canonicalUrl = (string) ($article?->canonical_url ?? '');

$robotsIndex = $article === null
    ? true
    : (bool) $article->robots_index;

$robotsFollow = $article === null
    ? true
    : (bool) $article->robots_follow;

$authorId = $article?->author_id !== null
    ? (int) $article->author_id
    : (int) (\Moves\Core\Auth::user()?->id ?? 0);

$wordCount = (int) ($article?->word_count ?? 0);
$readingTime = (int) ($article?->reading_time ?? 0);

$tagNames = [];

foreach (($tags ?? []) as $tag) {
    if (is_object($tag) && isset($tag->name)) {
        $tagNames[] = (string) $tag->name;
    } elseif (is_array($tag) && isset($tag['name'])) {
        $tagNames[] = (string) $tag['name'];
    }
}

$tagsValue = implode(', ', $tagNames);

$this->layout('layouts/default', [
    'title' => $isEdit ? 'Editar artigo' : 'Novo artigo',
    'productName' => 'Suporte',
    'activeProduct' => 'support',
    'currentPage' => 'articles',
    'hasMovesEditor' => true,
]);
?>

<div class="studio-page support-editor-page">

    <header class="studio-page-heading support-article-heading">
        <div>
            <div class="studio-eyebrow">BASE DE CONHECIMENTO</div>

            <h1>
                <?= $isEdit ? 'Editar artigo' : 'Novo artigo' ?>
            </h1>

            <p>
                Crie conteúdo de ajuda organizado, pesquisável
                e preparado para publicação.
            </p>
        </div>

        <a
            href="/support/articles"
            class="studio-btn"
        >
            Voltar
        </a>
    </header>

<form
        method="post"
        action="/support/articles/save"
        class="support-article-form"
        data-support-article-form
    >
        <?= \Moves\Core\Csrf::field() ?>

        <?php if ($isEdit): ?>
            <input
                type="hidden"
                name="id"
                value="<?= $id ?>"
            >
        <?php endif; ?>

        <div class="support-editor-layout">

            <main class="support-editor-main">

                <section class="studio-panel">
                    <div class="studio-panel-body">

                        <div class="studio-field">
                            <label for="support-title">
                                Título
                            </label>

                            <input
                                id="support-title"
                                type="text"
                                name="title"
                                maxlength="255"
                                value="<?= $this->e($titleValue) ?>"
                                placeholder="Ex.: Como emitir a segunda via do boleto"
                                required
                                data-support-title
                            >
                        </div>

                        <div class="studio-field">
                            <label for="support-slug">
                                Slug
                            </label>

                            <div class="support-slug-field">
                                <span>/ajuda/</span>

                                <input
                                    id="support-slug"
                                    type="text"
                                    name="slug"
                                    maxlength="280"
                                    value="<?= $this->e($slugValue) ?>"
                                    placeholder="como-emitir-segunda-via"
                                    data-support-slug
                                    data-existing-slug="<?= $this->e($slugValue) ?>"
                                >
                            </div>

                            <small>
                                A URL permanece estável. Altere o slug
                                somente quando necessário.
                            </small>
                        </div>

                        <div class="studio-field">
                            <label for="support-excerpt">
                                Resumo
                            </label>

                            <textarea
                                id="support-excerpt"
                                name="excerpt"
                                rows="3"
                                data-support-excerpt
                                placeholder="Explique em poucas linhas o que o leitor encontrará neste artigo."
                            ><?= $this->e($excerptValue) ?></textarea>
                        </div>

                    </div>
                </section>

                <section class="studio-panel support-content-panel">

                    <div class="support-panel-heading">
                        <div>
                            <h2>Conteúdo</h2>
                            <p>
                                Use títulos, listas, imagens, vídeos,
                                links, tabelas e outros recursos.
                            </p>
                        </div>

                        <div
                            class="support-reading-stats"
                            aria-live="polite"
                        >
                            <span data-support-word-count>
                                <?= number_format(
                                    $wordCount,
                                    0,
                                    ',',
                                    '.'
                                ) ?> palavras
                            </span>

                            <span>·</span>

                            <span data-support-reading-time>
                                <?= $readingTime ?> min de leitura
                            </span>
                        </div>
                    </div>

                    <textarea
                        id="support-content"
                        name="content"
                        data-editor="moves"
                        data-editor-document="support-article:<?= $isEdit
                            ? $this->e($slugValue)
                            : 'new' ?>"
                        data-editor-height="620"
                    ><?= $this->e($contentValue) ?></textarea>

                </section>

                <section class="studio-panel support-seo-panel">
                    <div class="studio-panel-body">

                        <div class="support-seo-heading">
                            <div>
                                <h2>SEO</h2>
                                <p>
                                    Configure as informações para
                                    mecanismos de busca.
                                </p>
                            </div>
                        </div>

                        <div class="studio-field">
                            <label for="support-focus-keyword">
                                Palavra-chave principal
                            </label>

                            <input
                                id="support-focus-keyword"
                                type="text"
                                name="focus_keyword"
                                maxlength="150"
                                value="<?= $this->e($focusKeyword) ?>"
                                placeholder="segunda via boleto"
                            >
                        </div>

                        <div class="studio-field">
                            <label for="support-meta-title">
                                Título SEO
                            </label>

                            <input
                                id="support-meta-title"
                                type="text"
                                name="meta_title"
                                maxlength="255"
                                value="<?= $this->e($metaTitle) ?>"
                                placeholder="Se vazio, usa o título do artigo"
                                data-support-meta-title
                            >

                            <small>
                                <span data-support-meta-title-count>
                                    <?= mb_strlen($metaTitle) ?>
                                </span>
                                caracteres
                            </small>
                        </div>

                        <div class="studio-field">
                            <label for="support-meta-description">
                                Meta description
                            </label>

                            <textarea
                                id="support-meta-description"
                                name="meta_description"
                                maxlength="320"
                                rows="4"
                                placeholder="Descrição exibida nos mecanismos de busca."
                                data-support-meta-description
                            ><?= $this->e($metaDescription) ?></textarea>

                            <small>
                                <span data-support-meta-description-count>
                                    <?= mb_strlen($metaDescription) ?>
                                </span>
                                / 320 caracteres
                            </small>
                        </div>

                        <div class="studio-field">
                            <label for="support-canonical">
                                URL canonical
                            </label>

                            <input
                                id="support-canonical"
                                type="url"
                                name="canonical_url"
                                maxlength="500"
                                value="<?= $this->e($canonicalUrl) ?>"
                                placeholder="https://..."
                            >
                        </div>

                        <div class="support-seo-indexing">

                            <label>
                                <input
                                    type="checkbox"
                                    name="robots_index"
                                    value="1"
                                    <?= $robotsIndex
                                        ? 'checked'
                                        : '' ?>
                                >
                                Permitir indexação
                            </label>

                            <label>
                                <input
                                    type="checkbox"
                                    name="robots_follow"
                                    value="1"
                                    <?= $robotsFollow
                                        ? 'checked'
                                        : '' ?>
                                >
                                Seguir links
                            </label>

                        </div>

                    </div>
                </section>

            </main>

            <aside class="support-editor-sidebar">

                <section class="studio-panel">
                    <div class="studio-panel-body">

                        <div class="support-panel-title">
                            Publicação
                        </div>

                        <div class="studio-field">
                            <label for="support-status">
                                Status
                            </label>

                            <select
                                id="support-status"
                                name="status"
                            >
                                <option
                                    value="draft"
                                    <?= $statusValue === 'draft'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Rascunho
                                </option>

                                <option
                                    value="published"
                                    <?= $statusValue === 'published'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Publicado
                                </option>

                                <option
                                    value="archived"
                                    <?= $statusValue === 'archived'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Arquivado
                                </option>
                            </select>
                        </div>

                        <?php if (
                            $isEdit
                            && !empty($article?->published_at)
                        ): ?>
                            <div class="support-publication-info">
                                Publicado em
                                <strong>
                                    <?= $this->e(
                                        (string) $article->published_at
                                    ) ?>
                                </strong>
                            </div>
                        <?php endif; ?>

                        <div class="studio-actions">
                            <button
                                type="submit"
                                class="studio-btn primary"
                            >
                                <?= $isEdit
                                    ? 'Salvar alterações'
                                    : 'Criar artigo' ?>
                            </button>
                        </div>

                    </div>
                </section>

                <section class="studio-panel">
                    <div class="studio-panel-body">

                        <div class="support-panel-title">
                            Classificação
                        </div>

                        <div class="studio-field">
                            <label for="support-product">
                                Produto
                            </label>

                            <select
                                id="support-product"
                                name="product_id"
                                data-support-product
                            >
                                <option value="">
                                    Sem produto
                                </option>

                                <?php foreach (($products ?? []) as $product): ?>
                                    <?php
                                    $currentProductId =
                                        (int) ($product->id ?? 0);
                                    ?>

                                    <option
                                        value="<?= $currentProductId ?>"
                                        <?= $productId === $currentProductId
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= $this->e(
                                            (string) ($product->name ?? '')
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button
                                type="button"
                                class="support-inline-create"
                                data-support-create-product
                            >
                                <span>⊕</span>
                                Criar produto
                            </button>
                        </div>

                        <div class="studio-field">
                            <label for="support-category">
                                Categoria
                            </label>

                            <select
                                id="support-category"
                                name="category_id"
                                data-support-category
                            >
                                <option value="">
                                    Sem categoria
                                </option>

                                <?php foreach (($categories ?? []) as $category): ?>
                                    <?php
                                    $currentCategoryId =
                                        (int) ($category->id ?? 0);

                                    $categoryProductId =
                                        $category->product_id !== null
                                            ? (int) $category->product_id
                                            : 0;
                                    ?>

                                    <option
                                        value="<?= $currentCategoryId ?>"
                                        data-product-id="<?= $categoryProductId ?>"
                                        <?= $categoryId === $currentCategoryId
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= $this->e(
                                            (string) ($category->name ?? '')
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button
                                type="button"
                                class="support-inline-create"
                                data-support-create-category
                            >
                                <span>⊕</span>
                                Criar categoria
                            </button>
                        </div>

                        <div class="studio-field">
                            <label for="support-author">
                                Autor
                            </label>

                            <select
                                id="support-author"
                                name="author_id"
                            >
                                <?php foreach (($authors ?? []) as $author): ?>
                                    <?php $currentAuthorId = (int) ($author->id ?? 0); ?>
                                    <option
                                        value="<?= $currentAuthorId ?>"
                                        <?= $authorId === $currentAuthorId ? 'selected' : '' ?>
                                    >
                                        <?= $this->e((string) ($author->name ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <small>
                                Autor atual do artigo.
                            </small>
                        </div>

                        <div class="studio-field">
                            <label for="support-tags">
                                Tags
                            </label>

                            <input
                                id="support-tags"
                                type="text"
                                name="tags"
                                value="<?= $this->e($tagsValue) ?>"
                                placeholder="boleto, financeiro, segunda via"
                            >

                            <small>
                                Separe as tags por vírgulas.
                            </small>
                        </div>

                    </div>
                </section>

                <section class="studio-panel">
                    <div class="studio-panel-body">

                        <div class="support-panel-title">
                            Imagem de capa
                        </div>

                        <input
                            type="hidden"
                            name="cover_media_id"
                            value="<?= $coverMediaId ?: '' ?>"
                            data-support-cover-id
                        >

                        <div
                            class="support-cover"
                            data-support-cover
                        >
                            <?php if ($coverMediaId): ?>
                                <img
                                    src="/media/<?= $coverMediaId ?>"
                                    alt=""
                                    data-support-cover-preview
                                >
                            <?php else: ?>
                                <div
                                    class="support-cover-empty"
                                    data-support-cover-empty
                                >
                                    Nenhuma imagem selecionada
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="support-cover-actions">
                            <button
                                type="button"
                                class="studio-btn"
                                data-support-cover-select
                            >
                                Escolher imagem
                            </button>

                            <button
                                type="button"
                                class="studio-btn"
                                data-support-cover-remove
                                <?= !$coverMediaId
                                    ? 'hidden'
                                    : '' ?>
                            >
                                Remover
                            </button>
                        </div>

                        <small>
                            A imagem utiliza a biblioteca oficial
                            de mídia do Moves.
                        </small>

                    </div>
                </section>



                <?php if ($isEdit): ?>
                    <section class="studio-panel">
                        <div class="studio-panel-body">

                            <div class="support-panel-title">
                                Revisões
                            </div>

                            <?php if (!empty($revisions)): ?>
                                <div class="support-revision-list">
                                    <?php
                                    foreach (
                                        array_slice($revisions, 0, 5)
                                        as $revision
                                    ):
                                    ?>
                                        <div class="support-revision-item">
                                            <strong>
                                                <?= $this->e(
                                                    (string) (
                                                        $revision->title
                                                        ?? 'Revisão'
                                                    )
                                                ) ?>
                                            </strong>

                                            <span>
                                                <?= $this->e(
                                                    (string) (
                                                        $revision->created_at
                                                        ?? ''
                                                    )
                                                ) ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="support-muted">
                                    Ainda não há revisões.
                                </p>
                            <?php endif; ?>

                        </div>
                    </section>
                <?php endif; ?>

            </aside>

        </div>
    </form>

</div>

<script src="/themes/admin/js/support-article-form.js?v=2026091701"></script>
