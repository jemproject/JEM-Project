<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

require_once JPATH_SITE . '/components/com_jem/helpers/map.php';

use Joomla\CMS\Date\Date;
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
            $filters['country']
        );
        $title = $this->getPdfTitle($app, Text::_('COM_JEM_EVENTS_MAP'));

        JemPdfView::renderEventsMapList(
            $title,
            (array) $rows,
            'jem-events-map.pdf',
            (string) $params->get('map_provider', 'osm') === 'google' ? 'google' : 'osm',
            max(0, min(19, (int) $params->get('map_zoom', 4)))
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
            $filterMode = $app->input->get('jem_map_filter_mode', $params->get('date_filter_default', 'all'), 'string');
            $filterDate = $app->input->get('jem_map_filter_date', '', 'string');
            $now = Factory::getDate();

            switch ($filterMode) {
                case 'date':
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
                        $date = Date::createFromFormat('Y-m-d', $filterDate);

                        if ($date && $date->format('Y-m-d') === $filterDate) {
                            $filterStartDate = $filterDate;
                            $filterEndDate = $filterDate;
                        }
                    }
                    break;

                case 'today':
                    $filterStartDate = $now->format('Y-m-d');
                    $filterEndDate = $now->format('Y-m-d');
                    break;

                case 'tomorrow':
                    $tomorrow = clone $now;
                    $tomorrow->modify('+1 day');
                    $filterStartDate = $tomorrow->format('Y-m-d');
                    $filterEndDate = $tomorrow->format('Y-m-d');
                    break;

                case 'week':
                    $endOfWeek = clone $now;
                    $endOfWeek->modify('Sunday this week');
                    $filterStartDate = $now->format('Y-m-d');
                    $filterEndDate = $endOfWeek->format('Y-m-d');
                    break;

                case 'month':
                    $endOfMonth = clone $now;
                    $endOfMonth->modify('last day of this month');
                    $filterStartDate = $now->format('Y-m-d');
                    $filterEndDate = $endOfMonth->format('Y-m-d');
                    break;

                case 'year':
                    $endOfYear = clone $now;
                    $endOfYear->setDate((int) $now->format('Y'), 12, 31);
                    $filterStartDate = $now->format('Y-m-d');
                    $filterEndDate = $endOfYear->format('Y-m-d');
                    break;
            }
        }

        $selectedCountry = $defaultCountry;

        if ($showCountryFilter && $app->input->exists('jem_map_filter_country')) {
            $selectedCountry = trim($app->input->getString('jem_map_filter_country', ''));
        }

        return array(
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
