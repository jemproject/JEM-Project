/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
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

    if ($('filter_continent')) {
        $('filter_continent').on('change', function () {
            if (country = $('filter_country')) {
                country.selectedIndex = 0;
            }
            if (city = $('filter_city')) {
                city.selectedIndex = 0;
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
