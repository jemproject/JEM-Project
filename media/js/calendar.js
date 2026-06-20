// window.addEvent('domready', function() {
jQuery(document).ready(function ($) {
    var allHidden = false;

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
        var hiddenCategories = [];
        var hiddenVenues = [];

        $('#jlcalendarlegend .eventCat.catoff').each(function () {
            hiddenCategories.push($(this).data('filterClass') || $(this).attr('id'));
        });

        $('#jlcalendarlegend .eventVenues.catoff').each(function () {
            hiddenVenues.push($(this).data('filterClass') || $(this).attr('id'));
        });

        $('.jlcalendar .event-filter').each(function () {
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
    }

    /* categories filtering */
    $(document).on('click', '#jlcalendarlegend .eventCat', function (event) {
        event.preventDefault();
        allHidden = false;
        updateGlobalButtons(null);
        $(this).toggleClass('catoff');
        refreshCalendarFilters();
    });

    /* venues filtering */
    $(document).on('click', '#jlcalendarlegend .eventVenues', function (event) {
        event.preventDefault();
        allHidden = false;
        updateGlobalButtons(null);
        $(this).toggleClass('catoff');
        refreshCalendarFilters();
    });

    /* Show all */
    $(document).on('click', '#buttonshowall', function (event) {
        event.preventDefault();
        allHidden = false;
        updateGlobalButtons(this);
        $('#jlcalendarlegend .eventCat, #jlcalendarlegend .eventVenues').removeClass('catoff');
        refreshCalendarFilters();
    });

    /* Hide all */
    $(document).on('click', '#buttonhideall', function (event) {
        event.preventDefault();
        allHidden = true;
        updateGlobalButtons(this);
        $('#jlcalendarlegend .eventCat, #jlcalendarlegend .eventVenues').addClass('catoff');
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
