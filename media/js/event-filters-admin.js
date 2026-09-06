(function () {
    'use strict';

    function positiveIds(value) {
        return String(value || '')
            .split(',')
            .map(function (entry) {
                return parseInt(entry, 10);
            })
            .filter(function (entry, index, values) {
                return entry > 0 && values.indexOf(entry) === index;
            });
    }

    function rowValue(row) {
        var key = row.dataset.filterKey;
        var valueCell = row.querySelector('[data-filter-value]');

        if (!valueCell) {
            return key === 'contact' ? [] : (key === 'contact_category' ? 0 : '');
        }

        if (key === 'contact_category') {
            var category = valueCell.querySelector('select');

            return category ? Math.max(0, parseInt(category.value, 10) || 0) : 0;
        }

        if (key === 'contact') {
            var contacts = valueCell.querySelector('input[type="hidden"][id$="_id"]');

            return contacts ? positiveIds(contacts.value) : [];
        }

        var customValue = valueCell.querySelector('[data-filter-custom-value]');

        return customValue ? String(customValue.value || '').trim().slice(0, 200) : '';
    }

    function hasValue(row) {
        var value = rowValue(row);

        if (Array.isArray(value)) {
            return value.length > 0;
        }

        return row.dataset.filterKey === 'contact_category'
            ? value > 0
            : String(value).trim() !== '';
    }

    function checked(row, property) {
        var control = row.querySelector('[data-filter-property="' + property + '"]');

        return Boolean(control && control.checked);
    }

    function enforceStatus(row, changedProperty) {
        var active = row.querySelector('[data-filter-property="active"]');
        var visible = row.querySelector('[data-filter-property="visible"]');
        var editable = row.querySelector('[data-filter-property="editable"]');

        if (!active || !visible || !editable) {
            return;
        }

        if (active.checked && !hasValue(row)) {
            active.checked = false;
        }

        if (changedProperty === 'editable' && editable.checked) {
            visible.checked = true;
        }

        if (changedProperty === 'visible' && !visible.checked) {
            editable.checked = false;
        }

        if (!active.checked && visible.checked && !editable.checked) {
            if (changedProperty === 'editable') {
                visible.checked = false;
            } else {
                editable.checked = true;
            }
        }

        if (!visible.checked) {
            editable.checked = false;
        }
    }

    function synchronise(root) {
        var storage = root.querySelector('input[type="hidden"]');

        if (!storage) {
            return;
        }

        var rows = Array.from(root.querySelectorAll('tbody tr[data-filter-key]')).map(function (row) {
            enforceStatus(row, '');

            var condition = row.querySelector('[data-filter-property="condition"]');

            return {
                key: row.dataset.filterKey,
                condition: condition ? condition.value : '',
                value: rowValue(row),
                active: checked(row, 'active'),
                visible: checked(row, 'visible'),
                editable: checked(row, 'editable')
            };
        });

        storage.value = JSON.stringify({version: 2, rows: rows});
    }

    function customRows(root) {
        return Array.from(root.querySelectorAll('tbody tr[data-filter-optional="1"]'));
    }

    function updateCustomPicker(root) {
        var picker = root.querySelector('[data-jem-custom-field-picker]');
        var addButton = root.querySelector('[data-jem-event-filter-add]');
        var counter = root.querySelector('[data-jem-event-filter-count]');
        var maximum = Math.max(0, parseInt(root.dataset.jemEventFilterMaxCustom, 10) || 0);
        var rows = customRows(root);
        var used = rows.map(function (row) {
            return row.dataset.filterKey;
        });

        if (picker) {
            Array.from(picker.options).forEach(function (option) {
                if (option.value) {
                    option.disabled = used.indexOf(option.value) !== -1;
                }
            });

            if (!picker.value || picker.selectedOptions[0].disabled) {
                var available = Array.from(picker.options).find(function (option) {
                    return option.value && !option.disabled;
                });

                picker.value = available ? available.value : '';
            }
        }

        if (addButton) {
            addButton.disabled = rows.length >= maximum || !picker || !picker.value;
        }

        if (counter) {
            counter.textContent = rows.length + ' / ' + maximum;
        }
    }

    function addCustomField(root) {
        var picker = root.querySelector('[data-jem-custom-field-picker]');
        var body = root.querySelector('.jem-event-filter-table > tbody');
        var maximum = Math.max(0, parseInt(root.dataset.jemEventFilterMaxCustom, 10) || 0);

        if (!picker || !picker.value || !body || customRows(root).length >= maximum) {
            return;
        }

        var template = root.querySelector('template[data-filter-template="' + picker.value + '"]');

        if (!template || body.querySelector('tr[data-filter-key="' + picker.value + '"]')) {
            updateCustomPicker(root);
            return;
        }

        body.appendChild(template.content.cloneNode(true));
        updateCustomPicker(root);
        synchronise(root);
    }

    function clearContactPreselection(root) {
        var contactRow = root.querySelector('tr[data-filter-key="contact"]');

        if (!contactRow) {
            return;
        }

        var contactId = contactRow.querySelector('input[type="hidden"][id$="_id"]');

        if (contactId) {
            contactId.value = '';
        }
    }

    function initialise(root) {
        if (root.dataset.jemEventFilterReady === '1') {
            return;
        }

        root.dataset.jemEventFilterReady = '1';
        var draggedRow = null;

        root.addEventListener('change', function (event) {
            var row = event.target.closest('tr[data-filter-key]');

            if (!row) {
                return;
            }

            var property = event.target.dataset.filterProperty || '';

            if (row.dataset.filterKey === 'contact_category' && event.target.closest('[data-filter-value]')) {
                clearContactPreselection(root);
            }

            enforceStatus(row, property);
            updateCustomPicker(root);
            window.setTimeout(function () {
                synchronise(root);
            }, 0);
        });

        root.addEventListener('click', function (event) {
            if (event.target.closest('[data-jem-event-filter-add]')) {
                addCustomField(root);
                return;
            }

            var removeButton = event.target.closest('[data-jem-event-filter-remove]');

            if (!removeButton) {
                return;
            }

            var row = removeButton.closest('tr[data-filter-optional="1"]');

            if (row) {
                row.remove();
                updateCustomPicker(root);
                synchronise(root);
            }
        });

        root.addEventListener('dragstart', function (event) {
            var row = event.target.closest('tr[data-filter-key]');

            if (!row) {
                return;
            }

            draggedRow = row;
            row.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', row.dataset.filterKey);
        });

        root.addEventListener('dragover', function (event) {
            var target = event.target.closest('tr[data-filter-key]');

            if (!draggedRow || !target || target === draggedRow) {
                return;
            }

            event.preventDefault();
            var bounds = target.getBoundingClientRect();
            var insertBefore = event.clientY < bounds.top + (bounds.height / 2);

            target.parentNode.insertBefore(draggedRow, insertBefore ? target : target.nextSibling);
        });

        root.addEventListener('dragend', function () {
            if (draggedRow) {
                draggedRow.classList.remove('is-dragging');
            }

            draggedRow = null;
            updateCustomPicker(root);
            synchronise(root);
        });

        var form = root.closest('form');

        if (form) {
            form.addEventListener('submit', function () {
                synchronise(root);
            });
        }

        updateCustomPicker(root);
        synchronise(root);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-jem-event-filter-editor]').forEach(initialise);
    });
}());
