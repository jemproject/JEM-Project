/**
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 * @author     Sascha Karnatz
 */

// window.addEvent('domready', function(){
jQuery(document).ready(function ($) {

    /*
    $('filter_date').addEvent('change', function() {
        this.form.submit();
    });
    */

    if ($('#filter_continent').length) {
        $('#filter_continent').on('change', function () {
            if ($('#filter_country').length) {
                $('#filter_country').val('');
            }
            if ($('#filter_city').length) {
                $('#filter_city').val('');
            }
            this.form.submit();
        });
    }

    if (country = $('filter_country')) {
        country.on('change', function () {
            if (city = $('filter_city')) {
                city.selectedIndex = 0;
            }
            this.form.submit();
        });
    }

    if (city = $('filter_city')) {
        city.on('change', function () {
            this.form.submit();
        });
    }

    if ($('filter_category')) {
        $('filter_category').on('change', function () {
            this.form.submit();
        });
    }
});
