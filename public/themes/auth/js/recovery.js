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

const newPassword = document.querySelector('#password');
const passwordRules = {
    length: (value) => value.length >= 10 && value.length <= 128,
    lower: (value) => /[a-z]/.test(value),
    upper: (value) => /[A-Z]/.test(value),
    number: (value) => /\d/.test(value),
    symbol: (value) => /[^A-Za-z0-9\s]/.test(value),
};

newPassword?.addEventListener('input', () => {
    Object.entries(passwordRules).forEach(([rule, accepts]) => {
        document.querySelector(`[data-password-rule="${rule}"]`)
            ?.classList.toggle('is-valid', accepts(newPassword.value));
    });
});
