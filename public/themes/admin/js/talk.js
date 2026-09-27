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
        channels.querySelector('[data-channel-new]')?.addEventListener('click', () => openDialog());
        channels.querySelectorAll('[data-dialog-close]').forEach((button) => button.addEventListener('click', () => dialog?.close()));
        channels.querySelectorAll('[data-channel-edit]').forEach((button) => button.addEventListener('click', () => {
            try { openDialog(JSON.parse(button.dataset.channelEdit || '{}')); } catch (_) { openDialog(); }
        }));
        channels.querySelector('[data-qr-close]')?.addEventListener('click', () => { channels.querySelector('[data-channel-qr]').hidden = true; });
        const render = (row, status) => {
            const badge = row.querySelector('[data-channel-state]');
            if (badge) { badge.textContent = status.status || 'disconnected'; badge.classList.toggle('success', Boolean(status.connected)); }
            const error = row.querySelector('[data-channel-error]');
            if (error) error.textContent = status.status === 'error' ? (status.detail || '') : '';
            const qr = channels.querySelector('[data-channel-qr]');
            if (qr && status.qr) { qr.hidden = false; qr.querySelector('img').src = status.qr; }
            else if (qr && status.connected) qr.hidden = true;
        };
        const request = async (id, action) => {
            const response = await fetch(`/talk/channels/${id}/${action}`, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: csrf }), credentials: 'same-origin' });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Falha ao operar o canal.');
            const row = channels.querySelector(`[data-channel-row="${id}"]`); if (row) render(row, data); return data;
        };
        channels.querySelectorAll('[data-channel-connect]').forEach((button) => button.addEventListener('click', async () => { try { await request(button.dataset.channelConnect, 'connect'); } catch (error) { window.alert(error.message); } }));
        channels.querySelectorAll('[data-channel-logout]').forEach((button) => button.addEventListener('click', async () => { if (!window.confirm('Desconectar somente este canal?')) return; try { await request(button.dataset.channelLogout, 'logout'); } catch (error) { window.alert(error.message); } }));
        const refresh = async () => { for (const row of channels.querySelectorAll('[data-channel-row]')) { try { const response = await fetch(row.dataset.statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin' }); if (response.ok) render(row, await response.json()); } catch (_) {} } };
        refresh(); window.setInterval(refresh, 3000);
    }

    const inboxTabs = page.querySelector('[data-talk-inbox-tabs]');
    if (inboxTabs) {
        inboxTabs.addEventListener('click', (event) => {
            const button = event.target.closest('[data-talk-scope]');
            if (!button) return;
            const scope = button.dataset.talkScope || 'all';
            inboxTabs.querySelectorAll('[data-talk-scope]').forEach((element) => element.classList.toggle('active', element === button));
            page.querySelectorAll('.talk-inbox-row').forEach((row) => {
                const status = row.dataset.talkStatus || '';
                const unread = Number(row.dataset.talkUnread || 0);
                row.hidden = scope === 'attending'
                    ? !['assigned', 'open'].includes(status)
                    : scope === 'unread' ? unread <= 0 : false;
            });
        });
    }

    const thread = page.querySelector('.talk-thread');
    if (thread) thread.scrollTop = thread.scrollHeight;

    const textarea = page.querySelector('[data-talk-composer]');
    if (textarea) {
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
            if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
                event.preventDefault();
                textarea.form?.requestSubmit();
            }
        });
        resize();
    }

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
    let syncing = false;
    let syncTimer = null;
    const syncStatus = document.createElement('span');
    syncStatus.className = 'talk-sync-status';
    syncStatus.setAttribute('role', 'status');
    syncStatus.setAttribute('aria-live', 'polite');
    page.querySelector('.talk-homebar-actions')?.prepend(syncStatus);
    const deliveryLabel = (status) => status === 'pending' ? 'Enviando' : status === 'failed' ? 'Falhou' : 'Enviada';
    const renderThread = (ticket) => {
        const target = page.querySelector('.talk-live-conversation .talk-thread');
        if (!target || !ticket?.messages) return;
        const pinnedToBottom = target.scrollHeight - target.scrollTop - target.clientHeight < 80;
        const fragment = document.createDocumentFragment();
        ticket.messages.forEach((message) => {
            const article = document.createElement('article');
            article.className = `talk-message talk-message-${message.direction}`;
            const meta = document.createElement('small');
            meta.textContent = `${message.sender_name || (message.sender_type === 'contact' ? ticket.contact_name : 'Sistema')} · ${message.sent_at || message.created_at || ''}`;
            const body = document.createElement('p'); body.textContent = message.body || '';
            article.append(meta, body);
            if (message.direction === 'outbound') { const status = message.delivery_status || 'sent'; const badge = document.createElement('span'); badge.className = `talk-delivery talk-delivery-${status}`; badge.textContent = deliveryLabel(status); article.append(badge); }
            fragment.append(article);
        });
        target.replaceChildren(fragment);
        if (pinnedToBottom) target.scrollTop = target.scrollHeight;
    };
    const updateConversationRows = (rows) => {
        const target = page.querySelector('.talk-inbox-list-body');
        if (!target || !Array.isArray(rows)) return;
        const byTicket = new Map(rows.map((row) => [String(row.ticket_id || 0), row]));
        target.querySelectorAll('.talk-inbox-row').forEach((element) => {
            const id = new URL(element.href).searchParams.get('ticket'); const row = byTicket.get(String(id));
            if (!row) { element.remove(); return; }
            element.dataset.talkStatus = row.ticket_status || row.status || ''; element.dataset.talkUnread = String(row.unread_count || 0);
            const copy = element.querySelector('.talk-inbox-row-copy'); if (copy) { copy.querySelector('strong').textContent = row.contact_name || row.phone || 'Sem identificação'; copy.querySelector('small').textContent = row.last_message_preview || 'Conversa do WhatsApp'; }
            byTicket.delete(String(id));
        });
        byTicket.forEach((row) => {
            const link=document.createElement('a');link.className='talk-inbox-row';link.href=`/talk/view/inbox?ticket=${Number(row.ticket_id)||0}`;link.dataset.talkStatus=row.ticket_status||row.status||'';link.dataset.talkUnread=String(row.unread_count||0);
            const avatar=document.createElement('span');avatar.className='talk-contact-avatar';avatar.textContent=String(row.contact_name||row.phone||'?').slice(0,1).toUpperCase();
            const copy=document.createElement('span');copy.className='talk-inbox-row-copy';const name=document.createElement('strong');name.textContent=row.contact_name||row.phone||'Sem identificação';const preview=document.createElement('small');preview.textContent=row.last_message_preview||'Conversa do WhatsApp';const meta=document.createElement('span');meta.className='talk-inbox-row-meta';const queue=document.createElement('em');queue.textContent=row.queue_name||'WhatsApp';meta.append(queue);if(Number(row.unread_count)>0){const unread=document.createElement('b');unread.textContent=String(row.unread_count);meta.append(unread);}copy.append(name,preview,meta);link.append(avatar,copy);target.prepend(link);
        });
        const activeScope=page.querySelector('[data-talk-scope].active')?.dataset.talkScope||'all';
        page.querySelectorAll('.talk-inbox-row').forEach((row)=>{const status=row.dataset.talkStatus||'';const unread=Number(row.dataset.talkUnread||0);row.hidden=activeScope==='attending'?!['assigned','open'].includes(status):activeScope==='unread'?unread<=0:false;});
    };
    const sync = async () => {
        if (syncing) return;
        syncing = true;
        try {
            const current = new URL(location.href); const query = new URLSearchParams();
            if (current.searchParams.get('ticket')) query.set('ticket', current.searchParams.get('ticket'));
            if (current.searchParams.get('q')) query.set('q', current.searchParams.get('q'));
            const response = await fetch(`/talk/sync?${query}`, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('sync unavailable');
            const data = await response.json();
            if (revision !== null && data.revision !== revision) { updateConversationRows(data.conversations); renderThread(data.ticket); }
            revision = data.revision; syncStatus.textContent = '';
            const badge = document.querySelector('[data-talk-notification-count]');
            if (badge) {
                badge.textContent = String(data.notifications || '');
                badge.hidden = !data.notifications;
            }
        } catch (_) { syncStatus.textContent = 'Reconectando…'; }
        finally { syncing = false; scheduleSync(); }
    };
    const scheduleSync = () => { window.clearTimeout(syncTimer); syncTimer = window.setTimeout(sync, document.hidden ? 15000 : 2000); };
    document.addEventListener('visibilitychange', () => { if (!document.hidden) { window.clearTimeout(syncTimer); sync(); } });
    sync();
})();
