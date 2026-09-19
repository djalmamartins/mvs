(function () {
    'use strict';

    const init = () => {
        const form = document.querySelector('[data-support-article-form]');

        if (!form || form.dataset.supportArticleReady === 'true') {
            return;
        }

        form.dataset.supportArticleReady = 'true';

        const title = form.querySelector('[data-support-title]');
        const slug = form.querySelector('[data-support-slug]');
        const excerpt = form.querySelector('[data-support-excerpt]');
        const content = form.querySelector('#support-content');
        const metaTitle = form.querySelector('[data-support-meta-title]');
        const metaDescription = form.querySelector(
            '[data-support-meta-description]'
        );
        const product = form.querySelector('[data-support-product]');
        const category = form.querySelector('[data-support-category]');
        const wordOutput = form.querySelector('[data-support-word-count]');
        const timeOutput = form.querySelector('[data-support-reading-time]');
        const metaTitleCount = form.querySelector(
            '[data-support-meta-title-count]'
        );
        const metaDescriptionCount = form.querySelector(
            '[data-support-meta-description-count]'
        );
        const user = document.body.dataset.editorUser || 'anonymous';
        const documentKey = content?.dataset.editorDocument || 'support-article:new';
        const storageKey = `moves-editor:draft:${user}:${documentKey}`;
        const pendingKey = 'moves-editor:pending-save';
        const persistedContent = content?.value || '';
        const persistedAt = Date.parse(content?.dataset.editorPersistedAt || '') || 0;
        let editor = null;
        let draftTimer = null;
        let dirty = false;
        let recovered = false;

        const normalizeHtml = (value) => String(value || '')
            .replace(/>\s+</g, '><')
            .trim();

        const draftPayload = (html) => ({
            content: html,
            savedAt: Date.now(),
            documentKey,
        });

        const readDraft = () => {
            const raw = localStorage.getItem(storageKey);
            if (!raw) return null;
            try {
                const parsed = JSON.parse(raw);
                if (
                    parsed
                    && typeof parsed.content === 'string'
                    && parsed.documentKey === documentKey
                    && Number.isFinite(Number(parsed.savedAt))
                ) {
                    return parsed;
                }
            } catch (_) {
                // Drafts created before Moves Editor 1.0.0 did not include
                // document identity or timestamps and cannot be recovered safely.
            }
            localStorage.removeItem(storageKey);
            return null;
        };

        const removeDraft = () => {
            clearTimeout(draftTimer);
            localStorage.removeItem(storageKey);
            dirty = false;
        };

        const saveDraft = () => {
            if (!editor) return;
            const html = editor.getHTML();
            if (normalizeHtml(html) === normalizeHtml(persistedContent)) {
                removeDraft();
                return;
            }
            localStorage.setItem(storageKey, JSON.stringify(draftPayload(html)));
            dirty = true;
        };

        const scheduleDraft = () => {
            dirty = true;
            clearTimeout(draftTimer);
            draftTimer = setTimeout(saveDraft, 700);
        };

        const recoverDraft = () => {
            if (!editor || recovered) return;
            recovered = true;
            const draft = readDraft();
            if (!draft || draft.documentKey !== documentKey) return;
            const differs = normalizeHtml(draft.content) !== normalizeHtml(persistedContent);
            const newer = Number(draft.savedAt || 0) > persistedAt;
            if (!differs) {
                removeDraft();
                return;
            }
            if (!newer) return;
            if (window.confirm('Existe um rascunho temporário mais recente neste navegador. Deseja recuperá-lo?')) {
                editor.setHTML(draft.content);
                content.value = editor.getHTML();
                content.dispatchEvent(new Event('input', { bubbles: true }));
                dirty = true;
            } else {
                removeDraft();
            }
        };

        const prepareMediaLibrary = async () => {
            const body = document.querySelector('.moves-editor-host .me-dialog-body');
            if (!body || body.querySelector('[data-tab="library"]')) return;
            const tabs = body.querySelector('.me-tabs');
            const urlPanel = body.querySelector('[data-panel="url"]');
            if (!tabs || !urlPanel) return;
            const tab = document.createElement('button');
            tab.type = 'button';
            tab.dataset.tab = 'library';
            tab.textContent = 'Biblioteca';
            const panel = document.createElement('div');
            panel.className = 'me-tab-panel me-library-panel';
            panel.dataset.panel = 'library';
            panel.textContent = 'Carregando biblioteca…';
            tabs.append(tab);
            urlPanel.insertAdjacentElement('afterend', panel);
            tab.addEventListener('click', () => {
                body.querySelectorAll('[data-tab]').forEach((item) => item.classList.toggle('active', item === tab));
                body.querySelectorAll('[data-panel]').forEach((item) => item.classList.toggle('active', item === panel));
            });
            try {
                const response = await fetch(document.body.dataset.editorLibrary, { headers: { Accept: 'application/json' } });
                const data = await response.json();
                panel.textContent = '';
                data.files.forEach((file) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'me-library-item';
                    const image = document.createElement('img');
                    image.src = file.url;
                    image.alt = '';
                    const label = document.createElement('span');
                    label.textContent = file.name;
                    button.append(image, label);
                    button.addEventListener('click', () => {
                        panel.querySelectorAll('.selected').forEach((item) => item.classList.remove('selected'));
                        button.classList.add('selected');
                        body.querySelector('[name="url"]').value = file.url;
                        body.querySelector('[name="alt"]').value = file.alt || '';
                    });
                    panel.append(button);
                });
                if (!data.files.length) panel.textContent = 'Nenhuma imagem disponível.';
            } catch (_) {
                panel.textContent = 'Não foi possível carregar a biblioteca.';
            }
        };

        const connectEditor = (instance) => {
            if (!instance || editor) return;
            editor = instance;
            recoverDraft();
            content.addEventListener('moveseditor:change', scheduleDraft);
            instance.root.addEventListener('click', (event) => {
                if (event.target.closest('[data-action="image"]')) {
                    window.setTimeout(prepareMediaLibrary, 0);
                }
            });
        };

        content?.addEventListener('moveseditor:ready', (event) => {
            connectEditor(event.detail?.editor);
        });
        connectEditor(window.MovesEditorRegistry?.get(content?.id));

        const slugify = (value) => value
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');

        const cleanText = (html) => {
            const node = document.createElement('div');
            node.innerHTML = html || '';
            return (node.textContent || '').replace(/\s+/g, ' ').trim();
        };

        const bindAutomaticField = (source, target, value) => {
            if (!source || !target || target.dataset.manual === 'true') {
                return;
            }

            target.addEventListener('input', () => {
                target.dataset.manual = 'true';
            });

            source.addEventListener('input', () => {
                if (target.dataset.manual !== 'true') {
                    target.value = value(source.value);
                }
            });
        };

        if (slug && !slug.dataset.existingSlug) {
            bindAutomaticField(title, slug, slugify);
        }

        bindAutomaticField(
            title,
            metaTitle,
            (value) => value.trim().slice(0, 255)
        );
        bindAutomaticField(
            content,
            excerpt,
            (value) => cleanText(value).slice(0, 500)
        );
        bindAutomaticField(
            content,
            metaDescription,
            (value) => cleanText(value).slice(0, 320)
        );

        const updateMetrics = () => {
            const words = cleanText(content?.value || '')
                .split(/\s+/)
                .filter(Boolean).length;
            const minutes = words > 0 ? Math.max(1, Math.ceil(words / 200)) : 0;

            if (wordOutput) {
                wordOutput.textContent = `${words.toLocaleString('pt-BR')} palavras`;
            }
            if (timeOutput) {
                timeOutput.textContent = `${minutes} min de leitura`;
            }
        };

        const filterCategories = () => {
            if (!product || !category) {
                return;
            }

            Array.from(category.options).forEach((option) => {
                const matches = !option.value
                    || !product.value
                    || option.dataset.productId === product.value;
                option.hidden = !matches;
            });

            if (category.selectedOptions[0]?.hidden) {
                category.value = '';
            }
        };

        const updateSeoCounters = () => {
            if (metaTitleCount && metaTitle) {
                metaTitleCount.textContent = String(metaTitle.value.length);
            }
            if (metaDescriptionCount && metaDescription) {
                metaDescriptionCount.textContent = String(
                    metaDescription.value.length
                );
            }
        };

        content?.addEventListener('input', updateMetrics);
        metaTitle?.addEventListener('input', updateSeoCounters);
        metaDescription?.addEventListener('input', updateSeoCounters);
        product?.addEventListener('change', filterCategories);
        form.addEventListener('submit', () => {
            if (!editor) return;
            content.value = editor.getHTML();
            saveDraft();
            sessionStorage.setItem(pendingKey, JSON.stringify({ storageKey }));
        });
        window.addEventListener('beforeunload', (event) => {
            if (!dirty) return;
            event.preventDefault();
            event.returnValue = '';
        });
        updateMetrics();
        updateSeoCounters();
        filterCategories();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}());
