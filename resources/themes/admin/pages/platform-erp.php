<?php

declare(strict_types=1);

$this->layout('layouts/default', compact('title', 'productName', 'activeProduct', 'currentPage'));
?>
<section class="platform-page">
    <header class="studio-page-head"><div><p class="studio-eyebrow">ERP · CADASTROS</p><h1 class="studio-page-title">Administradora e condomínios</h1><p class="studio-page-description">Dados reais do tenant ativo e dos escopos autorizados.</p></div><div class="actions"><a class="studio-btn primary" href="/settings">Gerenciar cadastros</a></div></header>
    <section class="platform-metrics" aria-label="Indicadores do ERP">
        <article><span>Administradora</span><strong><?= $this->e((string) ($company['name'] ?? '—')) ?></strong><small><?= $this->e((string) ($company['status'] ?? 'sem status')) ?></small></article>
        <article><span>Condomínios</span><strong><?= count($condominiums) ?></strong><small>vinculados ao tenant atual</small></article>
        <article><span>Ativos</span><strong><?= count(array_filter($condominiums, static fn (array $item): bool => ($item['status'] ?? '') === 'active')) ?></strong><small>em operação</small></article>
    </section>
    <section class="platform-panel"><header><div><h2>Condomínios</h2><p>Lista isolada pela administradora selecionada.</p></div></header>
        <?php if ($condominiums === []): ?><div class="platform-empty"><strong>Nenhum condomínio cadastrado</strong><p>Use Configurações para criar o primeiro cadastro.</p></div><?php else: ?><div class="platform-table-wrap"><table><thead><tr><th>Condomínio</th><th>CNPJ</th><th>Cidade/UF</th><th>Status</th></tr></thead><tbody><?php foreach ($condominiums as $condominium): ?><tr><td><strong><?= $this->e((string) $condominium['legal_name']) ?></strong><small><?= $this->e((string) ($condominium['trade_name'] ?? '')) ?></small></td><td><?= $this->e((string) $condominium['tax_id']) ?></td><td><?= $this->e(trim((string) ($condominium['city'] ?? '') . '/' . (string) ($condominium['state'] ?? ''), '/')) ?></td><td><span class="platform-status"><?= $this->e((string) $condominium['status']) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
</section>
