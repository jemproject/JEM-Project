jQuery(document).ready(function ($) {
    var initialScope = new URL(window.location.href).searchParams;
    var allHidden = initialScope.get('calhide') === '1';
    var inheritedCategories = (initialScope.get('calcats') || '').split(',').filter(function (id) {
        return /^[1-9][0-9]*$/.test(id);
    }).map(function (id) { return 'cat' + id; });
    var inheritedVenues = (initialScope.get('calvenues') || '').split(',').filter(function (id) {
        return /^[1-9][0-9]*$/.test(id);
    }).map(function (id) { return 'venue' + id; });

    // Carry only numeric legend exclusions into links generated for Calendar -> Day.
    function updateDayLinks(hiddenCategories, hiddenVenues) {
        function ids(tokens, prefix) {
            return tokens.map(function (token) {
                var match = new RegExp('^' + prefix + '([1-9][0-9]*)$').exec(token);
                return match ? match[1] : null;
            }).filter(Boolean).filter(function (id, index, values) {
                return values.indexOf(id) === index;
            }).join(',');
        }

        var categories = ids(hiddenCategories, 'cat');
        var venues = ids(hiddenVenues, 'venue');
        document.querySelectorAll('#jem a[href], #jem [data-day-link]').forEach(function (element) {
            var attribute = element.hasAttribute('data-day-link') ? 'data-day-link' : 'href';
            var value = element.getAttribute(attribute);
            var url;
            try {
                url = new URL(value, document.baseURI);
            } catch (error) {
                return;
            }
            if (url.origin !== window.location.origin || url.searchParams.get('jemcal') !== '1') {
                return;
            }
            [['calcats', categories], ['calvenues', venues], ['calhide', allHidden ? '1' : '']].forEach(function (entry) {
                if (entry[1]) {
                    url.searchParams.set(entry[0], entry[1]);
                } else {
                    url.searchParams.delete(entry[0]);
                }
            });
            element.setAttribute(attribute, url.href);
        });
    }

    function hasToken(value, token) {
        return (' ' + (value || '') + ' ').indexOf(' ' + token + ' ') !== -1;
    }

    function updateGlobalButtons(activeButton) {
        $('#buttonshowall, #buttonhideall').removeClass('jem-calendar-global-active');

        if (activeButton) {
            $(activeButton).addClass('jem-calendar-global-active');
        }
    }

    function refreshCalendarFilters() {
        var hiddenCategories = inheritedCategories.slice();
        var hiddenVenues = inheritedVenues.slice();
        var hiddenSpecialDayTypes = [];

        $('#jlcalendarlegend .eventCat.catoff').each(function () {
            hiddenCategories.push($(this).data('filterClass') || $(this).attr('id'));
        });

        $('#jlcalendarlegend .eventVenues.catoff').each(function () {
            hiddenVenues.push($(this).data('filterClass') || $(this).attr('id'));
        });

        $('#jem .eventSpecialDayType.catoff').each(function () {
            hiddenSpecialDayTypes.push($(this).data('filterClass') || $(this).attr('id'));
        });

        updateDayLinks(hiddenCategories, hiddenVenues);

        $('#jem .event-filter').each(function () {
            var item = $(this);
            var itemCategories = item.data('categories') || '';
            var itemVenue = item.data('venue') || '';
            var hidden = allHidden;

            if (!hidden) {
                $.each(hiddenCategories, function (index, filterClass) {
                    if (item.hasClass(filterClass) || hasToken(itemCategories, filterClass)) {
                        hidden = true;
                        return false;
                    }
                });
            }

            if (!hidden) {
                $.each(hiddenVenues, function (index, filterClass) {
                    if (item.hasClass(filterClass) || itemVenue === filterClass) {
                        hidden = true;
                        return false;
                    }
                });
            }

            item.toggle(!hidden);
        });

        $('.is-special-day').each(function () {
            var item = $(this);
            var layers = item.data('specialDayLayers') || item.data('special-day-layers') || [];
            var visibleLayers = [];
            var activeLayer = null;
            var hidden = false;

            if (typeof layers === 'string') {
                try {
                    layers = JSON.parse(layers);
                } catch (error) {
                    layers = [];
                }
            }

            if (Array.isArray(layers) && layers.length) {
                $.each(layers, function (index, layer) {
                    if ($.inArray(layer.filterClass, hiddenSpecialDayTypes) === -1) {
                        visibleLayers.push(layer);
                    }
                });

                activeLayer = visibleLayers.length ? visibleLayers[0] : null;
                hidden = !activeLayer;
            } else {
                $.each(hiddenSpecialDayTypes, function (index, filterClass) {
                    if (item.hasClass(filterClass)) {
                        hidden = true;
                        return false;
                    }
                });
            }

            if (hidden) {
                item.attr('style', '');
                item.removeAttr('title');
            } else if (activeLayer) {
                var color = activeLayer.color || '#d1d5db';
                var textColor = activeLayer.textColor || '#111827';
                var titles = [];

                $.each(visibleLayers, function (index, layer) {
                    if (layer.title && $.inArray(layer.title, titles) === -1) {
                        titles.push(layer.title);
                    }
                });

                item.attr('style', '--jem-calendar-special-day-bg:' + color + ';--jem-calendar-special-day-color:' + textColor + ';background-color:' + color + ';color:' + textColor + ';');

                if (titles.length) {
                    item.attr('title', titles.join(', '));
                }
            }

            item.toggleClass('is-special-day-filtered', hidden);
        });
    }

    /* categories filtering */
    $(document).on('click', '#jlcalendarlegend .eventCat', function (event) {
        event.preventDefault();
        allHidden = false;
        updateGlobalButtons(null);
        $(this).toggleClass('catoff');
        $(this).attr('aria-pressed', $(this).hasClass('catoff') ? 'false' : 'true');
        refreshCalendarFilters();
    });

    /* venues filtering */
    $(document).on('click', '#jlcalendarlegend .eventVenues', function (event) {
        event.preventDefault();
        allHidden = false;
        updateGlobalButtons(null);
        $(this).toggleClass('catoff');
        $(this).attr('aria-pressed', $(this).hasClass('catoff') ? 'false' : 'true');
        refreshCalendarFilters();
    });

    /* special day types filtering */
    $(document).on('click', '#jem .eventSpecialDayType', function (event) {
        event.preventDefault();
        allHidden = false;
        updateGlobalButtons(null);
        $(this).toggleClass('catoff');
        $(this).attr('aria-pressed', $(this).hasClass('catoff') ? 'false' : 'true');
        refreshCalendarFilters();
    });

    /* Show all */
    $(document).on('click', '#buttonshowall', function (event) {
        event.preventDefault();
        allHidden = false;
        inheritedCategories = [];
        inheritedVenues = [];
        updateGlobalButtons(this);
        $('#jlcalendarlegend .eventCat, #jlcalendarlegend .eventVenues, #jem .eventSpecialDayType').removeClass('catoff').attr('aria-pressed', 'true');
        refreshCalendarFilters();
    });

    /* Hide all */
    $(document).on('click', '#buttonhideall', function (event) {
        event.preventDefault();
        allHidden = true;
        updateGlobalButtons(this);
        $('#jlcalendarlegend .eventCat, #jlcalendarlegend .eventVenues, #jem .eventSpecialDayType').addClass('catoff').attr('aria-pressed', 'false');
        refreshCalendarFilters();
    });
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {html: true});
    });

    var annualPopoverSelector = '.jem-annual-calendar .jem-annual-day-popover';
    var annualPopoverOptions = {
        trigger: 'manual',
        placement: 'top',
        html: true,
        sanitize: false
    };

    function getAnnualPopover(element) {
        return bootstrap.Popover.getInstance(element);
    }

    function ensureAnnualPopover(element) {
        return getAnnualPopover(element) || new bootstrap.Popover(element, annualPopoverOptions);
    }

    function hideAnnualPopovers(except) {
        document.querySelectorAll(annualPopoverSelector).forEach(function (element) {
            var popover = getAnnualPopover(element);

            if (popover && element !== except) {
                popover.hide();
            }
        });
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest(annualPopoverSelector);

        if (trigger) {
            event.preventDefault();
            event.stopPropagation();
            hideAnnualPopovers(trigger);
            ensureAnnualPopover(trigger).toggle();
            return;
        }

        var annualDay = event.target.closest('.jem-annual-calendar .jem-annual-day[data-day-link]');

        if (annualDay && !event.target.closest('a, button, .popover')) {
            window.location.href = annualDay.getAttribute('data-day-link');
            return;
        }

        if (!event.target.closest('.popover')) {
            hideAnnualPopovers();
        }
    });

    document.addEventListener('keydown', function (event) {
        var annualDay = event.target.closest('.jem-annual-calendar .jem-annual-day[data-day-link]');

        if (!annualDay || event.target.closest('a, button')) {
            return;
        }

        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            window.location.href = annualDay.getAttribute('data-day-link');
        }
    });

    document.querySelectorAll(annualPopoverSelector).forEach(ensureAnnualPopover);
});
