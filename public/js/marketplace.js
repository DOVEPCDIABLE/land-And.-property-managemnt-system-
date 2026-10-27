(() => {
    'use strict';
    const all = (selector, parent = document) => [...parent.querySelectorAll(selector)];
    const notify = (message, type = 'success') => window.EstateToast?.show(message, { type });
    const header = document.querySelector('[data-header]');
    const menu = document.querySelector('#main-menu');
    const menuButton = document.querySelector('[data-menu]');
    if (document.body.classList.contains('is-home')) {
        const updateHeader = () => header?.classList.toggle('is-scrolled', window.scrollY > 50);
        window.addEventListener('scroll', updateHeader, { passive: true });
        updateHeader();
    }
    menuButton?.addEventListener('click', () => {
        const opened = menu.classList.toggle('is-open');
        menuButton.setAttribute('aria-expanded', String(opened));
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            menu?.classList.remove('is-open');
            menuButton?.setAttribute('aria-expanded', 'false');
            all('.business-menu[open]').forEach(item => item.removeAttribute('open'));
        }
    });
    all('.main-menu a').forEach(link => link.addEventListener('click', () => {
        menu.classList.remove('is-open');
        menuButton?.setAttribute('aria-expanded', 'false');
        all('.business-menu[open]').forEach(item => item.removeAttribute('open'));
    }));
    document.addEventListener('click', event => {
        all('.business-menu[open]').forEach(item => { if (!item.contains(event.target)) item.removeAttribute('open'); });
    });

    const brandName = document.body.dataset.brandName || 'Castegroup';
    const inquiry = document.querySelector('#inquiry-dialog');
    const inquiryCopy = {
        listing: ['Your property. Its next chapter.', 'Tell us about the property you would like to sell or let. Live publishing is not active yet.'],
        viewing: ['See the possibilities.', 'Request information about this concept. It is not an active listing and no viewing is confirmed.'],
        valuation: ['A more informed next move.', 'Request a professional assessment. No automated estimate will be generated.'],
        professional: ['Find the right connection.', 'Tell us which kind of professional you need, or how you would like to join the planned directory.'],
        agency: ['Make room for your business.', `Register your agency’s interest in a ${brandName} workspace.`],
        referral: ['Better, together.', 'Register your interest in the planned agent and agency referral network.'],
        demo: ['Meet your next workspace.', 'Request a conversation about the working foundation, roadmap and future plans.'],
        alert: ['Let the right space find you.', 'Save your preferences for the team. Automatic matching and alert delivery are not active yet.'],
        support: ['Let’s talk.', `Tell the ${brandName} team how we can help.`],
        report: ['Thank you for speaking up.', 'Tell us what you noticed. Please do not include sensitive financial or identity details.']
    };
    function openInquiry(intent, preferences = {}, context = '') {
        if (!inquiry) return;
        const selected = Object.hasOwn(inquiryCopy, intent) ? intent : 'support';
        inquiry.querySelector('[data-intent]').value = selected;
        inquiry.querySelector('#inquiry-title').textContent = inquiryCopy[selected][0];
        inquiry.querySelector('[data-inquiry-description]').textContent = inquiryCopy[selected][1] + (context ? ` Subject: ${context}.` : '');
        for (const field of ['property', 'location', 'type', 'purpose', 'budget', 'currency', 'beds', 'channel', 'topic']) {
            inquiry.querySelector(`[data-inquiry-${field}]`).value = field === 'topic' ? context : (preferences[field] || (field === 'currency' ? document.querySelector('[data-inquiry-currency]').defaultValue : ''));
        }
        inquiry.showModal();
    }
    all('[data-inquiry]').forEach(button => button.addEventListener('click', () => openInquiry(button.dataset.inquiry, { property: button.dataset.property || '' }, button.dataset.context || '')));
    all('dialog').forEach(dialog => {
        dialog.querySelector('[data-close]')?.addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => { if (event.target === dialog) { const rect = dialog.getBoundingClientRect(); if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close(); } });
    });
    if (inquiry?.dataset.reopen) { const previous = Object.fromEntries(['property', 'location', 'type', 'purpose', 'budget', 'currency', 'beds', 'channel', 'topic'].map(field => [field, inquiry.querySelector(`[data-inquiry-${field}]`).value])); openInquiry(inquiry.dataset.reopen, previous, previous.topic); }
    document.querySelector('[data-valuation-form]')?.addEventListener('submit', event => {
        event.preventDefault();
        openInquiry('valuation', Object.fromEntries(new FormData(event.currentTarget)));
    });
    document.querySelector('[data-alert-form]')?.addEventListener('submit', event => {
        event.preventDefault();
        openInquiry('alert', Object.fromEntries(new FormData(event.currentTarget)));
    });

    document.querySelector('[data-save-search]')?.addEventListener('click', () => { const fields = Object.fromEntries(new FormData(document.querySelector('.property-search'))); openInquiry('alert', { ...fields, budget: fields.max || fields.min || '' }); });

    const properties = JSON.parse(document.querySelector('#property-data')?.textContent || '[]');
    const known = new Set(properties.map(property => property.slug));
    function read(key) {
        try {
            const data = JSON.parse(localStorage.getItem(key) || '[]');
            return new Set(Array.isArray(data) ? data.filter(slug => known.has(slug)) : []);
        } catch { return new Set(); }
    }
    let saved = read('citiestate.saved');
    let compared = new Set([...read('citiestate.compared')].slice(0, 3));
    function persist(key, values) {
        try { localStorage.setItem(key, JSON.stringify([...values])); }
        catch { notify('Browser storage is unavailable. Your choices will last for this page only.', 'warning'); }
    }
    function updateChoices() {
        all('[data-save]').forEach(button => button.setAttribute('aria-pressed', String(saved.has(button.dataset.save))));
        all('[data-compare]').forEach(input => { input.checked = compared.has(input.dataset.compare); });
        all('[data-saved-count]').forEach(item => { item.textContent = String(saved.size); });
        all('[data-compare-count]').forEach(item => { item.textContent = String(compared.size); });
        const bar = document.querySelector('[data-shortlist]');
        if (bar) bar.hidden = saved.size === 0 && compared.size === 0;
    }
    all('[data-save]').forEach(button => button.addEventListener('click', () => {
        const slug = button.dataset.save;
        if (!known.has(slug)) return;
        if (saved.has(slug)) { saved.delete(slug); notify('Removed from your favourites.'); }
        else { saved.add(slug); notify('Saved to favourites on this browser.'); }
        persist('citiestate.saved', saved);
        updateChoices();
    }));
    all('[data-compare]').forEach(input => input.addEventListener('change', () => {
        const slug = input.dataset.compare;
        if (!known.has(slug)) return;
        if (input.checked && !compared.has(slug) && compared.size >= 3) { input.checked = false; notify('Choose up to three properties to compare.', 'warning'); return; }
        input.checked ? compared.add(slug) : compared.delete(slug);
        persist('citiestate.compared', compared);
        updateChoices();
    }));
    updateChoices();
    window.addEventListener('storage', event => {
        if (event.key === null || ['citiestate.saved', 'citiestate.compared'].includes(event.key)) {
            saved = read('citiestate.saved'); compared = new Set([...read('citiestate.compared')].slice(0, 3)); updateChoices();
        }
    });
    document.querySelector('[data-clear-saved]')?.addEventListener('click', () => {
        saved.clear(); compared.clear(); persist('citiestate.saved', saved); persist('citiestate.compared', compared); updateChoices(); notify('Saved properties and comparisons have been cleared from this browser.');
    });
    const collection = document.querySelector('#collection-dialog');
    const currency = property => property.formatted_price || `${property.currency} ${property.price}`;
    const make = (tag, text, className = '') => { const element = document.createElement(tag); element.textContent = text; if (className) element.className = className; return element; };
    function showCollection(mode) {
        const values = mode === 'compare' ? compared : saved;
        const selected = properties.filter(property => values.has(property.slug));
        document.querySelector('#collection-title').textContent = mode === 'compare' ? 'A clearer comparison.' : 'Your saved possibilities.';
        const content = document.querySelector('[data-collection-content]');
        content.replaceChildren();
        if (selected.length === 0) content.append(make('p', 'Nothing here yet. Explore the properties and choose your favourites.'));
        else if (mode === 'compare') {
            const wrapper = make('div', '', 'comparison-wrap');
            const table = make('table', '', 'comparison-table');
            const header = document.createElement('tr');
            header.append(make('th', 'Concept property'));
            selected.forEach(property => header.append(make('th', property.name)));
            const thead = document.createElement('thead'); thead.append(header); table.append(thead);
            const tbody = document.createElement('tbody');
            const rows = [['Location', p => p.location], ['Type', p => p.type], ['Purpose', p => p.purpose], ['Sample price', p => currency(p) + (p.purpose === 'rent' ? ' / year' : '')], ['Bedrooms', p => String(p.beds)], ['Bathrooms', p => String(p.baths)], ['Area', p => `${p.area} m²`], ['Furnished', p => p.furnished ? 'Yes' : 'No']];
            rows.forEach(([label, value]) => { const tr = document.createElement('tr'); const th = make('th', label); th.scope = 'row'; tr.append(th); selected.forEach(property => tr.append(make('td', value(property)))); tbody.append(tr); });
            table.append(tbody); wrapper.append(table); content.append(wrapper);
        } else {
            const grid = make('div', '', 'collection-items');
            selected.forEach(property => {
                const card = make('article', '', 'collection-item');
                const image = document.createElement('img'); image.src = `/media/${property.image}`; image.alt = `Illustrative concept: ${property.name}`;
                const link = make('a', 'View concept ↗'); link.href = `/properties/${encodeURIComponent(property.slug)}?currency=${encodeURIComponent(property.display_currency || property.currency)}`;
                card.append(image, make('h3', property.name), make('p', property.location), make('p', currency(property)), link);
                grid.append(card);
            });
            content.append(grid);
        }
        collection.showModal();
    }
    all('[data-show-saved]').forEach(button => button.addEventListener('click', () => showCollection('saved')));
    all('[data-show-compare]').forEach(button => button.addEventListener('click', () => showCollection('compare')));

    const latestCards = all('.latest-grid [data-property-card]');
    function filterLatest(type) {
        let count = 0;
        latestCards.forEach(card => {
            const matches = type === 'land' ? card.dataset.propertyType === 'land' : type === 'commercial' ? ['office', 'shop', 'warehouse'].includes(card.dataset.propertyType) : card.dataset.purpose === type && !['land', 'office', 'shop', 'warehouse'].includes(card.dataset.propertyType);
            card.hidden = !matches || count >= 3;
            if (matches) count++;
        });
        all('[data-latest]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.latest === type)));
    }
    all('[data-latest]').forEach(button => button.addEventListener('click', () => filterLatest(button.dataset.latest)));
    if (latestCards.length) filterLatest('buy');

    const journeys = {
        seekers: [['Find your space.', 'Search by location, budget and the way you want to live.'], ['Make a shortlist.', 'Save favourites and compare the details that matter.'], ['See the possibilities.', 'Request a viewing and connect with the right people.']],
        owners: [['Tell your property’s story.', 'Submit accurate details and the information you have available.'], ['Prepare for the next step.', 'Complete applicable checks and prepare your listing.'], ['Start the conversation.', 'Publish when available and manage inquiries in one place.']],
        businesses: [['Create your workspace.', 'Register your agency’s interest and discuss your needs.'], ['Connect your business.', 'Bring your team, listings and clients into one workspace as features become available.'], ['Keep moving forward.', 'Assign leads and track outcomes as business workflows are introduced.']],
        providers: [['Make the introduction.', 'Register interest in a professional profile.'], ['Show your expertise.', 'Prepare service details and relevant credentials for review.'], ['Find new connections.', 'Receive inquiries and manage opportunities once the directory launches.']]
    };
    all('[data-journey]').forEach(button => button.addEventListener('click', () => {
        all('[data-journey]').forEach(other => other.setAttribute('aria-pressed', String(other === button)));
        const steps = document.querySelector('[data-journey-steps]'); steps.replaceChildren();
        journeys[button.dataset.journey].forEach(([title, text], index) => { const li = document.createElement('li'); li.append(make('span', `0${index + 1}`), make('h3', title), make('p', text)); steps.append(li); });
    }));

    const search = document.querySelector('.property-search');
    if (search) {
        const fields = all('.search-main .extra-search', search).map(field => { const marker = document.createComment('Responsive search field'); field.before(marker); return { field, marker }; });
        const adjustFields = () => fields.forEach(({ field, marker }, index) => {
            const move = window.innerWidth <= 600 || (window.innerWidth <= 900 && index === 1);
            move ? search.querySelector('.filters-grid').append(field) : marker.after(field);
        });
        window.addEventListener('resize', adjustFields, { passive: true }); adjustFields();
    }
    document.querySelector('[data-search-currency]')?.addEventListener('change', event => all('[data-currency-label]').forEach(label => { label.textContent = event.target.value; }));
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const video = document.querySelector('[data-hero-video]');
    const videoToggle = document.querySelector('[data-video-toggle]');
    let userPaused = false;
    const connection = navigator.connection;
    function ensureVideoSource() { if (!video.getAttribute('src')) { video.src = video.dataset.src; video.load(); } }
    function videoLabel(playing) {
        videoToggle.hidden = false;
        videoToggle.setAttribute('aria-label', playing ? 'Pause architectural film' : 'Play architectural film');
        videoToggle.textContent = playing ? 'Ⅱ  Pause film' : '▷  Play film';
    }
    async function playVideo() {
        if (!video || motion.matches) return;
        ensureVideoSource();
        try { await video.play(); video.classList.add('is-playing'); videoLabel(true); }
        catch { video.classList.remove('is-playing'); videoLabel(false); }
    }
    if (video) {
        video.muted = true;
        videoToggle.hidden = motion.matches;
        videoToggle.addEventListener('click', () => {
            if (video.paused) { userPaused = false; playVideo(); }
            else { userPaused = true; video.pause(); videoLabel(false); }
        });
        video.addEventListener('error', () => { video.classList.remove('is-playing'); videoToggle.hidden = true; });
        motion.addEventListener('change', () => { if (motion.matches) { video.pause(); video.classList.remove('is-playing'); videoToggle.hidden = true; } else { videoLabel(false); } });
        if (!motion.matches && window.innerWidth > 700 && !connection?.saveData && !['slow-2g', '2g'].includes(connection?.effectiveType)) playVideo();
        else if (!motion.matches) videoLabel(false);
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) video.pause();
            else if (!userPaused && video.getAttribute('src') && !motion.matches) playVideo();
        });
    }
    if ('IntersectionObserver' in window && !motion.matches) {
        document.documentElement.classList.add('js-reveal');
        const observer = new IntersectionObserver(entries => entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); } }), { threshold: 0.1 });
        all('.reveal').forEach(element => observer.observe(element));
    }
})();
