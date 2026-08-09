/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

(function (document) {
    'use strict';

    function isStrictCalendarDate(value, field) {
        var inputValue = String(value || '').trim();

        // JEM deliberately supports events without a date.
        if (inputValue === '') {
            return true;
        }

        var wrapper = field.closest('.field-calendar');
        var calendar = wrapper && wrapper._joomlaCalendar;

        if (!calendar || typeof Date.parseFieldDate !== 'function') {
            return false;
        }

        var parsed = Date.parseFieldDate(
            inputValue,
            calendar.params.dateFormat,
            calendar.params.dateType,
            calendar.strings
        );
        var roundTrip = parsed.print(
            calendar.params.dateFormat,
            calendar.params.dateType,
            true,
            calendar.strings
        );

        // Joomla's parser rolls impossible dates into another month or year.
        // Comparing with the rendered value detects that rollover before submit.
        return roundTrip === inputValue;
    }

    function registerValidator() {
        if (!document.formvalidator) {
            return;
        }

        document.formvalidator.setHandler('jemdate', isStrictCalendarDate);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', registerValidator);
    } else {
        registerValidator();
    }
})(document);
