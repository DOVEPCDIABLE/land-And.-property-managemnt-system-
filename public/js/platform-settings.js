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

document.querySelector('[data-add-invoice-item]')?.addEventListener('click', () => {
    const container = document.querySelector('[data-invoice-items]');
    const index = container.children.length;
    const row = container.firstElementChild.cloneNode(true);
    row.querySelectorAll('input').forEach(input => { input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`); input.value = input.name.endsWith('[quantity]') ? '1' : ''; });
    container.append(row);
});
