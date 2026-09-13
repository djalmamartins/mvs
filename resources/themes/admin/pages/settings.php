<?php

declare(strict_types=1);

/**
 * Moves | Settings Page
 *
 * Exibe o formulário administrativo de configurações.
 *
 * @author Djalma Martins
 */

$this->layout('layouts/default', ['title' => $title, 'currentPage' => 'settings']);
?>
<section class="studio-page">
    <header class="studio-page-heading"><div><p class="studio-eyebrow">SISTEMA</p><h2><?= $this->e($title) ?></h2><p>Identidade pública e canais institucionais, sem credenciais sensíveis.</p></div><div class="studio-actions"><a class="studio-btn" href="/admin/versions">Versões</a><a class="studio-btn" href="/admin/diagnostics">Diagnóstico</a></div></header>
    <form class="studio-settings-form" method="post" action="/admin/settings">
        <?= $this->csrf() ?>
        <section class="studio-panel"><header><div><p class="studio-eyebrow">GERAL</p><h2>Identidade e SEO</h2></div></header><div class="studio-panel-body studio-fields-two"><div class="studio-field"><label for="app_name">Nome da aplicação</label><input id="app_name" name="app_name" value="<?= $this->e($settings['app_name']) ?>" minlength="2" maxlength="100" required></div><div class="studio-field"><label for="site_title">Título público</label><input id="site_title" name="site_title" value="<?= $this->e($settings['site_title']) ?>" maxlength="160"><small>Usado como referência editorial e de SEO.</small></div><div class="studio-field studio-field-wide"><label for="site_description">Descrição pública</label><textarea id="site_description" name="site_description" maxlength="500" rows="4"><?= $this->e($settings['site_description']) ?></textarea></div></div></section>
        <section class="studio-panel"><header><div><p class="studio-eyebrow">CONTATO</p><h2>Canais institucionais</h2></div></header><div class="studio-panel-body studio-fields-two"><div class="studio-field"><label for="contact_email">E-mail de contato</label><input id="contact_email" type="email" name="contact_email" value="<?= $this->e($settings['contact_email']) ?>"></div><div class="studio-field"><label for="contact_phone">Telefone</label><input id="contact_phone" name="contact_phone" value="<?= $this->e($settings['contact_phone']) ?>" maxlength="30"></div><div class="studio-field"><label for="social_instagram">Instagram</label><input id="social_instagram" type="url" name="social_instagram" value="<?= $this->e($settings['social_instagram']) ?>" placeholder="https://instagram.com/..."></div><div class="studio-field"><label for="social_linkedin">LinkedIn</label><input id="social_linkedin" type="url" name="social_linkedin" value="<?= $this->e($settings['social_linkedin']) ?>" placeholder="https://linkedin.com/company/..."></div></div></section>
        <footer class="studio-settings-save"><span>As alterações ficam disponíveis para integração com o site.</span><button class="studio-btn primary" type="submit">Salvar configurações</button></footer>
    </form>
</section>
