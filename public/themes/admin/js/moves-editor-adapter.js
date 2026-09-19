(() => {
    'use strict';

    const registry = new Map();

    const uploadImage = async (file, textarea) => {
        const form = textarea.closest('form');
        const payload = new FormData();
        payload.append('_token', form?.querySelector('[name="_token"]')?.value || '');
        payload.append('action', 'upload');
        payload.append('image', file);

        const response = await fetch(document.body.dataset.editorUpload, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: payload,
        });
        const data = await response.json();
        if (!response.ok || !data.url) {
            throw new Error(data.error || 'Não foi possível enviar a imagem.');
        }
        return data.url;
    };

    const initialize = (textarea) => {
        if (!(window.MovesEditor instanceof Function) || registry.has(textarea.id)) {
            return;
        }

        if (!textarea.id) {
            textarea.id = `moves-editor-${registry.size + 1}`;
        }
        const host = document.createElement('div');
        host.id = `${textarea.id}-editor`;
        host.className = 'moves-editor-host';
        textarea.hidden = true;
        textarea.insertAdjacentElement('afterend', host);

        const editor = new window.MovesEditor(host, {
            initialHTML: textarea.value,
            minHeight: Number(textarea.dataset.editorHeight) || 360,
            imageUploader: (file) => uploadImage(file, textarea),
            maxImageSize: 8 * 1024 * 1024,
            onChange: (html) => {
                textarea.value = html;
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                textarea.dispatchEvent(new CustomEvent('moveseditor:change', {
                    bubbles: true,
                    detail: { editor },
                }));
            },
        });

        registry.set(textarea.id, editor);
        textarea.closest('form')?.addEventListener('submit', () => {
            textarea.value = editor.getHTML();
        });
        textarea.dispatchEvent(new CustomEvent('moveseditor:ready', {
            bubbles: true,
            detail: { editor },
        }));
    };

    window.MovesEditorRegistry = {
        get: (id) => registry.get(id) || null,
        entries: () => Array.from(registry.entries()),
    };

    const init = () => document
        .querySelectorAll('textarea[data-editor="moves"]')
        .forEach(initialize);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
