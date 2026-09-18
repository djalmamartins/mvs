<?php

declare(strict_types=1);

$this->layout('layouts/default', [
    'title' => $title,
    'productName' => 'Suporte',
    'activeProduct' => 'support',
    'currentPage' => 'articles',
]);

$productNames = [];

foreach ($products as $product) {
    $productNames[(int) $product->id] = (string) $product->name;
}

$categoryNames = [];

foreach ($categories as $category) {
    $categoryNames[(int) $category->id] = (string) $category->name;
}

$statusLabels = [
    'draft' => 'Rascunho',
    'published' => 'Publicado',
    'archived' => 'Arquivado',
];

$statusTones = [
    'draft' => 'warning',
    'published' => 'success',
    'archived' => 'neutral',
];
?>

<section class="studio-page">

    <header class="studio-page-heading">
        <div>
            <p class="studio-eyebrow">BASE DE CONHECIMENTO</p>
            <h2>Artigos</h2>
            <p>
                Crie, organize e publique conteúdos de ajuda para
                os produtos da plataforma Moves.
            </p>
        </div>

        <a
            class="studio-btn primary"
            href="/support/articles/create"
        >
            Novo artigo
        </a>
    </header>

    <form
        class="studio-filter-bar"
        method="get"
        action="/support/articles"
    >
        <label>
            <span>Buscar</span>

            <input
                type="search"
                name="q"
                value="<?= $this->e((string) $search) ?>"
                placeholder="Título ou conteúdo"
            >
        </label>

        <label>
            <span>Status</span>

            <select name="status">
                <option value="">Todos</option>

                <?php foreach ($statusLabels as $value => $label): ?>
                    <option
                        value="<?= $this->e($value) ?>"
                        <?= $status === $value ? ' selected' : '' ?>
                    >
                        <?= $this->e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Produto</span>

            <select name="product">
                <option value="0">Todos</option>

                <?php foreach ($products as $product): ?>
                    <option
                        value="<?= (int) $product->id ?>"
                        <?= $productId === (int) $product->id
                            ? ' selected'
                            : '' ?>
                    >
                        <?= $this->e((string) $product->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Categoria</span>

            <select name="category">
                <option value="0">Todas</option>

                <?php foreach ($categories as $category): ?>
                    <option
                        value="<?= (int) $category->id ?>"
                        <?= $categoryId === (int) $category->id
                            ? ' selected'
                            : '' ?>
                    >
                        <?= $this->e((string) $category->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <button type="submit">
            Filtrar
        </button>

        <a href="/support/articles">
            Limpar
        </a>
    </form>

    <div class="studio-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Artigo</th>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th>Status</th>
                    <th>Atualizado</th>
                    <th>Ações</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($articles === []): ?>
                    <tr>
                        <td
                            colspan="6"
                            class="studio-empty"
                        >
                            Nenhum artigo encontrado.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($articles as $article): ?>
                    <?php
                    $articleStatus = (string) $article->status;
                    $tone = $statusTones[$articleStatus] ?? 'neutral';
                    ?>

                    <tr>
                        <td>
                            <strong>
                                <?= $this->e((string) $article->title) ?>
                            </strong>

                            <?php if (
                                trim((string) $article->excerpt) !== ''
                            ): ?>
                                <small>
                                    <?= $this->e(
                                        (string) $article->excerpt
                                    ) ?>
                                </small>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= $this->e(
                                $productNames[
                                    (int) $article->product_id
                                ] ?? '—'
                            ) ?>
                        </td>

                        <td>
                            <?= $this->e(
                                $categoryNames[
                                    (int) $article->category_id
                                ] ?? '—'
                            ) ?>
                        </td>

                        <td>
                            <span
                                class="studio-status studio-status-<?= $this->e($tone) ?>"
                            >
                                <?= $this->e(
                                    $statusLabels[$articleStatus]
                                    ?? $articleStatus
                                ) ?>
                            </span>
                        </td>

                        <td>
                            <?= $this->e(
                                (string) (
                                    $article->updated_at ?? '—'
                                )
                            ) ?>
                        </td>

                        <td>
                            <a
                                class="studio-icon-action"
                                href="/support/articles/<?= $this->e(rawurlencode((string) $article->slug)) ?>/edit"
                                title="Editar"
                                aria-label="Editar <?= $this->e((string) $article->title) ?>"
                            >
                                <i class="icon-pencil-outline" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</section>