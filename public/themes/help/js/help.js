(() => {
    'use strict';
    const dialog = document.querySelector('.help-lightbox');
    if (!(dialog instanceof HTMLDialogElement)) return;
    const target = dialog.querySelector('img');
    const caption = dialog.querySelector('figcaption');
    const close = () => dialog.close();
    document.querySelectorAll('.help-rich-content figure img').forEach((image) => {
        image.tabIndex = 0;
        image.setAttribute('role', 'button');
        image.setAttribute('aria-label', `${image.alt || 'Imagem'} — ampliar`);
        image.loading = 'lazy';
        const open = () => {
            target.src = image.currentSrc || image.src;
            target.alt = image.alt;
            caption.textContent = image.closest('figure')?.querySelector('figcaption')?.textContent || '';
            dialog.showModal();
        };
        image.addEventListener('click', open);
        image.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); open(); }
        });
    });
    dialog.querySelector('button')?.addEventListener('click', close);
    dialog.addEventListener('click', (event) => { if (event.target === dialog) close(); });
    dialog.addEventListener('cancel', close);
})();
