const menuButton = document.querySelector('.studio-menu');
const backdrop = document.querySelector('.studio-sidebar-backdrop');
const profile = document.querySelector('.studio-header-profile');
const launcher = document.querySelector('.studio-app-launcher');
const launcherTrigger = launcher?.querySelector('.studio-app-launcher-trigger');
const launcherItems = () => Array.from(launcher?.querySelectorAll('[role="menuitem"]') || []);
const profileTrigger = profile?.querySelector('.studio-profile-trigger');
const themeButton = document.querySelector('.studio-theme-toggle');
const isMobile = () => window.matchMedia('(max-width: 1024px)').matches;

function setMenuState() {
    const open = isMobile()
        ? document.body.classList.contains('menu-open')
        : !document.body.classList.contains('menu-collapsed');
    menuButton?.setAttribute('aria-expanded', String(open));
}

function closeOverlays() {
    const profileWasOpen = profile?.classList.contains('open');
    const launcherWasOpen = launcher?.classList.contains('open');
    document.body.classList.remove('menu-open');
    profile?.classList.remove('open');
    launcher?.classList.remove('open');
    profileTrigger?.setAttribute('aria-expanded', 'false');
    launcherTrigger?.setAttribute('aria-expanded', 'false');
    setMenuState();
    if (profileWasOpen) profileTrigger?.focus();
    if (launcherWasOpen) launcherTrigger?.focus();
}

function applyTheme(theme) {
    document.documentElement.dataset.theme = theme;
    localStorage.setItem('studio-theme', theme);
    const dark = theme === 'dark';
    themeButton?.setAttribute('aria-pressed', String(dark));
    themeButton?.setAttribute('aria-label', dark ? 'Ativar modo claro' : 'Ativar modo escuro');
}

applyTheme(localStorage.getItem('studio-theme') || 'light');
if (!isMobile() && localStorage.getItem('studio-menu-collapsed') === '1') {
    document.body.classList.add('menu-collapsed');
}
setMenuState();

themeButton?.addEventListener('click', () => {
    applyTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
});
menuButton?.addEventListener('click', () => {
    if (isMobile()) {
        document.body.classList.toggle('menu-open');
    } else {
        document.body.classList.toggle('menu-collapsed');
        localStorage.setItem('studio-menu-collapsed', document.body.classList.contains('menu-collapsed') ? '1' : '0');
    }
    setMenuState();
});
backdrop?.addEventListener('click', closeOverlays);
launcherTrigger?.addEventListener('click', () => {
    const open = !launcher.classList.contains('open');
    launcher.classList.toggle('open', open);
    launcherTrigger.setAttribute('aria-expanded', String(open));
    if (open) {
        launcherItems()[0]?.focus();
        profile?.classList.remove('open');
        profileTrigger?.setAttribute('aria-expanded', 'false');
    }
});
profileTrigger?.addEventListener('click', () => {
    const open = !profile.classList.contains('open');
    profile.classList.toggle('open', open);
    profileTrigger.setAttribute('aria-expanded', String(open));
});
document.addEventListener('click', event => {
    if (!event.target.closest('.studio-app-launcher')) {
        launcher?.classList.remove('open');
        launcherTrigger?.setAttribute('aria-expanded', 'false');
    }
    if (!event.target.closest('.studio-header-profile')) {
        profile?.classList.remove('open');
        profileTrigger?.setAttribute('aria-expanded', 'false');
    }
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        closeOverlays();
        return;
    }
    if (!launcher?.classList.contains('open')) return;
    const items = launcherItems();
    const current = items.indexOf(document.activeElement);
    if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
        event.preventDefault();
        items[(current + 1 + items.length) % items.length]?.focus();
    }
    if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
        event.preventDefault();
        items[(current - 1 + items.length) % items.length]?.focus();
    }
    if (event.key === 'Home') {
        event.preventDefault();
        items[0]?.focus();
    }
    if (event.key === 'End') {
        event.preventDefault();
        items[items.length - 1]?.focus();
    }
});
window.addEventListener('resize', () => {
    if (!isMobile()) document.body.classList.remove('menu-open');
    setMenuState();
});

document.addEventListener('submit', event => {
    const form = event.target.closest('form[data-confirm-submit]');
    if (form && !window.confirm(form.dataset.confirmSubmit)) event.preventDefault();
});

document.addEventListener('click', event => {
    const control = event.target.closest('[data-confirm]');
    if (control && !window.confirm(control.dataset.confirm)) event.preventDefault();
});

const seoForm = document.querySelector('.studio-editor');
if (seoForm) {
    const fields = {
        title: seoForm.querySelector('#title'), slug: seoForm.querySelector('#slug'), excerpt: seoForm.querySelector('#excerpt'),
        content: seoForm.querySelector('#content'), seoTitle: seoForm.querySelector('#seo_title'), seoDescription: seoForm.querySelector('#seo_description')
    };
    const slugify = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 190);
    const plain = value => value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    const automatic = { slug: !fields.slug?.value, title: !fields.seoTitle?.value, description: !fields.seoDescription?.value };
    const refreshSeo = () => {
        if (automatic.slug && fields.slug) fields.slug.value = slugify(fields.title?.value || '');
        if (automatic.title && fields.seoTitle) fields.seoTitle.value = plain(fields.title?.value || '').slice(0, 60);
        if (automatic.description && fields.seoDescription) fields.seoDescription.value = plain(fields.excerpt?.value || fields.content?.value || '').slice(0, 160);
        [fields.seoTitle, fields.seoDescription].forEach((field, index) => {
            const count = seoForm.querySelector(`[data-seo-count="${field?.id}"]`);
            if (count && field) count.textContent = `${field.value.length}/${index === 0 ? 60 : 160}`;
        });
        const previewTitle = seoForm.querySelector('[data-seo-preview-title]');
        const previewUrl = seoForm.querySelector('[data-seo-preview-url]');
        const previewDescription = seoForm.querySelector('[data-seo-preview-description]');
        if (previewTitle) previewTitle.textContent = fields.seoTitle?.value || 'O título da página aparecerá aqui';
        if (previewUrl) previewUrl.textContent = previewUrl.textContent.replace(/[^/]*$/, '') + (fields.slug?.value || 'endereco-da-pagina');
        if (previewDescription) previewDescription.textContent = fields.seoDescription?.value || 'A descrição automática aparecerá aqui.';
    };
    fields.slug?.addEventListener('input', () => { automatic.slug = fields.slug.value === ''; refreshSeo(); });
    fields.seoTitle?.addEventListener('input', () => { automatic.title = fields.seoTitle.value === ''; refreshSeo(); });
    fields.seoDescription?.addEventListener('input', () => { automatic.description = fields.seoDescription.value === ''; refreshSeo(); });
    [fields.title, fields.excerpt, fields.content].forEach(field => field?.addEventListener('input', refreshSeo));
    refreshSeo();
}
