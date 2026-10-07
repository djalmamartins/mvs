(() => {
    'use strict';
    const form = document.querySelector('[data-erp-payable-form]');
    if (!form) return;
    const supplier = form.querySelector('[data-payable-supplier]');
    const condominium = form.querySelector('[data-payable-condominium]');
    const installments = form.querySelector('[data-payable-installments]');
    const template = form.querySelector('[data-payable-installment-template]');
    const filter = (select) => {
        const condo = condominium.value;
        for (const option of select.options) {
            if (!option.dataset.condominium) continue;
            const visible = condo !== '' && option.dataset.condominium === condo;
            option.hidden = !visible;
            option.disabled = !visible;
            if (!visible && option.selected) select.value = '';
        }
    };
    const filterAll = () => {
        form.querySelectorAll('[data-payable-account], [data-payable-period]').forEach(filter);
    };
    const renumber = () => {
        const rows = Array.from(installments.querySelectorAll('.erp-installment-row'));
        rows.forEach((row, index) => {
            row.querySelector('legend').textContent = `Parcela ${index + 1}`;
            const button = row.querySelector('[data-remove-payable-installment]');
            button.disabled = rows.length === 1;
            button.setAttribute('aria-label', `Remover parcela ${index + 1}`);
        });
    };
    supplier.addEventListener('change', () => {
        condominium.value = supplier.selectedOptions[0]?.dataset.condominium ?? '';
        filterAll();
    });
    form.querySelector('[data-add-payable-installment]').addEventListener('click', () => {
        installments.appendChild(template.content.cloneNode(true));
        renumber();
        filterAll();
        installments.lastElementChild?.querySelector('select')?.focus();
    });
    installments.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-payable-installment]');
        if (!button || installments.querySelectorAll('.erp-installment-row').length <= 1) return;
        button.closest('.erp-installment-row').remove();
        renumber();
    });
    renumber();
    filterAll();
})();
