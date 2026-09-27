(() => {
    'use strict';

    const page = document.querySelector('.talk-page');
    if (!page) return;

    const connection = page.querySelector('[data-whatsapp-connection]');
    if (connection) {
        const refreshConnection = async () => {
            try {
                const response = await fetch('/talk/whatsapp/status', {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                    credentials: 'same-origin',
                });
                if (!response.ok) return;
                const status = await response.json();
                const badge = connection.querySelector('[data-whatsapp-state]');
                const detail = connection.querySelector('[data-whatsapp-detail]');
                if (badge) {
                    badge.textContent = status.connected ? 'Conectado' : 'Desconectado';
                    badge.classList.toggle('success', Boolean(status.connected));
                }
                if (detail) detail.textContent = status.detail || '';
                let qr = connection.querySelector('[data-whatsapp-qr]');
                if (status.qr && !qr) {
                    qr = document.createElement('div');
                    qr.className = 'talk-connection-qr';
                    qr.dataset.whatsappQr = '';
                    connection.querySelector('.talk-connection-card')?.append(qr);
                }
                if (qr) {
                    if (status.qr) {
                        qr.innerHTML = '<img alt="QR Code para conectar o WhatsApp"><div><strong>Conectar WhatsApp</strong><p>No celular, abra WhatsApp → Aparelhos conectados → Conectar aparelho e leia este QR Code.</p></div>';
                        qr.querySelector('img').src = status.qr;
                    } else {
                        qr.remove();
                    }
                }
            } catch (_) {
                // The next poll retries automatically.
            }
        };
        refreshConnection();
        window.setInterval(refreshConnection, 3000);
    }

    const channels = page.querySelector('[data-talk-channels]');
    if (channels) {
        const dialog = channels.querySelector('[data-channel-dialog]');
        const form = dialog?.querySelector('form');
        const csrf = form?.querySelector('input[name="_token"]')?.value || '';
        const connectDialog = channels.querySelector('[data-connect-dialog]');
        const removeDialog = channels.querySelector('[data-remove-dialog]');
        const feedback = channels.querySelector('[data-channel-feedback]');
        let connectTimer = null;
        let qrTimer = null;
        const stateLabels = { starting: 'Preparando conexão', qr: 'Aguardando QR', connected: 'Conectado', reconnecting: 'Reconectando', disconnected: 'Desconectado', error: 'Erro' };
        const formatPhone = (value) => { const digits = String(value || '').replace(/\D/g, ''); const match = digits.match(/^55(\d{2})(\d{5})(\d{4})$/); return match ? `(${match[1]}) ${match[2]}-${match[3]}` : (digits || 'Não configurado'); };
        const showFeedback = (message, error = false) => { if (!feedback) return; feedback.textContent = message; feedback.hidden = false; feedback.classList.toggle('is-error', error); window.setTimeout(() => { feedback.hidden = true; }, 4500); };
        const openDialog = (data = {}) => {
            if (!dialog || !form) return;
            form.reset();
            form.elements.id.value = data.id || 0;
            form.elements.name.value = data.name || '';
            form.elements.display_name.value = data.display_name || '';
            form.elements.default_queue_id.value = data.default_queue_id || 0;
            form.elements.status.value = data.status || 'active';
            dialog.querySelector('[data-dialog-title]').textContent = data.id ? 'Editar canal' : 'Novo canal';
            dialog.showModal();
        };
        channels.querySelectorAll('[data-channel-new],[data-channel-empty-new]').forEach((button) => button.addEventListener('click', () => openDialog()));
        channels.querySelectorAll('[data-dialog-close]').forEach((button) => button.addEventListener('click', () => dialog?.close()));
        channels.querySelectorAll('[data-channel-edit]').forEach((button) => button.addEventListener('click', () => {
            try { openDialog(JSON.parse(button.dataset.channelEdit || '{}')); } catch (_) { openDialog(); }
        }));
        const render = (row, status) => {
            const badge = row.querySelector('[data-channel-state]');
            const state = status.status || 'disconnected';
            if (badge) { badge.dataset.state = state; const label = badge.querySelector('span'); if (label) label.textContent = stateLabels[state] || 'Desconectado'; }
            const error = row.querySelector('[data-channel-error]');
            if (error) error.textContent = state === 'error' ? 'Não foi possível conectar o WhatsApp.' : '';
            const phone = status.profile?.phone_number || '';
            if (phone) { const element = row.querySelector('[data-channel-phone]'); if (element) { element.dataset.channelPhoneValue = phone; element.textContent = formatPhone(phone); } }
            const connect = row.querySelector('[data-channel-connect]'); if (connect) connect.textContent = state === 'disconnected' || state === 'error' ? 'Conectar' : 'Reconectar';
        };
        const request = async (id, action) => {
            const response = await fetch(`/talk/channels/${id}/${action}`, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: csrf }), credentials: 'same-origin' });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Falha ao operar o canal.');
            const row = channels.querySelector(`[data-channel-row="${id}"]`); if (row) render(row, data); return data;
        };
        const paintConnect = (status) => {
            if (!connectDialog) return;
            const state = status.status || 'error', title = connectDialog.querySelector('[data-connect-title]'), detail = connectDialog.querySelector('[data-connect-detail]'), qr = connectDialog.querySelector('[data-connect-qr]'), instructions = connectDialog.querySelector('[data-connect-instructions]'), success = connectDialog.querySelector('[data-connect-success]'), retry = connectDialog.querySelector('[data-connect-retry]'), done = connectDialog.querySelector('[data-connect-done]'), spinner = connectDialog.querySelector('.talk-spinner');
            connectDialog.querySelector('[data-connect-stage]').dataset.connectStage = state;
            qr.hidden = true; instructions.hidden = true; success.hidden = true; retry.hidden = true; done.hidden = true; spinner.hidden = !['starting','reconnecting'].includes(state);
            if (state === 'starting') { title.textContent = 'Preparando o WhatsApp...'; detail.textContent = 'Aguarde enquanto a sessão é preparada.'; }
            else if (state === 'qr' && status.qr) { title.textContent = 'Aguardando leitura...'; detail.textContent = 'Leia o QR Code com o número que será usado no atendimento.'; qr.src = status.qr; qr.hidden = false; instructions.hidden = false; window.clearTimeout(qrTimer); qrTimer = window.setTimeout(() => paintConnect({ status: 'expired' }), 60000); }
            else if (state === 'connected') { title.textContent = 'WhatsApp conectado'; detail.textContent = 'O canal está pronto para receber e enviar mensagens.'; success.hidden = false; success.querySelector('[data-connect-phone]').textContent = formatPhone(status.profile?.phone_number); done.hidden = false; window.clearTimeout(qrTimer); }
            else if (state === 'reconnecting') { title.textContent = 'Conectando ao WhatsApp...'; detail.textContent = 'Tentando recuperar a sessão existente.'; }
            else if (state === 'expired') { title.textContent = 'O QR Code expirou.'; detail.textContent = 'Gere um novo código para continuar.'; retry.hidden = false; }
            else { title.textContent = 'Não foi possível conectar o WhatsApp.'; detail.textContent = 'Verifique se o bridge está ativo e tente novamente.'; retry.hidden = false; }
        };
        const pollConnect = async (id) => { window.clearTimeout(connectTimer); try { const response = await fetch(`/talk/channels/${id}/status`, { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin' }); const status = await response.json(); const row = channels.querySelector(`[data-channel-row="${id}"]`); if (row) render(row, status); paintConnect(status); if (!['connected','error','disconnected'].includes(status.status)) connectTimer = window.setTimeout(() => pollConnect(id), 1800); } catch (_) { paintConnect({ status: 'error' }); } };
        const startConnect = async (id) => { if (!connectDialog) return; connectDialog.dataset.channelId = id; connectDialog.showModal(); paintConnect({ status: 'starting' }); try { const status = await request(id, 'connect'); paintConnect(status); if (status.status !== 'connected') connectTimer = window.setTimeout(() => pollConnect(id), 1200); } catch (_) { paintConnect({ status: 'error' }); } };
        channels.querySelectorAll('[data-channel-connect]').forEach((button) => button.addEventListener('click', () => startConnect(button.dataset.channelConnect)));
        channels.querySelectorAll('[data-connect-cancel]').forEach((button) => button.addEventListener('click', async () => { window.clearTimeout(connectTimer); window.clearTimeout(qrTimer); const id = connectDialog?.dataset.channelId; if (id && !connectDialog.querySelector('[data-connect-done]:not([hidden])')) { try { await request(id, 'disconnect'); } catch (_) {} } connectDialog?.close(); }));
        connectDialog?.querySelector('[data-connect-done]')?.addEventListener('click', () => { connectDialog.close(); showFeedback('Canal conectado'); });
        connectDialog?.querySelector('[data-connect-retry]')?.addEventListener('click', async () => { const id = connectDialog.dataset.channelId; paintConnect({ status: 'starting' }); try { await request(id, 'disconnect'); await startConnect(id); } catch (_) { paintConnect({ status: 'error' }); } });
        channels.querySelectorAll('[data-channel-disconnect]').forEach((button) => button.addEventListener('click', async () => { try { await request(button.dataset.channelDisconnect, 'disconnect'); showFeedback('Canal desconectado'); } catch (_) { showFeedback('Não foi possível desconectar.', true); } }));
        channels.querySelectorAll('[data-channel-remove]').forEach((button) => button.addEventListener('click', () => { const row = button.closest('[data-channel-row]'); removeDialog.dataset.channelId = button.dataset.channelRemove; removeDialog.querySelector('[data-remove-phone]').textContent = formatPhone(row?.querySelector('[data-channel-phone]')?.dataset.channelPhoneValue); removeDialog.showModal(); }));
        removeDialog?.querySelectorAll('[data-remove-cancel]').forEach((button) => button.addEventListener('click', () => removeDialog.close()));
        removeDialog?.querySelector('[data-remove-confirm]')?.addEventListener('click', async () => { const id = removeDialog.dataset.channelId; try { await request(id, 'remove'); channels.querySelector(`[data-channel-row="${id}"]`)?.remove(); removeDialog.close(); showFeedback('Canal removido. O histórico foi preservado.'); } catch (_) { showFeedback('Não foi possível remover o canal.', true); } });
        const refresh = async () => { for (const row of channels.querySelectorAll('[data-channel-row]')) { try { const response = await fetch(row.dataset.statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin' }); if (response.ok) render(row, await response.json()); } catch (_) {} } };
        refresh(); window.setInterval(refresh, 3000);
    }

    const inboxTabs = page.querySelector('[data-talk-inbox-tabs]');
    const applyInboxScope = () => {
        const scope = inboxTabs?.querySelector('[data-talk-scope].active')?.dataset.talkScope || 'all';
        page.querySelectorAll('.talk-inbox-row').forEach((row) => {
            const status = row.dataset.talkStatus || '';
            const unread = Number(row.dataset.talkUnread || 0);
            row.hidden = scope === 'attending'
                ? !['assigned', 'open'].includes(status)
                : scope === 'unread' ? unread <= 0 : false;
        });
    };
    if (inboxTabs) {
        inboxTabs.addEventListener('click', (event) => {
            const button = event.target.closest('[data-talk-scope]');
            if (!button) return;
            const scope = button.dataset.talkScope || 'all';
            inboxTabs.querySelectorAll('[data-talk-scope]').forEach((element) => element.classList.toggle('active', element === button));
            applyInboxScope();
        });
        applyInboxScope();
    }

    const thread = page.querySelector('.talk-thread');
    if (thread) thread.scrollTop = thread.scrollHeight;

    const setupComposer = () => {
        const textarea = page.querySelector('[data-talk-composer]');
        if (!textarea) return;
        const idempotency = textarea.form?.querySelector('[data-talk-idempotency]');
        if (idempotency && !idempotency.value) {
            idempotency.value = window.crypto?.randomUUID?.()
                || `intent-${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random().toString(16).slice(2)}`;
        }
        const resize = () => {
            textarea.style.height = 'auto';
            textarea.style.height = `${Math.min(textarea.scrollHeight, 180)}px`;
        };
        textarea.addEventListener('input', resize);
        textarea.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;
            event.preventDefault();
            textarea.form?.requestSubmit();
        });
        resize();
    };
    setupComposer();

    const file = page.querySelector('[data-talk-file]');
    const fileLabel = page.querySelector('[data-talk-file-name]');
    if (file && fileLabel) {
        file.addEventListener('change', () => {
            fileLabel.textContent = file.files?.[0]?.name || 'Nenhum arquivo selecionado';
        });
    }

    page.querySelectorAll('[data-confirm]').forEach((element) => element.addEventListener('click', (event) => {
        const message = element.getAttribute('data-confirm');
        if (message && !window.confirm(message)) event.preventDefault();
    }));

    let revision = null;
    let appliedRevision = null;
    let listedRevision = null;
    let timer = null;
    let polling = false;
    const status = page.querySelector('[data-talk-sync-status]');
    const setStatus = (state, label) => {
        if (!status || status.dataset.state === state) return;
        status.dataset.state = state;
        status.textContent = label;
    };
    const schedule = (delay) => {
        if (timer !== null) window.clearTimeout(timer);
        timer = window.setTimeout(sync, delay);
    };
    const refreshWorkspace = async () => {
        if (page.dataset.talkView !== 'inbox') return true;
        const currentComposer = page.querySelector('[data-talk-composer]');
        const editing = currentComposer && (document.activeElement === currentComposer || currentComposer.value.trim() !== '');
        if (listedRevision === revision && editing) return false;
        const response = await fetch(location.href, {
            headers: { Accept: 'text/html' }, cache: 'no-store', credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('Workspace indisponível');
        const fresh = new DOMParser().parseFromString(await response.text(), 'text/html');
        const nextPage = fresh.querySelector('.talk-page[data-talk-view="inbox"]');
        const nextList = nextPage?.querySelector('.talk-inbox-list-body');
        const currentList = page.querySelector('.talk-inbox-list-body');
        if (!nextList || !currentList) throw new Error('Workspace inválida');
        currentList.replaceWith(nextList);
        listedRevision = revision;
        applyInboxScope();
        const composer = page.querySelector('[data-talk-composer]');
        if (composer && (document.activeElement === composer || composer.value.trim() !== '')) return false;
        const shell = page.querySelector('.talk-home-shell');
        const nextShell = nextPage.querySelector('.talk-home-shell');
        const main = shell?.querySelector('main');
        const context = shell?.querySelector('.talk-home-context');
        const nextMain = nextShell?.querySelector('main');
        const nextContext = nextShell?.querySelector('.talk-home-context');
        if (!main || !context || !nextMain || !nextContext) throw new Error('Conversa inválida');
        const oldThread = main.querySelector('.talk-thread');
        const nearBottom = !oldThread || oldThread.scrollHeight - oldThread.scrollTop - oldThread.clientHeight < 48;
        const oldScrollTop = oldThread?.scrollTop || 0;
        main.replaceWith(nextMain);
        context.replaceWith(nextContext);
        const thread = page.querySelector('.talk-thread');
        if (thread) thread.scrollTop = nearBottom ? thread.scrollHeight : oldScrollTop;
        setupComposer();
        return true;
    };
    const sync = async () => {
        timer = null;
        if (polling) return;
        if (document.hidden) { schedule(15000); return; }
        polling = true;
        try {
            const response = await fetch('/talk/sync', {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Sincronização indisponível');
            const data = await response.json();
            if (typeof data.revision !== 'string') throw new Error('Revisão inválida');
            revision = data.revision;
            if (appliedRevision !== revision && await refreshWorkspace()) appliedRevision = revision;
            const badge = document.querySelector('[data-talk-notification-count]');
            if (badge) {
                badge.textContent = String(data.notifications || '');
                badge.hidden = !data.notifications;
            }
            setStatus('online', 'Atualizações conectadas');
        } catch (_) {
            setStatus('offline', 'Atualizações interrompidas. Tentando reconectar…');
        } finally {
            polling = false;
            schedule(document.hidden ? 15000 : status?.dataset.state === 'offline' ? 5000 : 2000);
        }
    };
    document.addEventListener('visibilitychange', () => {
        if (!polling) schedule(document.hidden ? 15000 : 0);
    });
    sync();
})();
