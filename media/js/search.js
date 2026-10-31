/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

/**
 * Set a Search filter, submitting an explicit value even when its control is hidden.
 *
 * @param {HTMLFormElement} form Search form.
 * @param {string} name Filter name.
 * @param {string} value Submitted value.
 * @returns {void}
 */
function jem_search_set_filter(form, name, value) {
    var field = form.elements[name];
    if (!field) {
        field = document.createElement('input');
        field.type = 'hidden';
        field.name = name;
        form.appendChild(field);
    }
    field.disabled = false;
    field.value = value;
}

/**
 * Remove old search values from the action URL without changing menu or sort context.
 *
 * @param {HTMLFormElement} form Search form.
 * @returns {void}
 */
function jem_search_clean_action(form) {
    var action = new URL(form.action, document.baseURI);
    ['filter', 'filter_search', 'filter_type', 'filter_category', 'filter_type_id',
        'filter_venue_id', 'filter_continent', 'filter_country', 'filter_state', 'filter_city',
        'filter_date_from', 'filter_date_to', 'limitstart', 'start', 'search_reset'].forEach(function (name) {
        action.searchParams.delete(name);
    });
    form.action = action.toString();
}

/**
 * Submit changed Search filters from the first page.
 *
 * @param {HTMLFormElement} form Search form.
 * @returns {void}
 */
function jem_search_submit(form) {
    jem_search_clean_action(form);
    jem_search_set_filter(form, 'limitstart', '0');
    form.submit();
}

/**
 * Clear all visitor search filters, including controls hidden by menu options.
 * Restore the default page size; keep sorting and administrator-controlled event scope.
 *
 * @returns {void}
 */
function jem_search_clear() {
    var form = document.getElementById('adminForm');
    if (!form) {
        return;
    }
    var defaults = {
        filter_search: '', filter_type: 'title', filter_category: '1',
        filter_type_id: '0', filter_venue_id: '0', filter_continent: '',
        filter_country: '', filter_state: '', filter_city: '', filter_date_from: '', filter_date_to: ''
    };
    Object.keys(defaults).forEach(function (name) {
        jem_search_set_filter(form, name, defaults[name]);
    });
    jem_search_set_filter(form, 'search_reset', '1');
    var panel = document.getElementById('jem_filter');
    if (panel && panel.dataset.defaultLimit !== undefined) {
        jem_search_set_filter(form, 'limit', panel.dataset.defaultLimit);
    }
    jem_search_submit(form);
}

/**
 * Fit geographic controls to one or more quarters of the available row.
 * Measure selected text and reserve a final row for Limit, wrapped controls and actions.
 *
 * @param {HTMLElement} panel Search filter panel.
 * @returns {void}
 */
