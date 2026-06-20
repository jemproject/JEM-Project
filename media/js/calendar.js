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
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        var options = {html: true};

        if (tooltipTriggerEl.classList.contains('jem-annual-day')) {
            options.trigger = 'hover focus click';
            options.delay = {show: 100, hide: 450};
        }

        return new bootstrap.Tooltip(tooltipTriggerEl, options)
    })
});
