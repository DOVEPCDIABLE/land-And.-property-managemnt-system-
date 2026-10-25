(() => {
    'use strict';
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.querySelector('.workspace-menu-toggle');
    if (!sidebar || !toggle) return;
    function close() { sidebar.classList.remove('is-menu-open'); toggle.setAttribute('aria-expanded', 'false'); }
    toggle.addEventListener('click', () => { const open = sidebar.classList.toggle('is-menu-open'); toggle.setAttribute('aria-expanded', String(open)); });
    sidebar.addEventListener('keydown', event => { if (event.key === 'Escape' && sidebar.classList.contains('is-menu-open')) { close(); toggle.focus(); } });
    window.matchMedia('(max-width:700px)').addEventListener('change', close);
})();
