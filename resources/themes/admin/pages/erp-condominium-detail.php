<?php
declare(strict_types=1);
$this->layout('layouts/default', compact('title', 'productName', 'activeProduct', 'currentPage'));
$tax = \Moves\Services\Platform\Cnpj::format((string) ($condominium['tax_id'] ?? ''));
$address = trim(implode(', ', array_filter([(string) ($condominium['street'] ?? ''), (string) ($condominium['address_number'] ?? ''), (string) ($condominium['complement'] ?? ''), (string) ($condominium['district'] ?? ''), (string) ($condominium['city'] ?? ''), (string) ($condominium['state'] ?? ''), (string) ($condominium['postal_code'] ?? '')])));
$displayName = (string) ($condominium['trade_name'] ?: $condominium['legal_name']);
?>
<main class="erp-page erp-page-condominium-detail">
    <nav class="erp-breadcrumb"><a href="/erp/condominiums">Condomínios</a><span>/</span><span><?= $this->e($displayName) ?></span></nav>
    <?php $this->insert('components/erp-page-header', ['heading' => $displayName, 'description' => (string) $condominium['legal_name'], 'kicker' => 'CADASTRO · ERP', 'actionHref' => ($canEdit ?? false) ? '/erp/condominiums/' . (int) $condominium['id'] . '/edit' : null, 'actionLabel' => ($canEdit ?? false) ? 'Editar cadastro' : null]); ?>
    <section class="erp-panel erp-person-summary">
        <div><span>CNPJ</span><strong><?= $this->e($tax ?: 'Não informado') ?></strong></div>
        <div><span>Situação</span><strong><?= $condominium['status'] === 'active' ? 'Ativo' : 'Inativo' ?></strong></div>
        <div><span>Unidades</span><strong><?= (int) $condominium['unit_count'] ?></strong></div>
        <div><span>Síndico atual</span><strong><?= $this->e((string) ($manager['full_name'] ?? 'Não informado')) ?></strong></div>
        <div><span>Endereço</span><strong><?= $this->e($address ?: 'Não informado') ?></strong></div>
        <div><span>Contato</span><strong><?= $this->e(trim(implode(' · ', array_filter([(string) ($condominium['email'] ?? ''), (string) ($condominium['phone'] ?? '')]))) ?: 'Não informado') ?></strong></div>
    </section>
    <?php if (($missingCnpjTask ?? null) !== null): ?><section class="erp-panel erp-detail-section" aria-labelledby="condominium-pending-title"><header class="erp-panel-head"><div><h2 id="condominium-pending-title">Pendência operacional</h2><p>Este cadastro está sem CNPJ válido e permanece acompanhado no Meu Dia.</p></div><?php if($canEdit ?? false): ?><a class="erp-button" href="<?= $this->e((string) $missingCnpjTask['source_url']) ?>">Regularizar CNPJ</a><?php endif; ?></header><?php if($canManagePending ?? false): ?><p><a href="/erp/pending/<?= (int) $missingCnpjTask['id'] ?>">Ver pendência · <?= $this->e((string) $missingCnpjTask['status']) ?></a></p><?php endif; ?></section><?php endif; ?>
    <section class="erp-panel erp-detail-section">
        <header class="erp-panel-head"><div><h2>Unidades</h2><p><?= count($units) ?> unidade(s) cadastrada(s)</p></div><a class="erp-button" href="/erp/units/new">Cadastrar unidade</a></header>
        <?php if ($units === []): ?>
            <div class="erp-empty-state"><strong>Nenhuma unidade cadastrada</strong><p>As unidades associadas a este condomínio aparecerão aqui.</p></div>
        <?php else: ?>
            <div class="erp-table-wrap"><table class="erp-responsive-directory-table">
                <thead><tr><th>Unidade</th><th>Bloco / torre</th><th>Complemento</th><th>Situação</th></tr></thead>
                <tbody><?php foreach ($units as $unit): ?><tr>
                    <td data-label="Unidade"><a class="erp-record-link" href="/erp/units/<?= (int) $unit['id'] ?>"><?= $this->e((string) $unit['code']) ?></a></td>
                    <td data-label="Bloco / torre"><?= $this->e((string) ($unit['block_name'] ?: '—')) ?></td>
                    <td data-label="Complemento"><?= $this->e((string) ($unit['complement'] ?: '—')) ?></td>
                    <td data-label="Situação"><?= $unit['status'] === 'active' ? 'Ativa' : 'Inativa' ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
    <section class="erp-panel erp-detail-section">
        <header class="erp-panel-head"><div><h2>Pessoas e vínculos</h2><p>Proprietários, moradores, síndico e histórico deste condomínio.</p></div></header>
        <?php if ($people === []): ?>
            <div class="erp-empty-state"><strong>Nenhum vínculo cadastrado</strong><p>Os vínculos criados em Pessoas aparecerão nesta seção.</p></div>
        <?php else: ?>
            <div class="erp-table-wrap"><table class="erp-responsive-directory-table">
                <thead><tr><th>Pessoa</th><th>Vínculo</th><th>Unidade</th><th>Período</th><th>Situação</th></tr></thead>
                <tbody><?php foreach ($people as $person): ?><tr>
                    <td data-label="Pessoa"><a class="erp-record-link" href="/erp/people/<?= (int) $person['person_id'] ?>"><?= $this->e((string) $person['full_name']) ?></a></td>
                    <td data-label="Vínculo"><?= $this->e($roles[$person['role']] ?? (string) $person['role']) ?></td>
                    <td data-label="Unidade"><?= $this->e((string) ($person['unit_code'] ?: 'Condomínio')) ?></td>
                    <td data-label="Período"><?= $this->e((string) $person['starts_at']) ?> → <?= $this->e((string) ($person['ends_at'] ?: 'Atual')) ?></td>
                    <td data-label="Situação"><?= $person['status'] === 'active' ? 'Ativo' : 'Histórico' ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
    <section class="erp-future-grid" aria-label="Áreas futuras do condomínio"><article><strong>Financeiro</strong><span>Disponível em uma próxima etapa</span></article><article><strong>Contas bancárias</strong><span>Disponível em uma próxima etapa</span></article><article><strong>Cobranças</strong><span>Disponível em uma próxima etapa</span></article><article><strong>Documentos e relatórios</strong><span>Disponível em uma próxima etapa</span></article></section>
</main>
