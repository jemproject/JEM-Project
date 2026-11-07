<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

require_once JPATH_SITE . '/components/com_jem/helpers/map.php';
require_once JPATH_SITE . '/components/com_jem/helpers/mapdate.php';

use Joomla\Component\Jem\Site\Helper\JemMapDateHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\Component\Jem\Site\Helper\JemMapHelper;

/**
 * Raw: Eventsmap
 */
class JemViewEventsMap extends HtmlView
{
    /**
     * Creates the PDF output for the Events Map view.
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();

        if ($app->input->getCmd('layout', '') !== 'pdf') {
            $app->close();

            return;
        }

        $params = $app->getParams();
        $filters = $this->getMapFilters($params);
        $rows = JemMapHelper::getEvents(
            $params,
            $filters['start'],
            $filters['end'],
            $filters['category'],
            $filters['country'],
            $filters['county'],
            $filters['city']
        );
        $title = $this->getPdfTitle($app, Text::_('COM_JEM_EVENTS_MAP'));

        JemPdfView::renderEventsMapList(
            $title,
            (array) $rows,
            'jem-events-map.pdf',
            (string) $params->get('map_provider', 'osm') === 'google' ? 'google' : 'osm',
            max(0, min(19, (int) $params->get('map_zoom', 4))),
            [
                'country' => (bool) $params->get('show_table_country', 1),
                'county' => (bool) $params->get('show_table_county', 1),
                'city' => (bool) $params->get('show_table_city', 1),
            ]
        );
    }

    /**
     * Returns the active map filters for the PDF export.
     */
    private function getMapFilters($params): array
    {
        $app = Factory::getApplication();
        $showDateFilter = (int) $params->get('show_date_filter', 0);
        $showCategoryFilter = (int) $params->get('show_category_filter', 0);
        $showCountryFilter = (int) $params->get('show_country_filter', 0);
        $defaultCountry = trim((string) $params->get('default_country', ''));
        $filterMode = 'all';
        $filterDate = '';
        $filterStartDate = null;
        $filterEndDate = null;

        if ($defaultCountry === '0') {
            $defaultCountry = '';
        }

        if ($showDateFilter) {
            $period = JemMapDateHelper::resolve(
                $app->input->getString('jem_map_filter_mode', (string) $params->get('date_filter_default', 'all')),
                $app->input->getString('jem_map_filter_date', ''),
                new \DateTimeImmutable('now', new \DateTimeZone($app->get('offset', 'UTC'))),
                (int) \JemHelper::config()->weekdaystart,
                $app->input->getInt('jem_map_filter_step', 0)
            );
            $filterMode = $period['mode'];
            $filterDate = $period['date'];
            $filterStartDate = $period['start'];
            $filterEndDate = $period['end'];
        }

        $selectedCountry = $defaultCountry;

        if ($showCountryFilter && $app->input->exists('jem_map_filter_country')) {
            $selectedCountry = trim($app->input->getString('jem_map_filter_country', ''));
        }

        $locations = JemMapHelper::getEventLocationFilters($params, $app->input, $selectedCountry);

        return array(
            'county' => $locations['county'],
            'city' => $locations['city'],
            'start' => $filterStartDate,
            'end' => $filterEndDate,
            'category' => $showCategoryFilter ? $app->input->getInt('jem_map_filter_catid', 0) : 0,
            'country' => $showCountryFilter ? $selectedCountry : $defaultCountry,
        );
    }

    /**
     * Returns the view/menu title for the PDF.
     */
    private function getPdfTitle($app, string $fallback): string
    {
        $params = $app->getParams();
        $menu = $app->getMenu();
        $menuActive = $menu ? $menu->getActive() : null;
        $title = trim((string) $params->get('page_heading', ''));

        if ($title === '') {
            $title = trim((string) $params->get('page_title', ''));
        }

        if ($title === '' && $menuActive) {
            $title = trim((string) $menuActive->title);
        }

        return $title !== '' ? $title : $fallback;
    }
}