function jem_search_fit_location(panel) {
    var grid = panel.querySelector('.jem-search-bottom');
    if (!grid || !grid.clientWidth) {
        return;
    }
    var columns = grid.clientWidth < 480 ? 1 : 4;
    var gap = parseFloat(getComputedStyle(grid).columnGap) || 0;
    var unit = (grid.clientWidth - gap * (columns - 1)) / columns;
    var context = document.createElement('canvas').getContext('2d');
    if (!context) {
        return;
    }
    grid.style.gridTemplateColumns = 'repeat(' + columns + ', minmax(0, 1fr))';
    var spanFor = function (width) {
        return Math.min(columns, Math.max(1, Math.ceil((width + gap) / (unit + gap))));
    };
    var row = 1;
    var nextColumn = 1;
    var hasLocations = false;
    var placed = [];
    Array.from(grid.children).forEach(function (item) {
        if (item.classList.contains('jem-search-filter-footer')) {
            var limit = item.querySelector('.jem-search-filter-limit');
            var actions = item.querySelector('.jem-search-filter-actions');
            // Display contents makes the footer controls share grid cells with wrapped locations.
            item.style.display = columns > 1 ? 'contents' : 'flex';
            var limitSpan = limit ? spanFor(limit.getBoundingClientRect().width) : 0;
            var actionsSpan = actions ? spanFor(actions.getBoundingClientRect().width) : 0;
            var footerRow = hasLocations ? Math.max(2, row) : 1;
            var liftLimit = columns > 1 && limit && placed.length > 0 && placed.length < 4
                && nextColumn - 1 + limitSpan <= columns;
            if (columns === 1 || limitSpan + actionsSpan > columns) {
                item.style.display = 'flex';
                footerRow = hasLocations ? row + 1 : 1;
                liftLimit = false;
            } else if (liftLimit) {
                // Fewer enabled locations can share their row with Limit; actions stay below.
                footerRow = row + 1;
            } else if (hasLocations && footerRow === row) {
                if (nextColumn - 1 + limitSpan + actionsSpan <= columns) {
                    placed.filter(function (field) { return field.row === row; })
                        .forEach(function (field) {
                            field.item.style.gridColumn = (field.column + limitSpan) + ' / span ' + field.span;
                        });
                } else {
                    footerRow++;
                }
            }
            item.style.gridColumn = '1 / -1';
            item.style.gridRow = footerRow;
            if (limit) {
                limit.style.gridColumn = (liftLimit ? nextColumn : 1) + ' / span ' + limitSpan;
                limit.style.gridRow = liftLimit ? row : footerRow;
            }
            if (actions) {
                actions.style.gridColumn = (columns - actionsSpan + 1) + ' / -1';
                actions.style.gridRow = footerRow;
            }
            return;
        }
        var select = item.querySelector('select');
        if (!select) {
            return;
        }
        var style = getComputedStyle(select);
        context.font = style.fontWeight + ' ' + style.fontSize + ' ' + style.fontFamily;
        var text = select.selectedOptions[0] ? select.selectedOptions[0].text : '';
        var needed = context.measureText(text).width
            + (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0) + 4;
        var label = item.querySelector('label');
        if (label) {
            var labelStyle = getComputedStyle(label);
            context.font = labelStyle.fontWeight + ' ' + labelStyle.fontSize + ' ' + labelStyle.fontFamily;
            needed = Math.max(needed, context.measureText(label.textContent.trim()).width
                + (item.querySelector('.jem-search-location-hint') ? 22 : 0));
        }
        var span = spanFor(needed);
        if (nextColumn + span - 1 > columns) {
            row++;
            nextColumn = 1;
        }
        item.style.gridColumn = nextColumn + ' / span ' + span;
        item.style.gridRow = row;
        placed.push({item: item, row: row, column: nextColumn, span: span});
        nextColumn += span;
        hasLocations = true;
    });
}

jQuery(document).ready(function ($) {
    var form = document.getElementById('adminForm');
    if (!form) {
        return;
    }

    var panel = document.getElementById('jem_filter');
    if (panel) {
        jem_search_fit_location(panel);
        if (document.fonts) {
            document.fonts.ready.then(function () { jem_search_fit_location(panel); });
        }
        if (window.ResizeObserver) {
            var lastWidth = -1;
            new ResizeObserver(function (entries) {
                var width = entries[0].contentRect.width;
                if (Math.abs(width - lastWidth) > 0.5) {
                    lastWidth = width;
                    jem_search_fit_location(panel);
                }
            }).observe(panel);
        } else {
            window.addEventListener('resize', function () { jem_search_fit_location(panel); });
        }
    }

    $(form).on('submit', function () {
        jem_search_clean_action(form);
        jem_search_set_filter(form, 'limitstart', '0');
    });

    $('#filter_continent, #filter_country, #filter_state, #filter_city, #filter_category, #filter_type_id, #filter_venue_id')
        .on('change', function () {
            if (this.name === 'filter_continent') {
                jem_search_set_filter(form, 'filter_country', '');
            }
            if (this.name === 'filter_continent' || this.name === 'filter_country') {
                jem_search_set_filter(form, 'filter_state', '');
            }
            if (['filter_continent', 'filter_country', 'filter_state'].includes(this.name)) {
                jem_search_set_filter(form, 'filter_city', '');
            }
            if (['filter_continent', 'filter_country', 'filter_state', 'filter_city'].includes(this.name)) {
                jem_search_set_filter(form, 'filter_venue_id', '0');
            }
            jem_search_submit(form);
        });
});
