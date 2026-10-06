<?php declare(strict_types=1); $this->layout('layouts/default', compact('title', 'productName', 'activeProduct', 'currentPage')); ?>
<main class="erp-page erp-page-suppliers">
    <?php $this->insert('components/erp-page-header', ['heading' => 'Fornecedores', 'description' => 'Pessoas físicas e jurídicas cadastradas pela administradora.', 'kicker' => 'MOVES · ERP', 'actionHref' => '/erp/suppliers/new', 'actionLabel' => 'Cadastrar fornecedor']); ?>
    <form method="get" action="/erp/suppliers" class="erp-filter-bar" role="search">
        <label class="erp-search-field"><span class="sr-only">Buscar fornecedor</span><i class="icon-search-outline" aria-hidden="true"></i><input type="search" name="q" value="<?= $this->e($filters['q']) ?>" placeholder="Nome ou CPF/CNPJ"></label>
        <label><span class="sr-only">Categoria</span><select name="category"><option value="">Todas as categorias</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= $filters['category'] === (string) $category['id'] ? 'selected' : '' ?>><?= $this->e((string) $category['name']) ?></option><?php endforeach; ?></select></label>
        <label><span class="sr-only">Condomínio</span><select name="condominium"><option value="">Todos os condomínios</option><?php foreach ($condominiums as $condominium): ?><option value="<?= (int) $condominium['id'] ?>" <?= $filters['condominium'] === (string) $condominium['id'] ? 'selected' : '' ?>><?= $this->e((string) ($condominium['trade_name'] ?: $condominium['legal_name'])) ?></option><?php endforeach; ?></select></label>
        <label><span class="sr-only">Situação</span><select name="status"><option value="">Todas as situações</option><option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Ativo</option><option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inativo</option></select></label>
        <button class="erp-button" type="submit">Filtrar</button>
    </form>
    <section class="erp-panel erp-data-panel">
        <header class="erp-panel-head"><div><h2>Fornecedores cadastrados</h2><p><?= count($suppliers) ?> registro(s) · dados reais desta administradora</p></div></header>
        <?php if ($suppliers === []): ?>
            <div class="erp-empty-state"><strong>Nenhum fornecedor encontrado</strong><p>Cadastre um fornecedor ou ajuste os filtros. Os dados não incluem lançamentos financeiros demonstrativos.</p><a class="erp-button erp-button-primary" href="/erp/suppliers/new">Cadastrar fornecedor</a></div>
        <?php else: ?>
            <div class="erp-table-wrap"><table class="erp-responsive-directory-table">
                <thead><tr><th>Fornecedor</th><th>CPF/CNPJ</th><th>Contato</th><th>Condomínios atendidos</th><th>Categoria</th><th>Situação</th></tr></thead>
                <tbody><?php foreach ($suppliers as $supplier): ?>
                    <tr>
                        <td data-label="Fornecedor"><a class="erp-record-link" href="/erp/suppliers/<?= (int) $supplier['id'] ?>"><strong><?= $this->e((string) $supplier['full_name']) ?></strong><?php if ($supplier['trade_name']): ?><small><?= $this->e((string) $supplier['trade_name']) ?></small><?php endif; ?></a></td>
                        <td data-label="CPF/CNPJ"><?= $this->e((string) $supplier['document_display']) ?></td>
                        <td data-label="Contato"><?= $this->e((string) $supplier['contact_display']) ?></td>
                        <td data-label="Condomínios atendidos"><?= $this->e((string) $supplier['condominiums_label']) ?></td>
                        <td data-label="Categoria"><?= $this->e((string) $supplier['category_name']) ?></td>
                        <td data-label="Situação"><span class="erp-status <?= $supplier['status'] === 'active' ? 'ativo' : '' ?>"><?= $supplier['status'] === 'active' ? 'Ativo' : 'Inativo' ?></span></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <footer class="erp-table-foot">A identidade do fornecedor é compartilhada com Pessoas. Um fornecedor pode atender mais de um condomínio.</footer>
        <?php endif; ?>
    </section>
</main>
