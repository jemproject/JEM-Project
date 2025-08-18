<?php
/**
 * @package    JEM
 * @subpackage JEM Calendar Module
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;

$mod_name = 'mod_jem_map';

require_once __DIR__ . '/helper.php';

# Create JEM's file logger (for debug)
JemHelper::addFileLogger();

# Parameters
$venueMarker     = $params->get('venue_markerfile', 'media/com_jem/images/marker.png');
$mylocMarker     = $params->get('mylocation_markerfile', 'media/com_jem/images/marker-red.png');
$height          = $params->get('height', '500px');
$zoom            = (int) $params->get('zoom', 8);
$showDateFilter  = (int) $params->get('show_date_filter', 1);
$jemItemid       = (int) $params->get('jem_itemid', 0);

// Filter from request (only if backend option is enabled)
$app         = Factory::getApplication();
$filterMode  = 'all';
$filterDate  = null;
$selectedRaw = '';

if ($showDateFilter) {
    $filterMode  = $app->input->get('jemfilter', 'all', 'cmd');   // 'all' of 'date'
    $selectedRaw = $app->input->get('jemdate', '', 'string');     // verwacht 'YYYY-MM-DD'

    if ($filterMode === 'date') {
        // Strikte validatie: alleen geldige YYYY-MM-DD doorlaten
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedRaw)) {
            $dt = \DateTime::createFromFormat('Y-m-d', $selectedRaw);
            if ($dt && $dt->format('Y-m-d') === $selectedRaw) {
                $filterDate = $selectedRaw;
            }
        }
        // Invalid date? Fall back to 'all' (prevents SQL error)
        if ($filterDate === null) {
            $filterMode = 'all';
        }
    }
}

// Fetch venues (JOIN + date filter only if $filterDate is not null)
$venues = ModJemMapHelper::getVenues($params, $filterDate);

// Render layout
require ModuleHelper::getLayoutPath($mod_name, $params->get('layout', 'default'));
