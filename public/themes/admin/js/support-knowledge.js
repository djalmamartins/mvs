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
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
