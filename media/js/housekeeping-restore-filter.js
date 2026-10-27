(() => {
    'use strict';

    const initialise = () => {
        const controls = document.getElementById('jem-restore-filters');
        const catalog = document.getElementById('jem-restore-catalog');

        if (!controls || !catalog) {
            return;
        }

        const buttons = Array.from(controls.querySelectorAll('[data-jem-restore-filter]'));
        const groups = Array.from(catalog.querySelectorAll('[data-jem-restore-group]'));
        const emptyMessage = document.getElementById('jem-restore-filter-empty');
        const expandButtons = Array.from(catalog.querySelectorAll('[data-jem-restore-expand]'));
        let currentMode = 'all';

        groups.forEach((group) => {
            group.dataset.jemExpanded = 'false';
            group.querySelector('[data-jem-restore-toggle]').disabled = false;
        });
        expandButtons.forEach((button) => {
            button.disabled = false;
        });

        const show = (mode) => {
            currentMode = mode;
            let visibleTotal = 0;

            groups.forEach((group) => {
                let visibleInGroup = 0;
                const expanded = group.dataset.jemExpanded === 'true';

                group.querySelectorAll('[data-jem-restore-status]').forEach((row) => {
                    const visible = mode === 'all' || row.dataset.jemRestoreStatus === mode;
                    row.classList.toggle('d-none', !visible || !expanded);
                    visibleInGroup += Number(visible);
                });

                group.classList.toggle('d-none', visibleInGroup === 0);
                const toggle = group.querySelector('[data-jem-restore-toggle]');
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                toggle.querySelector('span').className = expanded ? 'icon-chevron-down' : 'icon-chevron-right';

                const count = group.querySelector('[data-jem-restore-count]');
                if (count) {
                    count.textContent = `(${visibleInGroup})`;
                }
                visibleTotal += visibleInGroup;
            });

            if (emptyMessage) {
                emptyMessage.classList.toggle('d-none', visibleTotal > 0);
            }

            buttons.forEach((button) => {
                const active = button.dataset.jemRestoreFilter === mode;
                button.classList.toggle('active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        };

        controls.addEventListener('click', (event) => {
            const button = event.target.closest('[data-jem-restore-filter]');
            if (button && controls.contains(button)) {
                if (button.dataset.jemRestoreFilter !== 'all') {
                    groups.forEach((group) => {
                        group.dataset.jemExpanded = 'true';
                    });
                }
                show(button.dataset.jemRestoreFilter);
            }
        });

        catalog.addEventListener('click', (event) => {
            const toggle = event.target.closest('[data-jem-restore-toggle]');

            if (toggle && catalog.contains(toggle)) {
                const group = toggle.closest('[data-jem-restore-group]');
                group.dataset.jemExpanded = group.dataset.jemExpanded === 'true' ? 'false' : 'true';
                show(currentMode);
            }

            const expandButton = event.target.closest('[data-jem-restore-expand]');

            if (expandButton && catalog.contains(expandButton)) {
                const expanded = expandButton.dataset.jemRestoreExpand === 'all';
                groups.forEach((group) => {
                    group.dataset.jemExpanded = expanded ? 'true' : 'false';
                });
                show(currentMode);
            }
        });

        show(currentMode);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise, { once: true });
    } else {
        initialise();
    }
})();
