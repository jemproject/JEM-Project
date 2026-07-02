<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

if (is_file(JPATH_SITE . '/components/com_jem/helpers/map.php')) {
    require_once JPATH_SITE . '/components/com_jem/helpers/map.php';
}

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\Component\Jem\Site\Helper\JemMapHelper;

/**
 * Raw: Venuesmap
 */
class JemViewVenuesMap extends HtmlView
{
    /**
     * Creates the PDF output for the Venues Map view.
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();

        if ($app->input->getCmd('layout', '') !== 'pdf') {
            $app->close();

            return;
        }

        $params = $app->getParams();
        $mapProvider = (string) $params->get('map_provider', 'osm') === 'google' ? 'google' : 'osm';
        $showVenueImage = (int) $params->get('showvenueimage', 1) === 1;
        $settings = JemHelper::globalattribs();
        $showVenueDescription = (int) $params->get('showvenuedescription', 1) === 1
            && (!$settings || !method_exists($settings, 'get') || (int) $settings->get('global_show_locdescription', 1) === 1);
        $showCountryFilter = (int) $params->get('show_country_filter', 1);
        $showCategoryFilter = (int) $params->get('show_category_filter', 0);
        $venueOrder = (string) $params->get('venues_order', 'name_asc');
        $defaultCountry = trim((string) $params->get('default_country', ''));
        $defaultCountry = $defaultCountry === '0' ? '' : $defaultCountry;
        $selectedCountry = $showCountryFilter ? trim($app->input->getString('jem_map_filter_country', $defaultCountry)) : $defaultCountry;
        $selectedCity = $showCountryFilter ? trim($app->input->getString('jem_map_filter_city', '')) : '';
        $selectedCategoryId = $showCategoryFilter ? $app->input->getInt('jem_map_filter_catid', 0) : 0;
        $categoryStartDate = $selectedCategoryId > 0 ? Factory::getDate()->format('Y-m-d') : null;
        $rows = is_callable(array(JemMapHelper::class, 'getVenues'))
            ? JemMapHelper::getVenues($params, $categoryStartDate, null, $selectedCategoryId, $selectedCountry, $selectedCity, $venueOrder)
            : array();

        JemPdfView::renderVenueList(Text::_('COM_JEM_VENUES_MAP'), (array) $rows, 'jem-venues-map.pdf', 'map', $mapProvider, $showVenueImage, $showVenueDescription);
    }
}
