<?php
$heading = (string) ($heading ?? 'ERP');
$description = (string) ($description ?? 'Gestão financeira e operacional da administradora.');
$kicker = (string) ($kicker ?? 'MOVES · ERP');
?>
<header class="erp-page-head">
    <div><p class="erp-eyebrow"><?= $this->e($kicker) ?></p><h1><?= $this->e($heading) ?></h1><p><?= $this->e($description) ?></p></div>
    <?php if (!empty($actionHref) && !empty($actionLabel)): ?><a class="erp-button erp-button-primary" href="<?= $this->e($actionHref) ?>"><?= $this->e($actionLabel) ?></a><?php endif; ?>
</header>
