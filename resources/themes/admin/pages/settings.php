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
    <header class="studio-page-heading"><div><p class="studio-eyebrow">SISTEMA</p><h2><?= $this->e($title) ?></h2><p>Controle a identidade, comunicação, aparência e os módulos do MovesOS.</p></div><div class="studio-actions"><a class="studio-btn" href="/studio/versions"><?= studio_icon('tag') ?> Versões</a><a class="studio-btn" href="/" target="_blank" rel="noopener"><?= studio_icon('external-link') ?> Ver site</a></div></header>
    <section class="studio-settings-summary"><article><?= studio_icon('globe') ?><div><span>Site público</span><strong><?= $this->e($settings['app_name']) ?></strong><small><?= $this->e((string) config('APP_URL', '/')) ?></small></div></article><article><?= studio_icon('sparkles') ?><div><span>Tema ativo</span><strong>default</strong><small>Aparência pública</small></div></article><article><?= studio_icon('file-text') ?><div><span>E-mail</span><strong><?= $settings['contact_email']!==''?'Configurado':'Pendente' ?></strong><small><?= $this->e($settings['contact_email']?:'Nenhum endereço') ?></small></div></article><article><?= studio_icon('settings') ?><div><span>Ambiente</span><strong><?= $this->e(ucfirst((string)config('APP_ENV','produção'))) ?></strong><small>Moves Studio</small></div></article></section>
    <form class="studio-settings-form" method="post" action="/studio/settings">
        <?= $this->csrf() ?>
        <div class="studio-settings-layout"><nav class="studio-settings-nav" aria-label="Seções das configurações"><button class="active" type="button"><?= studio_icon('settings') ?><span><strong>Geral</strong><small>Identidade e domínio</small></span></button><button type="button"><?= studio_icon('file-text') ?><span><strong>Contato</strong><small>Telefones e endereço</small></span></button><button type="button"><?= studio_icon('message-square') ?><span><strong>E-mail</strong><small>Servidor e remetente</small></span></button><button type="button"><?= studio_icon('sparkles') ?><span><strong>Aparência</strong><small>Temas e arquivos</small></span></button><button type="button"><?= studio_icon('globe') ?><span><strong>Redes sociais</strong><small>Perfis institucionais</small></span></button><button type="button"><?= studio_icon('apps') ?><span><strong>Módulos</strong><small>Controle de acesso</small></span></button></nav>
        <div class="studio-stack"><section class="studio-panel"><header><div><h2>Identidade e publicação</h2><p>Informações principais usadas no site, títulos e compartilhamentos.</p></div><?= studio_icon('sparkles') ?></header><div class="studio-panel-body studio-fields-two"><div class="studio-field"><label for="app_name">Nome do site</label><input id="app_name" name="app_name" value="<?= $this->e($settings['app_name']) ?>" minlength="2" maxlength="100" required></div><div class="studio-field"><label for="site_title">Título complementar</label><input id="site_title" name="site_title" value="<?= $this->e($settings['site_title']) ?>" maxlength="160"></div><div class="studio-field studio-field-wide"><label for="site_description">Descrição do site</label><textarea id="site_description" name="site_description" maxlength="500" rows="4"><?= $this->e($settings['site_description']) ?></textarea></div></div></section>
        <section class="studio-panel"><header><div><h2>Canais institucionais</h2><p>Dados públicos de contato e redes sociais.</p></div></header><div class="studio-panel-body studio-fields-two"><div class="studio-field"><label for="contact_email">E-mail de contato</label><input id="contact_email" type="email" name="contact_email" value="<?= $this->e($settings['contact_email']) ?>"></div><div class="studio-field"><label for="contact_phone">Telefone</label><input id="contact_phone" name="contact_phone" value="<?= $this->e($settings['contact_phone']) ?>" maxlength="30"></div><div class="studio-field"><label for="social_instagram">Instagram</label><input id="social_instagram" type="url" name="social_instagram" value="<?= $this->e($settings['social_instagram']) ?>" placeholder="https://instagram.com/..."></div><div class="studio-field"><label for="social_linkedin">LinkedIn</label><input id="social_linkedin" type="url" name="social_linkedin" value="<?= $this->e($settings['social_linkedin']) ?>" placeholder="https://linkedin.com/company/..."></div></div></section></div></div>
        <footer class="studio-settings-save"><span>As alterações ficam disponíveis para integração com o site.</span><button class="studio-btn primary" type="submit">Salvar configurações</button></footer>
    </form>
</section>
