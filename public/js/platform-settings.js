(() => {
    'use strict';
    const filter = document.querySelector('[data-currency-filter]');
    filter?.addEventListener('input', () => {
        const text = filter.value.toLowerCase().trim();
        document.querySelectorAll('[data-currency-option]').forEach(label => { label.hidden = !label.textContent.toLowerCase().includes(text); });
    });
    document.querySelectorAll('[data-select-currencies]').forEach(button => button.addEventListener('click', () => {
        document.querySelectorAll('[name="enabled_currencies[]"]').forEach(checkbox => { checkbox.checked = button.dataset.selectCurrencies === 'all'; });
    }));
    const currency = document.querySelector('[data-payment-currency]');
    const amount = document.querySelector('#amount');
    const update = () => {
        const precision = Number(currency.selectedOptions[0]?.dataset.precision || 0);
        const step = precision ? `0.${'0'.repeat(precision - 1)}1` : '1';
        amount.step = step; amount.min = step;
    };
    if (currency && amount) { currency.addEventListener('change', update); update(); }
})();
