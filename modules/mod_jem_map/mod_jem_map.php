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
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Date\Date;

$mod_name = 'mod_jem_map';

require_once __DIR__ . '/helper.php';
require_once(JPATH_SITE.'/components/com_jem/helpers/helper.php');

$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('user-stylesheet', Uri::base() . "modules/mod_jem_map/tmpl/mod_jem_map.css");

# Parameters
$venueMarker = $params->get('venue_markerfile', 'media/com_jem/images/marker.webp');
$mylocMarker = $params->get('mylocation_markerfile', 'media/com_jem/images/marker-red.webp');

$venueMarker = rtrim(Uri::root(), '/') . '/' . ltrim((string) $venueMarker, '/');
$mylocMarker = rtrim(Uri::root(), '/') . '/' . ltrim((string) $mylocMarker, '/');

$height = $params->get('height', '500px');
$zoom = (int) $params->get('zoom', 8);
$showDateFilter = (int) $params->get('show_date_filter', 0);
$dateFilterDefault = $params->get('date_filter_default', 'today');

// Filter from request (only if backend option is enabled)
$filterMode = 'all';
$filterDate = null;
$selectedDate = '';

$filterStartDate = null;
$filterEndDate   = null;

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
$venues = ModJemMapHelper::getVenues($params, $filterStartDate, $filterEndDate);

// Get auto center map
$centerLat = $centerLng = 0;
$totalLat = $totalLng= 0;
$countVenues = 0;
if($params->get('map_auto_center',1)){
    foreach ($venues as $venue) {
        if (!empty($venue->latitude) && !empty($venue->longitude)) {
            $totalLat += (float)$venue->latitude;
            $totalLng += (float)$venue->longitude;
            $countVenues++;
        }
    }

    if ($countVenues > 0) {
        $centerLat = $totalLat / $countVenues;
        $centerLng = $totalLng / $countVenues   ;
    }
}

// Render layout
require ModuleHelper::getLayoutPath($mod_name, $params->get('layout', 'default'));
