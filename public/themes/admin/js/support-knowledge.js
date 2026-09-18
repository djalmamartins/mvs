(() => {
    'use strict';

    const initModal = (dialog) => {
        const form = dialog.querySelector('[data-knowledge-form]');
        const error = dialog.querySelector('[data-modal-error]');
        if (!form) return;

        const close = () => {
            dialog.close();
            form.reset();
            form.querySelector('[name="id"]').value = '0';
            error.textContent = '';
            dialog.querySelector('h2').textContent = dialog.dataset.knowledgeDialog === 'product'
                ? 'Novo produto'
                : dialog.dataset.knowledgeDialog === 'category' ? 'Nova categoria' : 'Nova tag';
        };

        dialog.querySelectorAll('[data-knowledge-close]').forEach((button) => {
            button.addEventListener('click', close);
        });
        dialog.addEventListener('close', () => { error.textContent = ''; });
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            error.textContent = '';
            const data = new FormData(form);
            data.set('response', 'json');
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: data,
                });
                const result = await response.json();
                if (!response.ok || !result.ok) throw new Error(result.error || 'Não foi possível salvar.');
                if (form.hasAttribute('data-reload-on-success')) {
                    window.location.reload();
                    return;
                }
                close();
                updateArticleSelects(dialog.dataset.knowledgeDialog, result.item);
            } catch (saveError) {
                error.textContent = saveError.message;
            }
        });
    };

    const fillForm = (dialog, trigger) => {
        const form = dialog.querySelector('[data-knowledge-form]');
        if (!trigger) {
            form.reset();
            form.querySelector('[name="id"]').value = '0';
        }
        const values = trigger?.dataset || {};
        form.querySelector('[name="id"]').value = values.id || '0';
        Object.keys(values).forEach((key) => {
            const field = form.querySelector(`[name="${key.replace(/([A-Z])/g, '_$1').toLowerCase()}"]`);
            if (field) field.value = values[key];
        });
        const title = dialog.querySelector('h2');
        title.textContent = values.id
            ? `Editar ${dialog.dataset.knowledgeDialog === 'product' ? 'produto' : dialog.dataset.knowledgeDialog === 'category' ? 'categoria' : 'tag'}`
            : title.textContent;
        filterParents(dialog);
    };

    const filterParents = (dialog) => {
        const product = dialog.querySelector('[data-modal-product]');
        const parent = dialog.querySelector('[data-modal-parent]');
        if (!product || !parent) return;
        Array.from(parent.options).forEach((option) => {
            option.hidden = Boolean(option.value && product.value && option.dataset.productId !== product.value);
        });
        if (parent.selectedOptions[0]?.hidden) parent.value = '';
    };

    const updateArticleSelects = (type, item) => {
        const select = type === 'product'
            ? document.querySelector('[data-support-product]')
            : document.querySelector('[data-support-category]');
        if (!select || !item) return;
        const value = String(item.id);
        let option = select.querySelector(`option[value="${value}"]`);
        if (!option) {
            option = document.createElement('option');
            option.value = value;
            select.append(option);
        }
        option.textContent = item.name;
        if (type === 'category') option.dataset.productId = String(item.product_id || '');
        select.value = value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const initArticleDrawer = () => {
        const drawer = document.querySelector('[data-knowledge-drawer]');
        const dataNode = document.querySelector('[data-knowledge-article-data]');
        if (!drawer || !dataNode) return;

        let articles = {};
        try { articles = JSON.parse(dataNode.textContent || '{}'); } catch (error) { return; }
        const backdrop = document.querySelector('[data-knowledge-drawer] + .knowledge-drawer-backdrop');
        const close = () => {
            drawer.classList.remove('is-open');
            backdrop?.classList.remove('is-open');
            drawer.setAttribute('aria-hidden', 'true');
        };
        const setText = (selector, value) => {
            const element = drawer.querySelector(selector);
            if (element) element.textContent = value || '—';
        };
        const renderHistory = (revisions) => {
            const target = drawer.querySelector('[data-drawer-history]');
            if (!target) return;
            target.innerHTML = revisions?.length
                ? revisions.map((revision) => `<article class="knowledge-history-item"><strong>${escapeHtml(revision.title)}</strong><span>${escapeHtml(revision.created_at)}</span></article>`).join('')
                : '<p class="knowledge-note">Nenhuma revisão registrada.</p>';
        };
        const open = (id) => {
            const article = articles[String(id)];
            if (!article) return;
            setText('[data-drawer-title]', article.title);
            setText('[data-drawer-excerpt]', article.excerpt);
            setText('[data-drawer-category]', article.category);
            setText('[data-drawer-product]', article.product);
            setText('[data-drawer-tags]', article.tags?.join(', '));
            setText('[data-drawer-author]', article.author);
            setText('[data-drawer-created]', article.created_at);
            setText('[data-drawer-updated]', article.updated_at);
            setText('[data-drawer-reading]', article.reading_time ? `${article.reading_time} min` : '—');
            setText('[data-drawer-focus]', article.focus_keyword);
            setText('[data-drawer-meta-title]', article.meta_title);
            setText('[data-drawer-meta-description]', article.meta_description);
            setText('[data-drawer-canonical]', article.canonical_url);
            setText('[data-drawer-index]', article.robots_index ? 'Permitida' : 'Bloqueada');
            setText('[data-drawer-follow]', article.robots_follow ? 'Permitido' : 'Bloqueado');
            const status = drawer.querySelector('[data-drawer-status]');
            if (status) status.innerHTML = `<span class="studio-status studio-status-${article.status === 'published' ? 'success' : article.status === 'draft' ? 'warning' : 'neutral'}">${escapeHtml({published: 'Publicado', draft: 'Rascunho', archived: 'Arquivado'}[article.status] || article.status)}</span>`;
            const content = drawer.querySelector('[data-drawer-content]');
            if (content) content.innerHTML = article.content || '<p class="knowledge-note">Este artigo ainda não possui conteúdo.</p>';
            renderHistory(article.revisions);
            const edit = drawer.querySelector('[data-drawer-edit]');
            if (edit) edit.href = `/support/articles/${encodeURIComponent(article.slug)}/edit`;
            drawer.querySelectorAll('[data-drawer-panel]').forEach((panel) => panel.classList.toggle('is-hidden', panel.dataset.drawerPanel !== 'overview'));
            drawer.querySelectorAll('[data-drawer-tab]').forEach((tab) => tab.classList.toggle('active', tab.dataset.drawerTab === 'overview'));
            drawer.classList.add('is-open');
            backdrop?.classList.add('is-open');
            drawer.setAttribute('aria-hidden', 'false');
        };
        drawer.querySelectorAll('[data-drawer-tab]').forEach((tab) => tab.addEventListener('click', () => {
            drawer.querySelectorAll('[data-drawer-tab]').forEach((item) => item.classList.toggle('active', item === tab));
            drawer.querySelectorAll('[data-drawer-panel]').forEach((panel) => panel.classList.toggle('is-hidden', panel.dataset.drawerPanel !== tab.dataset.drawerTab));
        }));
        document.querySelectorAll('[data-knowledge-open-drawer]').forEach((button) => button.addEventListener('click', () => open(button.dataset.knowledgeOpenDrawer)));
        document.querySelectorAll('[data-knowledge-close-drawer]').forEach((button) => button.addEventListener('click', close));
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
    };

    const escapeHtml = (value) => String(value || '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character]));

    const initContextMenus = () => {
        document.querySelectorAll('[data-knowledge-menu]').forEach((button) => button.addEventListener('click', (event) => {
            event.stopPropagation();
            const menu = document.querySelector(`[data-knowledge-menu-panel="${button.dataset.knowledgeMenu}"]`);
            document.querySelectorAll('.knowledge-context-menu.is-open').forEach((item) => item.classList.remove('is-open'));
            menu?.classList.toggle('is-open');
        }));
        document.addEventListener('click', () => document.querySelectorAll('.knowledge-context-menu.is-open').forEach((item) => item.classList.remove('is-open')));
    };

    const init = () => {
        document.querySelectorAll('[data-knowledge-dialog]').forEach(initModal);
        document.querySelectorAll('[data-knowledge-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const dialog = document.querySelector(`[data-knowledge-dialog="${button.dataset.knowledgeOpen}"]`);
                if (!dialog) return;
                fillForm(dialog);
                dialog.showModal();
                dialog.querySelector('input:not([type="hidden"]), select, textarea')?.focus();
            });
        });
        document.querySelectorAll('[data-support-create-product], [data-support-create-category]').forEach((button) => {
            button.addEventListener('click', () => {
                const type = button.hasAttribute('data-support-create-product')
                    ? 'product'
                    : 'category';
                const dialog = document.querySelector(`[data-knowledge-dialog="${type}"]`);
                if (!dialog) return;
                fillForm(dialog);
                dialog.showModal();
                dialog.querySelector('input:not([type="hidden"]), select, textarea')?.focus();
            });
        });
        document.querySelectorAll('[data-knowledge-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                const dialog = document.querySelector(`[data-knowledge-dialog="${button.dataset.knowledgeEdit}"]`);
                if (!dialog) return;
                fillForm(dialog, button);
                dialog.showModal();
                dialog.querySelector('input:not([type="hidden"]), select, textarea')?.focus();
            });
        });
        document.querySelectorAll('[data-modal-product]').forEach((select) => {
            select.addEventListener('change', () => filterParents(select.closest('dialog')));
        });
        initArticleDrawer();
        initContextMenus();
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
