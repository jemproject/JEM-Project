/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 * @author     Sascha Karnatz
 */

jQuery(document).ready(function ($) {

    if ($('#filter_continent').length) {
        $('#filter_continent').on('change', function () {
            if ($('#filter_country').length) { $('#filter_country').val(''); }
            if ($('#filter_city').length) { $('#filter_city').val(''); }
            if ($('#filter_venue_id').length) { $('#filter_venue_id').val('0'); }
            this.form.submit();
        });
    }

    if ($('#filter_country').length) {
        $('#filter_country').on('change', function () {
            if ($('#filter_city').length) { $('#filter_city').val(''); }
            if ($('#filter_venue_id').length) { $('#filter_venue_id').val('0'); }
            this.form.submit();
        });
    }

    if ($('#filter_city').length) {
        $('#filter_city').on('change', function () {
            this.form.submit();
        });
    }

    if ($('#filter_category').length) {
        $('#filter_category').on('change', function () {
            this.form.submit();
        });
    }

    if ($('#filter_type_id').length) {
        $('#filter_type_id').on('change', function () {
            this.form.submit();
        });
    }

    if ($('#filter_venue_id').length) {
        $('#filter_venue_id').on('change', function () {
            this.form.submit();
        });
    }
});
