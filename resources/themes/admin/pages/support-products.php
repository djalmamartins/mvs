<?php
declare(strict_types=1);
$this->layout('layouts/default', [
    'title' => $title,
    'productName' => 'Suporte',
    'activeProduct' => 'support',
    'currentPage' => 'products',
]);
$statusLabels = ['active' => 'Ativo', 'inactive' => 'Inativo'];
?>
<link rel="stylesheet" href="/themes/admin/css/support.css?v=20260918">
<script src="/themes/admin/js/support-knowledge.js?v=20260918" defer></script>
<section class="studio-page support-knowledge-page" data-knowledge-page="products">
    <header class="knowledge-header">
        <div>
            <p class="studio-eyebrow">MOVES STUDIO · SUPORTE</p>
            <h1>Base de conhecimento</h1>
            <p>Crie e gerencie conteúdos para ajudar seus usuários.</p>
        </div>
        <button class="studio-btn primary" type="button" data-knowledge-open="product"><?= studio_icon('briefcase') ?> Novo produto</button>
    </header>
    <nav class="support-knowledge-tabs" aria-label="Base de conhecimento"><a href="/support/articles"><?= studio_icon('newspaper') ?> Artigos</a><a href="/support/categories"><?= studio_icon('archive') ?> Categorias</a><a class="active" href="/support/products"><?= studio_icon('briefcase') ?> Produtos</a><a href="/support/tags"><?= studio_icon('tag') ?> Tags</a><span class="knowledge-tab-muted"><?= studio_icon('trash') ?> Lixeira</span></nav>
    <form class="studio-filter-bar" method="get">
        <label><span>Buscar</span><input type="search" name="q" value="<?= $this->e($search) ?>" placeholder="Nome ou descrição"></label>
        <label><span>Status</span><select name="status"><option value="">Todos</option><?php foreach ($statusLabels as $value => $label): ?><option value="<?= $value ?>"<?= $status === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <button class="studio-btn" type="submit">Filtrar</button><a class="studio-btn" href="/support/products">Limpar</a>
    </form>
    <div class="studio-table-wrap"><table><thead><tr><th>Produto</th><th>Slug</th><th>Categorias</th><th>Artigos</th><th>Status</th><th>Atualizado</th><th>Ações</th></tr></thead><tbody>
    <?php if ($products === []): ?><tr><td colspan="7" class="studio-empty">Nenhum produto encontrado.</td></tr><?php endif; ?>
    <?php foreach ($products as $product): ?><tr>
        <td><strong><?= $this->e((string) $product->name) ?></strong><small><?= $this->e((string) ($product->description ?? '')) ?></small></td>
        <td>/<?= $this->e((string) $product->slug) ?></td><td><?= (int) ($categoryCounts[(int) $product->id] ?? 0) ?></td><td><?= (int) ($articleCounts[(int) $product->id] ?? 0) ?></td>
        <td><span class="studio-status studio-status-<?= $product->status === 'active' ? 'success' : 'neutral' ?>"><?= $this->e($statusLabels[$product->status] ?? $product->status) ?></span></td>
        <td><?= $this->e((string) ($product->updated_at ?? '—')) ?></td>
        <td><button class="studio-icon-action" type="button" title="Editar" data-knowledge-edit="product" data-id="<?= (int) $product->id ?>" data-name="<?= $this->e((string) $product->name) ?>" data-description="<?= $this->e((string) ($product->description ?? '')) ?>" data-status="<?= $this->e((string) $product->status) ?>">✎</button>
            <form class="support-inline-form" method="post" action="/support/products/delete" data-confirm-submit="Excluir este produto?"><?= $this->csrf() ?><input type="hidden" name="id" value="<?= (int) $product->id ?>"><button class="studio-icon-action danger" type="submit" title="Excluir">×</button></form></td>
    </tr><?php endforeach; ?></tbody></table></div>
    <dialog class="support-modal" data-knowledge-dialog="product" aria-labelledby="product-modal-title"><form method="post" action="/support/products/save" data-knowledge-form data-reload-on-success>
        <?= $this->csrf() ?><input type="hidden" name="id" value="0"><input type="hidden" name="response" value="json"><h2 id="product-modal-title">Novo produto</h2><p class="support-modal-error" data-modal-error role="alert"></p>
        <label class="studio-field"><span>Nome</span><input name="name" maxlength="150" required></label><label class="studio-field"><span>Descrição</span><textarea name="description" rows="4"></textarea></label><label class="studio-field"><span>Status</span><select name="status"><option value="active">Ativo</option><option value="inactive">Inativo</option></select></label>
        <footer class="support-modal-actions"><button class="studio-btn" type="button" data-knowledge-close>Cancelar</button><button class="studio-btn primary" type="submit">Salvar produto</button></footer>
    </form></dialog>
</section>
