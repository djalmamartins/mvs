<header class="support-workspace-head">
    <div>
        <span class="support-kicker"><?= $this->e($kicker ?? 'Moves Studio · Suporte') ?></span>
        <h1><?= $this->e($heading) ?></h1>
        <p><?= $this->e($description ?? '') ?></p>
    </div>
    <?php if (!empty($actionHref) && !empty($actionLabel)): ?>
        <a class="support-primary-action" href="<?= $this->e($actionHref) ?>"><i class="<?= $this->e($actionIcon ?? 'icon-add') ?>"></i><?= $this->e($actionLabel) ?></a>
    <?php endif; ?>
</header>
