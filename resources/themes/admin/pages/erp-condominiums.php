<?php declare(strict_types=1); $this->layout('layouts/default', compact('title', 'productName', 'activeProduct', 'currentPage')); ?>
<main class="erp-page erp-page-condominiums">
    <?php $this->insert('components/erp-page-header', ['heading' => 'Condomínios', 'description' => 'Cadastros reais vinculados à administradora atual.', 'kicker' => 'MOVES · ERP', 'actionHref' => '/erp/condominiums/new', 'actionLabel' => 'Cadastrar condomínio']); ?>
    <form method="get" action="/erp/condominiums" class="erp-filter-bar" role="search">
        <label class="erp-search-field"><span class="sr-only">Buscar condomínio</span><i class="icon-search-outline" aria-hidden="true"></i><input type="search" name="q" value="<?= $this->e($filters['q']) ?>" placeholder="Nome ou CNPJ"></label>
        <label><span class="sr-only">Situação</span><select name="status"><option value="">Todas as situações</option><option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Ativo</option><option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inativo</option></select></label>
        <button class="erp-button" type="submit">Filtrar</button>
    </form>
    <section class="erp-panel erp-data-panel">
        <header class="erp-panel-head"><div><h2>Condomínios cadastrados</h2><p><?= count($condominiums) ?> registro(s) · dados reais desta administradora</p></div></header>
        <?php if ($condominiums === []): ?>
            <div class="erp-empty-state"><strong>Nenhum condomínio encontrado</strong><p>Cadastre um condomínio ou ajuste a busca. Os registros são compartilhados com as telas de pessoas e unidades do tenant.</p><a class="erp-button erp-button-primary" href="/erp/condominiums/new">Cadastrar condomínio</a></div>
        <?php else: ?>
            <div class="erp-table-wrap"><table class="erp-responsive-directory-table">
                <thead><tr><th>Condomínio</th><th>CNPJ</th><th>Endereço</th><th>Unidades</th><th>Síndico atual</th><th>Situação</th></tr></thead>
                <tbody><?php foreach ($condominiums as $row):
                    $address = trim(implode(', ', array_filter([(string) ($row['street'] ?? ''), (string) ($row['address_number'] ?? ''), (string) ($row['city'] ?? ''), (string) ($row['state'] ?? '')])));
                    $tax = preg_replace('/\D+/', '', (string) ($row['tax_id'] ?? '')) ?? '';
                    $formattedTax = strlen($tax) === 14 ? substr($tax, 0, 2) . '.' . substr($tax, 2, 3) . '.' . substr($tax, 5, 3) . '/' . substr($tax, 8, 4) . '-' . substr($tax, 12) : '—';
                ?>
                    <tr>
                        <td data-label="Condomínio"><a class="erp-record-link" href="/erp/condominiums/<?= (int) $row['id'] ?>"><strong><?= $this->e((string) ($row['trade_name'] ?: $row['legal_name'])) ?></strong><?php if ($row['trade_name']): ?><small><?= $this->e((string) $row['legal_name']) ?></small><?php endif; ?></a></td>
                        <td data-label="CNPJ"><?= $formattedTax ?></td>
                        <td data-label="Endereço"><?= $this->e($address ?: 'Não informado') ?></td>
                        <td data-label="Unidades"><?= (int) $row['unit_count'] ?></td>
                        <td data-label="Síndico atual"><?= $this->e((string) ($row['manager_name'] ?: 'Não informado')) ?></td>
                        <td data-label="Situação"><span class="erp-status <?= $row['status'] === 'active' ? 'ativo' : '' ?>"><?= $row['status'] === 'active' ? 'Ativo' : 'Inativo' ?></span></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <footer class="erp-table-foot">Endereço, unidades e síndico usam os mesmos cadastros canônicos do ERP.</footer>
        <?php endif; ?>
    </section>
</main>
