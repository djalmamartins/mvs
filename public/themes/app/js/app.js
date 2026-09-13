const menuButton = document.querySelector('.customer-menu-toggle');
const backdrop = document.querySelector('.customer-backdrop');
const dashboard = document.querySelector('[data-live-dashboard]');
const refreshButton = document.querySelector('[data-refresh]');
const liveStatus = document.querySelector('[data-live-status]');
const lastUpdated = document.querySelector('[data-last-updated]');
let refreshTimer;

function closeMenu() {
    document.body.classList.remove('customer-menu-open');
    menuButton?.setAttribute('aria-expanded', 'false');
}

function setConnection(online, text = online ? 'Conectado' : 'Sem conexão') {
    liveStatus?.classList.toggle('is-offline', !online);
    const label = liveStatus?.querySelector('span');
    if (label) label.textContent = text;
}

function updateCounter(key, value) {
    const element = document.querySelector(`[data-counter="${key}"]`);
    if (!element || element.textContent === String(value)) return;
    element.textContent = String(value);
    element.classList.remove('is-updated');
    requestAnimationFrame(() => element.classList.add('is-updated'));
}

async function refreshDashboard() {
    if (!dashboard || refreshButton?.disabled) return;
    refreshButton?.classList.add('is-loading');
    if (refreshButton) refreshButton.disabled = true;

    try {
        const response = await fetch(dashboard.dataset.statusUrl, {
            headers: {'Accept': 'application/json'}, credentials: 'same-origin', cache: 'no-store',
        });
        if (!response.ok) throw new Error('Status indisponível');
        const state = await response.json();
        Object.entries(state.summary || {}).forEach(([key, value]) => updateCounter(key, value));
        if (lastUpdated) {
            const time = new Date(state.updatedAt);
            lastUpdated.textContent = `Atualizado às ${time.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'})}`;
        }
        setConnection(true, 'Sincronizado');
    } catch (error) {
        setConnection(false);
        if (lastUpdated) lastUpdated.textContent = 'Não foi possível atualizar agora';
    } finally {
        refreshButton?.classList.remove('is-loading');
        if (refreshButton) refreshButton.disabled = false;
    }
}

menuButton?.addEventListener('click', () => {
    const open = !document.body.classList.contains('customer-menu-open');
    document.body.classList.toggle('customer-menu-open', open);
    menuButton.setAttribute('aria-expanded', String(open));
});
backdrop?.addEventListener('click', closeMenu);
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && document.body.classList.contains('customer-menu-open')) {
        closeMenu(); menuButton?.focus();
    }
});
document.querySelector('.customer-sidebar nav')?.addEventListener('click', event => {
    if (event.target.closest('a')) closeMenu();
});
refreshButton?.addEventListener('click', refreshDashboard);
window.addEventListener('online', () => { setConnection(true); refreshDashboard(); });
window.addEventListener('offline', () => setConnection(false));
document.addEventListener('visibilitychange', () => {
    window.clearInterval(refreshTimer);
    if (!document.hidden && dashboard) {
        refreshDashboard();
        refreshTimer = window.setInterval(refreshDashboard, 30000);
    }
});
if (dashboard) refreshTimer = window.setInterval(refreshDashboard, 30000);
setConnection(navigator.onLine);
