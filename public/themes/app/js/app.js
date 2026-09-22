const appLauncher = document.querySelector('.moves-app-launcher');
const appMenu = document.querySelector('#moves-app-menu');
const shell = document.querySelector('.customer-shell');
const navCollapse = document.querySelector('.customer-nav-collapse');
const navPreferenceKey = 'moves.navigation.collapsed';

function setNavCollapsed(collapsed) {
    shell?.classList.toggle('nav-collapsed', collapsed);
    navCollapse?.setAttribute('aria-expanded', String(!collapsed));
    if (navCollapse) {
        navCollapse.setAttribute('aria-label', collapsed ? 'Expandir navegação' : 'Recolher navegação');
        navCollapse.textContent = collapsed ? '›' : '‹';
    }
}
try { setNavCollapsed(localStorage.getItem(navPreferenceKey) === '1'); } catch (_) {}
navCollapse?.addEventListener('click', () => {
    const collapsed = !shell?.classList.contains('nav-collapsed');
    setNavCollapsed(collapsed);
    try { localStorage.setItem(navPreferenceKey, collapsed ? '1' : '0'); } catch (_) {}
});
function closeAppMenu(returnFocus = false) {
    if (!appMenu || !appLauncher) return;
    appMenu.hidden = true;
    appLauncher.setAttribute('aria-expanded', 'false');
    if (returnFocus) appLauncher.focus();
}
appLauncher?.addEventListener('click', () => {
    if (!appMenu) return;
    const opening = appMenu.hidden;
    appMenu.hidden = !opening;
    appLauncher.setAttribute('aria-expanded', String(opening));
    if (opening) appMenu.querySelector('[role="menuitem"]')?.focus();
});
appMenu?.addEventListener('keydown', event => {
    const items = [...appMenu.querySelectorAll('[role="menuitem"]')];
    const index = items.indexOf(document.activeElement);
    if (event.key === 'Escape') { event.preventDefault(); closeAppMenu(true); return; }
    if (!['ArrowDown','ArrowUp','Home','End'].includes(event.key) || !items.length) return;
    event.preventDefault();
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : event.key === 'ArrowDown' ? (index + 1 + items.length) % items.length : (index - 1 + items.length) % items.length;
    items[next].focus();
});
document.addEventListener('click', event => {
    if (appMenu && !appMenu.hidden && !appMenu.contains(event.target) && !appLauncher?.contains(event.target)) closeAppMenu();
});
const popoverButtons = [...document.querySelectorAll('[data-popover-button]')];
const closePopovers = (except = null, returnFocus = false) => {
    document.querySelectorAll('[data-popover]').forEach(popover => {
        if (popover === except) return;
        popover.hidden = true;
        const button = popoverButtons.find(item => item.getAttribute('aria-controls') === popover.id);
        button?.setAttribute('aria-expanded', 'false');
        if (returnFocus) button?.focus();
    });
};
popoverButtons.forEach(button => button.addEventListener('click', event => {
    event.stopPropagation();
    const popover = document.getElementById(button.getAttribute('aria-controls') || '');
    if (!popover) return;
    const opening = popover.hidden;
    closePopovers(popover);
    popover.hidden = !opening;
    button.setAttribute('aria-expanded', String(opening));
    if (opening) popover.querySelector('a,button')?.focus();
}));
document.addEventListener('click', event => {
    if (!event.target.closest('[data-popover]') && !event.target.closest('[data-popover-button]')) closePopovers();
});
document.querySelectorAll('[data-popover]').forEach(popover => popover.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        event.preventDefault();
        popover.hidden = true;
        const button = popoverButtons.find(item => item.getAttribute('aria-controls') === popover.id);
        button?.setAttribute('aria-expanded', 'false');
        button?.focus();
    }
}));

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
    if (event.key !== 'Escape') return;
    if (appMenu && !appMenu.hidden) {
        closeAppMenu(true);
        return;
    }
    if (document.body.classList.contains('customer-menu-open')) {
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

const talkWorkspace = document.querySelector('[data-talk-workspace]');
if (talkWorkspace) {
    const search = talkWorkspace.querySelector('[data-talk-search]');
    const filters = [...talkWorkspace.querySelectorAll('[data-talk-filter]')];
    const queueFilter = talkWorkspace.querySelector('[data-talk-filter-queue]');
    const priorityFilter = talkWorkspace.querySelector('[data-talk-filter-priority]');
    filters.forEach(button => button.addEventListener('click', () => {
        filters.forEach(item => item.classList.toggle('active', item === button));
        button.setAttribute('aria-pressed', 'true');
        filters.filter(item => item !== button).forEach(item => item.setAttribute('aria-pressed', 'false'));
        talkWorkspace.dataset.filter = button.dataset.talkFilter || 'all';
    }));
    let conversations = [...talkWorkspace.querySelectorAll('[data-talk-conversation]')];
    const applyTalkFilters = () => {
        const filter = talkWorkspace.dataset.filter || 'all';
        const query = search?.value.trim().toLocaleLowerCase('pt-BR') || '';
        let visible = 0;
        conversations.forEach(item => {
            const scope = item.dataset.scope || '';
            const haystack = (item.dataset.search || '').toLocaleLowerCase('pt-BR');
            const matchesFilter = filter === 'all' || filter === scope;
            const matchesQuery = query === '' || haystack.includes(query);
            const matchesQueue = !queueFilter?.value || item.dataset.queue === queueFilter.value;
            const matchesPriority = !priorityFilter?.value || item.dataset.priority === priorityFilter.value;
            item.hidden = !(matchesFilter && matchesQuery && matchesQueue && matchesPriority);
            if (!item.hidden) visible += 1;
        });
        talkWorkspace.querySelector('[data-talk-filter-empty]')?.toggleAttribute('hidden', visible !== 0);
    };
    const escapeTalkHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const renderTalkConversations = items => {
        const body = talkWorkspace.querySelector('.talk-list-body');
        if (!body || !Array.isArray(items)) return;
        const selected = talkWorkspace.querySelector('[data-talk-conversation][aria-current="true"]')?.getAttribute('href') || '';
        body.querySelectorAll('[data-talk-conversation]').forEach(item => item.remove());
        items.forEach(item => {
            const link = document.createElement('a');
            const href = '/talk/tickets/' + Number(item.id || 0);
            link.className = 'talk-conversation' + (selected === href ? ' is-selected' : '');
            link.href = href; link.dataset.talkConversation = ''; link.dataset.scope = item.scope || '';
            link.dataset.queue = item.queue_name || ''; link.dataset.priority = item.priority || 'normal';
            link.dataset.search = [item.contact_name,item.contact_phone,item.protocol,item.subject].filter(Boolean).join(' ').toLocaleLowerCase('pt-BR');
            if (selected === href) link.setAttribute('aria-current','true');
            const owner = item.scope === 'queue' ? 'Fila' : (item.assigned_name || 'Meu atendimento');
            link.innerHTML = '<span class="talk-conversation-main"><strong>'+escapeTalkHtml(item.contact_name || 'Contato')+'</strong><small>'+escapeTalkHtml(item.subject || item.protocol || '')+'</small></span><span class="talk-conversation-meta"><small>'+escapeTalkHtml(owner)+'</small><small>'+escapeTalkHtml(item.priority || 'normal')+'</small></span>';
            body.insertBefore(link, body.querySelector('[data-talk-filter-empty]'));
        });
        conversations = [...body.querySelectorAll('[data-talk-conversation]')];
        body.querySelector('.talk-empty:not([data-talk-filter-empty])')?.remove();
        applyTalkFilters();
    };
    filters.forEach(button => button.addEventListener('click', applyTalkFilters));
    search?.addEventListener('input', () => {
        talkWorkspace.dataset.query = search.value.trim().toLocaleLowerCase('pt-BR');
        applyTalkFilters();
    });
    queueFilter?.addEventListener('change', applyTalkFilters);
    priorityFilter?.addEventListener('change', applyTalkFilters);
}

const talkComposer = document.querySelector('[data-talk-composer]');
if (talkComposer) {
    const body = talkComposer.querySelector('[data-talk-composer-body]');
    const send = talkComposer.querySelector('[data-talk-send]');
    let submitting = false;
    body?.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            if (body.value.trim() && !submitting) talkComposer.requestSubmit();
        }
    });
    talkComposer.addEventListener('submit', event => {
        if (submitting || !body?.value.trim()) {
            event.preventDefault();
            return;
        }
        submitting = true;
        if (send) {
            send.disabled = true;
            send.textContent = 'Enviando…';
        }
    });
}

