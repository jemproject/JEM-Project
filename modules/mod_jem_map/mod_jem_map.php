<?php
/**
 * @package    JEM
 * @subpackage JEM Calendar Module
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Date\Date;
use Joomla\Component\Jem\Site\Helper\JemMapHelper;
use Joomla\Module\JemMap\Site\Helper\ModJemMapHelper;

$mod_name = 'mod_jem_map';

require_once __DIR__ . '/helper.php';
require_once(JPATH_SITE.'/components/com_jem/helpers/helper.php');
require_once(JPATH_SITE.'/components/com_jem/helpers/countries.php');

$app = Factory::getApplication();


# Parameters
$venueMarker = JemMapHelper::resolveMarkerUrl($params->get('venue_markerfile', 'media/com_jem/images/marker-red.webp'), 'media/com_jem/images/marker-red.webp');
$mylocMarker = JemMapHelper::resolveMarkerUrl($params->get('mylocation_markerfile', 'media/com_jem/images/marker-blue.webp'), 'media/com_jem/images/marker-blue.webp');

$height = $params->get('height', '500px');
$showDateFilter = (int) $params->get('show_date_filter', 0);
$showCategoryFilter = (int) $params->get('show_category_filter', 0);
$showCountryFilter = (int) $params->get('show_country_filter', 0);
$dateFilterDefault = $params->get('date_filter_default', 'all');
$defaultCountry = trim((string) $params->get('default_country', ''));

if ($defaultCountry === '0') {
    $defaultCountry = '';
}

// Filter from request (only if backend option is enabled)
$filterMode = 'all';
$filterDate = null;
$selectedDate = '';

$filterStartDate = null;
$filterEndDate   = null;
$selectedCategoryId = $showCategoryFilter ? $app->input->getInt('jem_map_filter_catid', 0) : 0;
$selectedCountry = $showCountryFilter
    ? trim($app->input->getString('jem_map_filter_country', $defaultCountry))
    : $defaultCountry;

if ($showDateFilter) {
    $filterMode = $app->input->get('jem_map_filter_mode', $dateFilterDefault, 'string');
    $filterDate = $app->input->get('jem_map_filter_date', '', 'string');

    if ($filterMode == 'date' && $filterDate === null) {
        $filterMode = 'all';
    }

    // Get the current date
    $now = Factory::getDate();

    switch ($filterMode){
        case 'date':
            // Filter for a single, specific date selected by the user.
            $selectedDate = $app->input->get('jem_map_filter_date', '', 'string');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
                $dt = Date::createFromFormat('Y-m-d', $selectedDate);
                if ($dt && $dt->format('Y-m-d') === $selectedDate) {
                    $filterStartDate = $selectedDate;
                    $filterEndDate   = $selectedDate;
                }
            }
            break;
        case 'today':
            // Shows events from today onwards, ending today.
            $filterStartDate = $now->format('Y-m-d');
            $filterEndDate   = $now->format('Y-m-d');
            break;

        case 'tomorrow':
            // Shows events for tomorrow.
            $tomorrow        = $now->modify('+1 day');
            $filterStartDate = $tomorrow->format('Y-m-d');
            $filterEndDate   = $tomorrow->format('Y-m-d');
            break;

        case 'week':
            // Shows events from today until the end of the current week (Sunday).
            $filterStartDate = $now->format('Y-m-d');
            $endOfWeek       = $now->modify('Sunday this week');
            $filterEndDate   = $endOfWeek->format('Y-m-d');
            break;

        case 'month':
            // Shows events from today until the last day of the current month.
            $filterStartDate = $now->format('Y-m-d');
            $endOfMonth      = $now->modify('last day of this month');
            $filterEndDate   = $endOfMonth->format('Y-m-d');
            break;

        case 'year':
            // Shows events from today until the last day of the current year (Dec 31).
            $filterStartDate = $now->format('Y-m-d');
            $endOfYear       = $now->setDate((int) $now->format('Y'), 12, 31);
            $filterEndDate   = $endOfYear->format('Y-m-d');
            break;

        case 'all':
        default:
            // Shows all venues with or without events.
            break;
    }
}

// Fetch venues (JOIN + date filter only if $filterDate is not null)
$categories = $showCategoryFilter ? ModJemMapHelper::getCategories($params) : array();
$countries = $showCountryFilter ? ModJemMapHelper::getVenueCountries() : array();

if ($countries) {
    foreach ($countries as $country) {
        $countryCode = (string) $country->country;
        $countryName = JemHelperCountries::getCountryName($countryCode);
        $country->country_name = $countryName ?: $countryCode;
    }

    usort(
        $countries,
        static function ($a, $b) {
            return strcasecmp((string) $a->country_name, (string) $b->country_name);
        }
    );
}

if ($selectedCategoryId > 0) {
    $validCategoryIds = array_map('intval', array_column($categories, 'id'));

    if (!in_array($selectedCategoryId, $validCategoryIds, true)) {
        $selectedCategoryId = 0;
    }
}

if ($selectedCountry !== '') {
    $validCountries = array_map(
        static function ($country) {
            return (string) $country->country;
        },
        $countries
    );

    if ($showCountryFilter && !in_array($selectedCountry, $validCountries, true)) {
        $selectedCountry = '';
    }
}

$venues = ModJemMapHelper::getVenues($params, $filterStartDate, $filterEndDate, $selectedCategoryId, $selectedCountry);

// Get auto center map
if($params->get('map_auto_center',1)){
    [$centerLat, $centerLng] = JemMapHelper::getCenter($venues);
} else {
    $centerLat = $centerLng = 0;
}

$layout = substr(strstr($params->get('layout', 'default'), ':'), 1);

JemHelper::loadModuleStyleSheet($mod_name, $layout);

// Render layout
require ModuleHelper::getLayoutPath($mod_name, $params->get('layout', 'default'));
JemHelper::loadModuleUserCss();
