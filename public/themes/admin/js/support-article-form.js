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