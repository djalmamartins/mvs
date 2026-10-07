(() => {
    'use strict';

    const form = document.querySelector('[data-erp-receivable-form]');
    if (!form) return;

    const person = form.querySelector('[data-receivable-person]');
    const accountSelects = Array.from(form.querySelectorAll('[data-receivable-account]'));
    const installments = form.querySelector('[data-installments]');
    const template = form.querySelector('[data-installment-template]');
    const addButton = form.querySelector('[data-add-installment]');

    const selectedCondominium = () => person.selectedOptions[0]?.dataset.condominium ?? '';
    const filterOptions = (select) => {
        const condominium = selectedCondominium();
        for (const option of select.options) {
            if (!option.dataset.condominium) continue;
            const visible = condominium !== '' && option.dataset.condominium === condominium;
            option.hidden = !visible;
            option.disabled = !visible;
            if (!visible && option.selected) select.value = '';
        }
    };
    const filterAll = () => {
        for (const select of accountSelects) filterOptions(select);
        for (const select of installments.querySelectorAll('select[name="installments[period_id][]"]')) filterOptions(select);
    };
    const numberRows = () => {
        const rows = Array.from(installments.querySelectorAll('.erp-installment-row'));
        rows.forEach((row, index) => {
            row.querySelector('legend').textContent = `Parcela ${index + 1}`;
            const remove = row.querySelector('[data-remove-installment]');
            remove.setAttribute('aria-label', `Remover parcela ${index + 1}`);
            remove.disabled = rows.length === 1;
        });
    };

    person.addEventListener('change', filterAll);
    addButton.addEventListener('click', () => {
        const fragment = template.content.cloneNode(true);
        installments.appendChild(fragment);
        numberRows();
        filterAll();
        installments.lastElementChild?.querySelector('input')?.focus();
    });
    installments.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-installment]');
        if (!button || installments.querySelectorAll('.erp-installment-row').length <= 1) return;
        const row = button.closest('.erp-installment-row');
        row.remove();
        numberRows();
    });

    numberRows();
    filterAll();
})();
