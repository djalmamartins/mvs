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
    let reloadScheduled = false;
    const sync = async () => {
        try {
            const response = await fetch('/talk/sync', {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                credentials: 'same-origin',
            });
            if (!response.ok) return;
            const data = await response.json();
            if (revision === null) {
                revision = data.revision;
            } else if (data.revision !== revision) {
                revision = data.revision;
                const composer = page.querySelector('[data-talk-composer]');
                const editing = composer && (document.activeElement === composer || composer.value.trim() !== '');
                const liveWorkspace = location.pathname.startsWith('/talk/view/');
                if (!editing && liveWorkspace && !reloadScheduled) {
                    reloadScheduled = true;
                    location.reload();
                    return;
                }
            }
            const badge = document.querySelector('[data-talk-notification-count]');
            if (badge) {
                badge.textContent = String(data.notifications || '');
                badge.hidden = !data.notifications;
            }
        } catch (_) {
            // The next poll retries automatically.
        }
    };
    sync();
    window.setInterval(sync, 2000);
})();
