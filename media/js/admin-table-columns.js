/**
 * Apply JEM's initial backend column visibility without overriding a user's
 * saved Joomla table-column preferences.
 */
(function () {
    'use strict';

    const applyDefaults = (table, attempt) => {
        const title = document.querySelector('.page-title');
        const tableName = table.dataset.name
            || (title ? title.textContent.trim().replace(/[^a-z0-9]/gi, '-').toLowerCase() : '');

        if (!tableName) {
            return;
        }

        const storageKey = `joomla-tablecolumns-${tableName}`;

        try {
            if (window.localStorage.getItem(storageKey) !== null) {
                return;
            }
        } catch (error) {
            return;
        }

        const headers = Array.from(table.querySelectorAll('thead tr:first-child > th'));
        const indexes = headers
            .map((header, index) => header.hasAttribute('data-jem-default-hidden') ? index : -1)
            .filter((index) => index >= 0);

        if (!indexes.length) {
            return;
        }

        const checkboxes = Array.from(document.querySelectorAll('input[name="table[column][]"]'));
        const defaults = indexes
            .map((index) => checkboxes.find((checkbox) => Number.parseInt(checkbox.value, 10) === index))
            .filter(Boolean);

        if (defaults.length !== indexes.length) {
            if (attempt < 10) {
                window.requestAnimationFrame(() => applyDefaults(table, attempt + 1));
            }
            return;
        }

        defaults.forEach((checkbox) => {
            if (checkbox.checked && !checkbox.disabled) {
                checkbox.click();
            }
        });
    };

    const initialise = () => {
        document.querySelectorAll('table:has(th[data-jem-default-hidden])')
            .forEach((table) => applyDefaults(table, 0));
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise, {once: true});
    } else {
        initialise();
    }
}());
