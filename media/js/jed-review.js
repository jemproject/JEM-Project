/** Local community-card preferences only; external navigation is not tracked. */
(() => {
    'use strict';
    const grid = document.querySelector('[data-jem-community-cards]');
    const status = document.querySelector('[data-jem-community-status]');
    if (!grid || !status) return;
    grid.querySelectorAll('[data-jem-community-card]').forEach((card) => {
        const key = card.dataset.jemCommunityCard;
        const shortcut = document.querySelector(`[data-jem-community-button="${key}"]`);
        const form = card.querySelector('form');
        let busy = false;
        form.addEventListener('submit', async (event) => {
            const action = event.submitter?.value;
            if (action !== 'dismiss' || !shortcut) return;
            event.preventDefault();
            if (busy) return;
            busy = true;
            const buttons = form.querySelectorAll('button');
            const data = new FormData(form);
            data.set('review_action', action);
            data.set('format', 'json');
            buttons.forEach((button) => { button.disabled = true; });
            form.setAttribute('aria-busy', 'true');
            status.textContent = '';
            let saved = false;
            try {
                const response = await fetch(form.action, {
                    method: 'POST', credentials: 'same-origin', body: data,
                    headers: {'Accept': 'application/json'}
                });
                const result = await response.json();
                if (!response.ok || result.success !== true || result.data?.dismissed !== true || result.data.card !== key) {
                    throw new Error('Preference not saved');
                }
                card.hidden = true;
                shortcut.hidden = false;
                grid.hidden = [...grid.querySelectorAll('[data-jem-community-card]')].every((item) => item.hidden);
                status.textContent = result.message || '';
                saved = true;
            } catch {
                status.textContent = status.dataset.error;
            } finally {
                busy = false;
                buttons.forEach((button) => { button.disabled = false; });
                form.removeAttribute('aria-busy');
                if (saved) shortcut.querySelector('a').focus();
            }
        });
    });
})();
