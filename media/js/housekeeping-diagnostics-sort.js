(function () {
    'use strict';

    function initialise() {
        const consent = document.getElementById('jem-diagnostic-consent');
        const send = document.querySelector('button[value="housekeeping.sendDiagnostics"]');

        if (consent && send) {
            const updateSend = () => {
                send.disabled = !consent.checked;
            };

            consent.addEventListener('change', updateSend);
            updateSend();
        }

        document.querySelectorAll('[data-jem-select-all]').forEach((selectAll) => {
            const table = selectAll.closest('table');
            const itemClass = selectAll.dataset.jemSelectAll === 'overrides'
                ? '.jem-diagnostic-override-item' : '.jem-diagnostic-report-item';
            const items = Array.from(table.querySelectorAll(itemClass + ':not(:disabled)'));

            function updateSelectAll() {
                const selected = items.filter((item) => item.checked).length;
                selectAll.checked = items.length > 0 && selected === items.length;
                selectAll.indeterminate = selected > 0 && selected < items.length;
                selectAll.disabled = items.length === 0;
            }

            selectAll.addEventListener('change', () => {
                items.forEach((item) => {
                    item.checked = selectAll.checked;
                    item.dispatchEvent(new Event('change', { bubbles: true }));
                });
                updateSelectAll();
            });
            table.addEventListener('change', (event) => {
                if (event.target.matches(itemClass)) {
                    updateSelectAll();
                }
            });
            updateSelectAll();
        });

        document.querySelectorAll('table[data-jem-delete-form]').forEach((table) => {
            const deleteForm = document.getElementById(table.dataset.jemDeleteForm);

            if (!deleteForm) {
                return;
            }
            const deleteButton = deleteForm.querySelector('button[type="submit"]');
            const selected = () => table.querySelectorAll('.jem-diagnostic-override-item:checked').length;
            const updateDelete = () => {
                deleteButton.disabled = selected() === 0;
            };

            table.addEventListener('change', updateDelete);
            deleteForm.addEventListener('submit', (event) => {
                if (selected() === 0 || !window.confirm(deleteForm.dataset.jemConfirm)) {
                    event.preventDefault();
                }
            });
            updateDelete();
        });

        const deleteOneForm = document.getElementById('jem-diagnostic-delete-one-form');

        if (deleteOneForm) {
            deleteOneForm.addEventListener('submit', (event) => {
                const button = event.submitter;
                const path = button ? button.dataset.jemOverridePath : '';

                if (!path || !window.confirm(deleteOneForm.dataset.jemConfirm + '\n' + path)) {
                    event.preventDefault();
                }
            });
            document.querySelectorAll('.jem-diagnostic-delete-one').forEach((button) => {
                button.disabled = false;
            });
        }

        const collator = new Intl.Collator(document.documentElement.lang || undefined, {
            numeric: true,
            sensitivity: 'base'
        });

        document.querySelectorAll('[data-jem-sort-table]').forEach((table) => {
            const body = table.tBodies[0];
            const grouped = table.hasAttribute('data-jem-sort-groups');
            const rows = grouped ? Array.from(table.tBodies) : Array.from(body ? body.rows : []);

            if (!body || rows.length < 2) {
                return;
            }

            const headers = Array.from(table.tHead.rows[0].cells);
            const originalOrder = new Map(rows.map((row, index) => [row, index]));
            let currentHeader = null;
            let currentDirection = 'ascending';

            function valueFor(item, index) {
                const row = grouped ? item.rows[0] : item;
                const cell = row ? row.cells[index] : null;

                return cell ? (cell.dataset.jemSortValue ?? cell.textContent.trim()) : '';
            }

            function numericValue(value) {
                const parsed = Number(String(value).replace(/[^\d.-]/g, ''));

                return Number.isFinite(parsed) ? parsed : -1;
            }

            function sort(index, type, direction, header) {
                const multiplier = direction === 'ascending' ? 1 : -1;

                rows.sort((first, second) => {
                    const firstValue = index === null ? first.dataset.jemSortId : valueFor(first, index);
                    const secondValue = index === null ? second.dataset.jemSortId : valueFor(second, index);
                    const compared = type === 'number'
                        ? numericValue(firstValue) - numericValue(secondValue)
                        : collator.compare(firstValue, secondValue);

                    return compared === 0
                        ? originalOrder.get(first) - originalOrder.get(second)
                        : compared * multiplier;
                });

                if (grouped) {
                    table.append(...rows);
                } else {
                    body.append(...rows);
                }
                headers.forEach((item) => item.removeAttribute('aria-sort'));

                if (header) {
                    header.setAttribute('aria-sort', direction);
                }

                currentHeader = header;
                currentDirection = direction;
            }

            headers.forEach((header, index) => {
                const type = header.dataset.jemSort;

                if (!type) {
                    return;
                }

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'jem-tools-sort-button';
                button.textContent = header.textContent.trim();
                header.replaceChildren(button);
                button.addEventListener('click', () => {
                    const direction = currentHeader === header && currentDirection === 'ascending'
                        ? 'descending' : 'ascending';

                    sort(index, type, direction, header);
                });
            });

            if (table.dataset.jemDefaultSort === 'id') {
                sort(null, 'number', 'ascending', headers.find((header) => header.hasAttribute('data-jem-sort-id-column')) || null);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise, { once: true });
    } else {
        initialise();
    }
}());
