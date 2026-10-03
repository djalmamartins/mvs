<?php

declare(strict_types=1);

$this->layout('layouts/default', compact('title', 'productName', 'activeProduct', 'currentPage'));
$firstName = trim(explode(' ', (string) $user->name)[0] ?? '');
$apps = [
    'talk' => ['Talk', '/talk', 'Atendimento e conversas'],
    'support' => ['Support', '/support', 'Base de conhecimento'],
    'erp' => ['ERP', '/erp', 'Administradora e condomínios'],
    'studio' => ['Studio', '/studio', 'Conteúdo e presença digital'],
];
$enabledProducts = array_filter(
    array_intersect_key($products, $apps),
    static fn (bool $enabled): bool => $enabled
);
?>
<section class="platform-page">
    <header class="studio-page-head">
        <div><p class="studio-eyebrow">HOME OPERACIONAL</p><h1 class="studio-page-title">Olá<?= $firstName !== '' ? ', ' . $this->e($firstName) : '' ?>!</h1><p class="studio-page-description"><?= is_array($company) ? 'Resumo da operação de ' . $this->e((string) $company['name']) . '.' : 'Sua conta ainda não está vinculada a uma administradora ativa.' ?></p></div>
    </header>

    <?php if ($mfaRecommendation): ?>
        <aside class="platform-security-nudge" aria-label="Recomendação de segurança">
            <div><strong>Proteja sua conta.</strong><span>Ative a verificação em duas etapas para aumentar a segurança.</span></div>
            <div class="platform-inline-actions"><a class="studio-btn primary" href="/profile/security/2fa">Ativar agora</a><form method="post" action="/profile/security/2fa/recommendation/dismiss"><?= $this->csrf() ?><button class="studio-btn" type="submit">Agora não</button></form></div>
        </aside>
    <?php endif; ?>

    <?php if (!is_array($company)): ?>
        <section class="platform-empty"><strong>Nenhuma administradora disponível</strong><p>Peça ao administrador para concluir seu vínculo de acesso. Nenhum dado de outro tenant foi exibido.</p></section>
    <?php else: ?>
        <section class="platform-metrics" aria-label="Indicadores da administradora">
            <article><span>Administradora</span><strong><?= $this->e((string) $company['name']) ?></strong><small><?= $this->e((string) ($company['city'] ?: 'Localização não informada')) ?></small></article>
            <article><span>Equipe</span><strong><?= count($members) ?></strong><small>membros vinculados</small></article>
            <article><span>Condomínios</span><strong><?= count($condominiums) ?></strong><small>cadastros do tenant</small></article>
            <article><span>Aplicativos</span><strong><?= count($enabledProducts) ?></strong><small>produtos habilitados</small></article>
        </section>
        <section class="platform-panel"><header><div><h2>Aplicativos da operação</h2><p>Somente produtos habilitados para a administradora atual.</p></div></header><div class="platform-app-grid">
            <?php foreach ($apps as $key => [$label, $href, $description]): ?><?php if (!($products[$key] ?? false)) { continue; } ?><a href="<?= $href ?>"><strong><?= $this->e($label) ?></strong><span><?= $this->e($description) ?></span></a><?php endforeach; ?>
        </div></section>
    <?php endif; ?>
</section>
