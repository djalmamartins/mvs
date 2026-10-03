(() => {
    const form = document.querySelector('form[action="/login/2fa"]');
    if (!form) return;

    const digits = [...form.querySelectorAll('.mfa-digit')];
    const code = form.querySelector('#code');
    const fallback = form.querySelector('#code-fallback');
    const recovery = form.querySelector('#recovery-code');
    const recoveryPanel = form.querySelector('.mfa-recovery');
    const group = form.querySelector('.mfa-code-group');
    const toggle = form.querySelector('.mfa-mode-toggle');

    fallback.disabled = true;
    fallback.hidden = true;
    form.querySelector('.mfa-fallback-label').hidden = true;
    code.disabled = false;
    code.name = 'code';
    group.hidden = false;
    digits[0].focus();
    if (toggle) toggle.hidden = false;

    function fill(value, start = 0) {
        const numbers = value.replace(/\D/g, '').slice(0, 6 - start);
        for (let index = 0; index < numbers.length; index++) {
            digits[start + index].value = numbers[index];
        }
        digits[Math.min(start + numbers.length, 5)].focus();
        if (numbers.length && start + numbers.length < 6) digits[start + numbers.length].focus();
    }

    digits.forEach((digit, index) => {
        digit.addEventListener('input', () => {
            const value = digit.value;
            digit.value = '';
            fill(value, index);
        });
        digit.addEventListener('paste', (event) => {
            const value = event.clipboardData?.getData('text') || '';
            if (!/^\d{6}$/.test(value)) return;
            event.preventDefault();
            digits.forEach((field) => { field.value = ''; });
            fill(value);
        });
        digit.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && !digit.value && index > 0) {
                digits[index - 1].value = '';
                digits[index - 1].focus();
                event.preventDefault();
            } else if (event.key === 'ArrowLeft' && index > 0) {
                digits[index - 1].focus();
                event.preventDefault();
            } else if (event.key === 'ArrowRight' && index < 5) {
                digits[index + 1].focus();
                event.preventDefault();
            } else if (event.key === 'Home') {
                digits[0].focus();
                event.preventDefault();
            } else if (event.key === 'End') {
                digits[5].focus();
                event.preventDefault();
            }
        });
    });

    toggle?.addEventListener('click', () => {
        const useRecovery = recoveryPanel.hidden;
        recoveryPanel.hidden = !useRecovery;
        group.hidden = useRecovery;
        recovery.disabled = !useRecovery;
        digits.forEach((digit) => { digit.disabled = useRecovery; });
        toggle.setAttribute('aria-expanded', String(useRecovery));
        toggle.textContent = useRecovery ? 'Usar código do autenticador' : 'Usar código de recuperação';
        if (useRecovery) recovery.focus();
        else digits[0].focus();
    });

    form.addEventListener('submit', (event) => {
        if (!recovery.disabled) {
            code.value = recovery.value.trim();
            if (!recovery.checkValidity() || !code.value) {
                event.preventDefault();
                recovery.reportValidity();
            }
            return;
        }
        code.value = digits.map((digit) => digit.value).join('');
        if (!/^\d{6}$/.test(code.value)) {
            event.preventDefault();
            const firstEmpty = digits.find((digit) => !digit.value || !/^\d$/.test(digit.value));
            (firstEmpty || digits[0]).focus();
        }
    });
})();