if (talkWorkspace?.dataset.syncUrl) {
    const syncState = talkWorkspace.querySelector('[data-talk-sync-state]');
    let talkSyncTimer = 0;
    let lastRevision = Number(talkWorkspace.dataset.revision || 0);
    let failures = 0;
    let talkSyncInFlight = false;
    const setTalkSyncState = (label, offline = false) => {
        if (!syncState) return;
        syncState.textContent = label;
        syncState.classList.toggle('is-offline', offline);
    };
    const scheduleTalkSync = () => {
        window.clearTimeout(talkSyncTimer);
        if (!document.hidden) talkSyncTimer = window.setTimeout(runTalkSync, failures ? Math.min(30000, 5000 * failures) : 10000);
    };
    const runTalkSync = async () => {
        if (document.hidden || talkSyncInFlight) return;
        talkSyncInFlight = true;
        try {
            const response = await fetch(talkWorkspace.dataset.syncUrl, {headers: {'Accept': 'application/json'}, credentials: 'same-origin', cache: 'no-store'});
            if (!response.ok) throw new Error('sync');
            const state = await response.json();
            failures = 0;
            setTalkSyncState('Conectado');
            const queueCount = talkWorkspace.querySelector('[data-talk-queue-count]');
            const mineCount = talkWorkspace.querySelector('[data-talk-mine-count]');
            const totalCount = talkWorkspace.querySelector('[data-talk-total-count]');
            if (queueCount) queueCount.textContent = String(Number(state.queue_count || 0));
            if (mineCount) mineCount.textContent = String(Number(state.mine_count || 0));
            if (totalCount) {
                const total = Number(state.queue_count || 0) + Number(state.mine_count || 0);
                totalCount.textContent = String(total);
                totalCount.setAttribute('aria-label', total + ' conversas');
            }
            const notificationCount = document.querySelector('[data-talk-notification-count]');
            if (notificationCount) {
                const unread = Number(state.notifications || 0);
                notificationCount.textContent = unread > 99 ? '99+' : String(unread);
                notificationCount.hidden = unread === 0;
                notificationCount.parentElement?.setAttribute('aria-label', unread === 1 ? '1 notificação não lida' : unread + ' notificações não lidas');
            }
            renderTalkConversations(state.conversations || []);
            const messageList = talkWorkspace.querySelector('[data-talk-messages]');
            if (messageList && Number(messageList.dataset.ticketId || 0) === Number(state.active_ticket_id || 0) && Array.isArray(state.messages)) {
                const currentMessages = [...messageList.querySelectorAll('[data-message-id]')].map(item => ({id: Number(item.dataset.messageId || 0), delivery: item.dataset.deliveryStatus || ''}));
                const nextMessages = state.messages.map(item => ({id: Number(item.id || 0), delivery: item.direction === 'outbound' ? (item.delivery_status || 'sent') : ''}));
                if (currentMessages.length !== nextMessages.length || currentMessages.some((item,index) => item.id !== nextMessages[index].id || item.delivery !== nextMessages[index].delivery)) {
                    messageList.innerHTML = '';
                    state.messages.forEach(message => {
                        const article = document.createElement('article');
                        article.className = 'talk-message ' + (message.direction === 'outbound' ? 'is-outbound' : 'is-inbound');
                        article.dataset.messageId = String(Number(message.id || 0));
                        article.dataset.deliveryStatus = message.direction === 'outbound' ? (message.delivery_status || 'sent') : '';
                        if (message.delivery_status === 'failed') article.classList.add('has-delivery-failed');
                        const body = document.createElement('p');
                        body.textContent = message.body || '';
                        const meta = document.createElement('small');
                        const deliveryLabels = {sent: 'Enviado', delivered: 'Entregue', read: 'Lido', failed: 'Falha no envio'};
                        meta.textContent = [message.sender_name || 'Contato', message.sent_at || '', message.direction === 'outbound' ? (deliveryLabels[message.delivery_status] || message.delivery_status || 'Enviado') : ''].filter(Boolean).join(' · ');
                        article.append(body, meta); messageList.appendChild(article);
                    });
                    messageList.scrollTop = messageList.scrollHeight;
                }
            }
            const revision = Number(state.revision || 0);
            if (lastRevision > 0 && revision > lastRevision) {
                const composerBody = talkWorkspace.querySelector('[data-talk-composer-body]');
                const hasDraft = Boolean(composerBody?.value.trim());
                if (!hasDraft && !messageList) window.location.reload();
            }
            lastRevision = Math.max(lastRevision, revision);
        } catch (error) {
            failures += 1;
            setTalkSyncState(navigator.onLine ? 'Reconectando…' : 'Offline', true);
        } finally {
            talkSyncInFlight = false;
            scheduleTalkSync();
        }
    };
    document.addEventListener('visibilitychange', () => {
        window.clearTimeout(talkSyncTimer);
        if (!document.hidden) runTalkSync();
    });
    window.addEventListener('online', runTalkSync);
    window.addEventListener('offline', () => setTalkSyncState('Offline', true));
    runTalkSync();
}

