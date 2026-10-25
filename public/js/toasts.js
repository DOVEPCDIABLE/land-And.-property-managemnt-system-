(() => {
    'use strict';
    const region = document.querySelector('[data-toast-region]');
    if (!region) return;
    const dismissals = new WeakMap();
    const titles = { success: 'Success', error: 'Something went wrong', warning: 'Please note', info: 'Update' };
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    function attach(toast, duration) {
        let timer;
        let remaining = duration;
        let started = 0;
        let hovered = false;
        let focused = false;
        let dismissed = false;
        function pause() {
            if (!timer) return;
            clearTimeout(timer);
            timer = undefined;
            remaining = Math.max(0, remaining - (performance.now() - started));
        }
        function resume() {
            if (dismissed || hovered || focused || document.hidden || duration <= 0 || timer) return;
            started = performance.now();
            timer = setTimeout(dismiss, remaining);
        }
        function visibilityChanged() { document.hidden ? pause() : resume(); }
        function dismiss() {
            if (dismissed) return;
            dismissed = true;
            clearTimeout(timer);
            document.removeEventListener('visibilitychange', visibilityChanged);
            toast.classList.add('is-leaving');
            if (reducedMotion.matches) toast.remove();
            else setTimeout(() => toast.remove(), 190);
        }
        toast.classList.add('toast-ready');
        toast.querySelector('.toast-close')?.addEventListener('click', dismiss);
        toast.addEventListener('mouseenter', () => { hovered = true; pause(); });
        toast.addEventListener('mouseleave', () => { hovered = false; resume(); });
        toast.addEventListener('focusin', () => { focused = true; pause(); });
        toast.addEventListener('focusout', (event) => {
            if (!toast.contains(event.relatedTarget)) { focused = false; resume(); }
        });
        toast.addEventListener('keydown', (event) => { if (event.key === 'Escape') dismiss(); });
        document.addEventListener('visibilitychange', visibilityChanged);
        dismissals.set(toast, dismiss);
        resume();
        return dismiss;
    }

    for (const toast of region.querySelectorAll('[data-toast]')) {
        attach(toast, toast.dataset.type === 'success' || toast.dataset.type === 'info' ? 6000 : 0);
    }

    window.EstateToast = Object.freeze({
        show(message, options = {}) {
            const type = Object.hasOwn(titles, options.type) ? options.type : 'info';
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.dataset.toast = '';
            toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
            toast.setAttribute('aria-atomic', 'true');
            const icon = document.createElement('span');
            icon.className = 'toast-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.textContent = type === 'success' ? '✓' : type === 'info' ? 'i' : '!';
            const content = document.createElement('div');
            content.className = 'toast-content';
            const title = document.createElement('strong');
            title.className = 'toast-title';
            title.textContent = options.title || titles[type];
            const body = document.createElement('p');
            body.className = 'toast-message';
            body.textContent = String(message);
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'toast-close';
            close.setAttribute('aria-label', 'Dismiss notification');
            close.textContent = '×';
            content.append(title, body);
            toast.append(icon, content, close);
            while (region.children.length >= 5) {
                const oldest = region.firstElementChild;
                dismissals.get(oldest)?.();
                oldest.remove();
            }
            region.append(toast);
            const duration = Number.isFinite(options.duration) ? Math.max(0, options.duration) : (type === 'error' || type === 'warning' ? 0 : 6000);
            return attach(toast, duration);
        }
    });
})();
