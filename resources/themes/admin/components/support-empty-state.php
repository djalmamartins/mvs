<div class="support-empty-state">
    <span class="support-empty-icon"><i class="<?= $this->e($icon) ?>"></i></span>
    <h2><?= $this->e($heading) ?></h2>
    <p><?= $this->e($description) ?></p>
    <?php if (!empty($note)): ?><small><?= $this->e($note) ?></small><?php endif; ?>
</div>