const confirmDialog = document.querySelector('[data-talk-confirm-dialog]');
let pendingConfirmForm = null;
document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
        if (form.dataset.confirmed === 'true' || !confirmDialog?.showModal) return;
        event.preventDefault();
        pendingConfirmForm = form;
        const message = confirmDialog.querySelector('[data-talk-confirm-message]');
        if (message) message.textContent = form.dataset.confirm || 'Confirmar esta ação?';
        confirmDialog.showModal();
        confirmDialog.querySelector('button[value="cancel"]')?.focus();
    });
});
confirmDialog?.addEventListener('close', () => {
    const form = pendingConfirmForm;
    pendingConfirmForm = null;
    if (!form) return;
    if (confirmDialog.returnValue === 'confirm') {
        form.dataset.confirmed = 'true';
        form.requestSubmit();
    } else {
        form.querySelector('button[type="submit"]')?.focus();
    }
});

const talkAttachmentForm = document.querySelector('[data-talk-attachment-form]');
if (talkAttachmentForm) {
    const input = talkAttachmentForm.querySelector('[data-talk-attachment]');
    let uploading = false;
    talkAttachmentForm.addEventListener('submit', event => {
        const file = input?.files?.[0];
        if (uploading || !file) {
            event.preventDefault();
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            event.preventDefault();
            window.alert('O anexo deve ter no máximo 10 MB.');
            return;
        }
        uploading = true;
        const button = talkAttachmentForm.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.textContent = 'Anexando…';
        }
    });
}
