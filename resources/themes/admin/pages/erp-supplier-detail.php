<?php
declare(strict_types=1);
$this->layout('layouts/default', compact('title', 'productName', 'activeProduct', 'currentPage'));
$document = $supplier['document_type'] === 'cpf' ? 'CPF' : ($supplier['document_type'] === 'cnpj' ? 'CNPJ' : 'Documento');
?>
<main class="erp-page erp-page-supplier-detail">
    <nav class="erp-breadcrumb"><a href="/erp/suppliers">Fornecedores</a><span>/</span><span><?= $this->e((string) $supplier['full_name']) ?></span></nav>
    <?php $this->insert('components/erp-page-header', ['heading' => (string) $supplier['full_name'], 'description' => $supplier['entity_type'] === 'organization' ? 'Pessoa jurídica' : 'Pessoa física', 'kicker' => 'CADASTRO · ERP', 'actionHref' => '/erp/people/' . (int) $supplier['person_id'], 'actionLabel' => 'Ver pessoa']); ?>
    <section class="erp-panel erp-person-summary">
        <div><span>CPF/CNPJ</span><strong><?= $this->e($supplier['document_type'] ? $document . ' · ' . (string) $supplier['document_number'] : 'Não informado') ?></strong></div>
        <div><span>Nome fantasia</span><strong><?= $this->e((string) ($supplier['trade_name'] ?: '—')) ?></strong></div>
        <div><span>Contato</span><strong><?= $this->e(trim(implode(' · ', array_filter([(string) ($supplier['email'] ?? ''), (string) ($supplier['phone'] ?? '')]))) ?: 'Não informado') ?></strong></div>
        <div><span>Categoria</span><strong><?= $this->e((string) $supplier['category_name']) ?></strong></div>
        <div><span>Situação</span><strong><?= $supplier['status'] === 'active' ? 'Ativo' : 'Inativo' ?></strong></div>
    </section>
    <section class="erp-panel erp-detail-section">
        <header class="erp-panel-head"><div><h2>Condomínios atendidos</h2><p>O vínculo é compartilhado e mantém o histórico de atendimento.</p></div></header>
        <?php if ($links === []): ?>
            <div class="erp-empty-state"><strong>Nenhum condomínio associado</strong><p>Associe os condomínios atendidos por este fornecedor.</p></div>
        <?php else: ?>
            <div class="erp-table-wrap"><table class="erp-responsive-directory-table">
                <thead><tr><th>Condomínio</th><th>Período</th><th>Situação</th><th>Ação</th></tr></thead>
                <tbody><?php foreach ($links as $link): ?>
                    <tr>
                        <td data-label="Condomínio"><a class="erp-record-link" href="/erp/condominiums/<?= (int) $link['condominium_id'] ?>"><?= $this->e((string) ($link['trade_name'] ?: $link['legal_name'])) ?></a></td>
                        <td data-label="Período"><?= $this->e((string) $link['starts_at']) ?> → <?= $this->e((string) ($link['ends_at'] ?: 'Atual')) ?></td>
                        <td data-label="Situação"><?= $link['status'] === 'active' ? 'Ativo' : 'Histórico' ?></td>
                        <td data-label="Ação">
                            <?php if ($link['status'] === 'active' && $supplier['status'] === 'active'): ?>
                                <form method="post" action="/erp/suppliers/<?= (int) $supplier['id'] ?>/condominiums/<?= (int) $link['id'] ?>/close" class="erp-inline-form"><?= $this->csrf() ?><label class="sr-only" for="supplier-end-<?= (int) $link['id'] ?>">Fim do atendimento</label><input id="supplier-end-<?= (int) $link['id'] ?>" type="date" name="ends_at" value="<?= date('Y-m-d') ?>" required><button class="erp-button" type="submit">Encerrar</button></form>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
    <?php if ($supplier['status'] === 'active'): ?>
        <section class="erp-panel erp-detail-section">
            <header class="erp-panel-head"><div><h2>Associar condomínio</h2><p>Selecione outro condomínio desta administradora.</p></div></header>
            <form method="post" action="/erp/suppliers/<?= (int) $supplier['id'] ?>/condominiums" class="erp-form-panel"><?= $this->csrf() ?><div class="erp-form-grid"><label>Condomínio<select name="condominium_id" required><option value="">Selecione</option><?php foreach ($condominiums as $condominium): ?><option value="<?= (int) $condominium['id'] ?>"><?= $this->e((string) ($condominium['trade_name'] ?: $condominium['legal_name'])) ?></option><?php endforeach; ?></select></label><label>Início do atendimento<input type="date" name="starts_at" value="<?= date('Y-m-d') ?>" required></label><label>Fim do atendimento (opcional)<input type="date" name="ends_at"></label></div><footer class="erp-form-actions"><button class="erp-button erp-button-primary" type="submit">Associar condomínio</button></footer></form>
        </section>
    <?php endif; ?>
    <section class="erp-future-grid" aria-label="Áreas futuras do fornecedor"><article><strong>Contas a pagar</strong><span>Disponível em uma próxima etapa</span></article><article><strong>Contratos e documentos</strong><span>Disponível em uma próxima etapa</span></article><article><strong>Ordens de serviço</strong><span>Disponível em uma próxima etapa</span></article><article><strong>Histórico</strong><span>Vínculos temporais preservados</span></article></section>
    <form method="post" action="/erp/suppliers/<?= (int) $supplier['id'] ?>/status" class="erp-status-form"><?= $this->csrf() ?><input type="hidden" name="status" value="<?= $supplier['status'] === 'active' ? 'inactive' : 'active' ?>"><button class="erp-button" type="submit"><?= $supplier['status'] === 'active' ? 'Inativar fornecedor' : 'Reativar fornecedor' ?></button></form>
</main>
