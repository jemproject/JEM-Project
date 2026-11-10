/** Registration Status navigation and accessible hierarchy expansion; no data writes. */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.jem-registration-status').forEach(root => {
        // A submitted arrow is a one-time action. Refresh must retain the resolved date.
        const canonical = new URL(root.dataset.rsCanonical, window.location.href);
        if (canonical.origin === window.location.origin) {
            canonical.hash = window.location.hash;
            window.history.replaceState(null, '', canonical);
        }
        const form = root.querySelector('.rs-filters');
        const date = form.querySelector('[name="rs_date"]');
        const mode = () => form.querySelector('[name="rs_mode"]:checked');
        const submit = () => form.requestSubmit();
        form.querySelectorAll('[name="rs_mode"]').forEach(radio => {
            radio.addEventListener('change', () => {
                if (date && ['today', 'upcoming', 'all'].includes(radio.value)) date.value = '';
                submit();
            });
        });
        if (date) date.addEventListener('change', () => {
            if (!date.value || !date.checkValidity()) return;
            if (!mode() || !['week', 'month', 'year'].includes(mode().value)) {
                form.querySelector('[name="rs_mode"][value="date"]').checked = true;
            }
            submit();
        });
        const geography = ['country', 'region', 'county', 'city'];
        form.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', () => {
                const index = geography.indexOf(select.name.replace('rs_', ''));
                if (index >= 0) geography.slice(index + 1).forEach(name => {
                    const child = form.querySelector('[name="rs_' + name + '"]');
                    if (child) child.value = '';
                });
                submit();
            });
        });
        const rows = [...root.querySelectorAll('[data-rs-row]')];
        const byId = new Map(rows.map(row => [row.id, row]));
        const toggles = [...root.querySelectorAll('[data-rs-toggle]')];
        const update = () => rows.forEach(row => {
            let parent = byId.get(row.dataset.rsParent);
            let hidden = false;
            const seen = new Set();
            while (parent && !seen.has(parent.id)) {
                seen.add(parent.id);
                if (parent.querySelector('[data-rs-toggle]')?.getAttribute('aria-expanded') === 'false') hidden = true;
                parent = byId.get(parent.dataset.rsParent);
            }
            row.hidden = hidden;
        });
        const setExpanded = (button, expanded) => {
            button.setAttribute('aria-expanded', String(expanded));
            button.textContent = expanded ? '−' : '+';
        };
        toggles.forEach(button => button.addEventListener('click', () => {
            setExpanded(button, button.getAttribute('aria-expanded') !== 'true'); update();
        }));
        root.querySelectorAll('[data-rs-expand]').forEach(button => button.addEventListener('click', () => {
            toggles.forEach(toggle => setExpanded(toggle, button.dataset.rsExpand === '1')); update();
        }));
        update();
        const interval = Number(root.dataset.rsRefresh || 0);
        if ([30, 60, 120].includes(interval)) {
            setInterval(() => {
                if (!document.hidden && !document.querySelector('.jem-ticket-dialog[open]') && !form.contains(document.activeElement)) window.location.reload();
            }, interval * 1000);
        }
    });
});
