const menuButton = document.querySelector('.studio-menu');
const backdrop = document.querySelector('.studio-sidebar-backdrop');
const profile = document.querySelector('.studio-header-profile');
const themeButton = document.querySelector('.studio-theme-toggle');
const isMobile = () => window.matchMedia('(max-width: 1024px)').matches;

function setMenuState() {
    const open = isMobile()
        ? document.body.classList.contains('menu-open')
        : !document.body.classList.contains('menu-collapsed');
    menuButton?.setAttribute('aria-expanded', String(open));
}

function closeOverlays() {
    document.body.classList.remove('menu-open');
    profile?.classList.remove('open');
    profile?.setAttribute('aria-expanded', 'false');
    setMenuState();
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
profile?.addEventListener('click', event => {
    if (event.target.closest('a, form')) return;
    const open = !profile.classList.contains('open');
    profile.classList.toggle('open', open);
    profile.setAttribute('aria-expanded', String(open));
});
profile?.addEventListener('keydown', event => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        profile.click();
    }
});
document.addEventListener('click', event => {
    if (!event.target.closest('.studio-header-profile')) {
        profile?.classList.remove('open');
        profile?.setAttribute('aria-expanded', 'false');
    }
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeOverlays();
});
window.addEventListener('resize', () => {
    if (!isMobile()) document.body.classList.remove('menu-open');
    setMenuState();
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
