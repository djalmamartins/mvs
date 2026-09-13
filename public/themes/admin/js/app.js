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
