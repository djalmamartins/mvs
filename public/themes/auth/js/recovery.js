document.querySelectorAll('[data-loading-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"]');
        if (!button || !form.checkValidity()) return;
        button.disabled = true;
        button.textContent = button.dataset.loadingLabel || 'Enviando…';
        form.setAttribute('aria-busy', 'true');
    });
});

const recoveryCode = document.querySelector('.recovery-code');
recoveryCode?.addEventListener('input', () => {
    recoveryCode.value = recoveryCode.value.replace(/\D/g, '').slice(0, 6);
});
