<?php
/**
 * @package    JEM
 * @subpackage JEM Map Module
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
 
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Helper\ModuleHelper;    

BaseDatabaseModel::addIncludePath(JPATH_SITE.'/components/com_jem/models', 'JemModel');

class ModJemMapHelper extends ModuleHelper
{
    /**
     * Fetch venues:
     * - Without date: all venues with lat/lng.
     * - With date: INNER JOIN with events + filter (single-day or interval).
     *
     * @param \Joomla\Registry\Registry $params
     * @param string|null $filterDate   'YYYY-MM-DD' or null
     * @return array<object>
     */
    public static function getVenues($params, $filterDate = null)
    {
        $db = Factory::getDbo();
        $q  = $db->getQuery(true);

        $q->select('DISTINCT v.id, v.venue, v.alias, v.city, v.latitude, v.longitude, v.country')
          ->from($db->quoteName('#__jem_venues', 'v'))
          ->where('v.latitude IS NOT NULL')
          ->where("v.latitude <> ''")
          ->where('v.longitude IS NOT NULL')
          ->where("v.longitude <> ''");

        // Only apply JOIN + date filter when filterDate is truly valid (YYYY-MM-DD)
        if ($filterDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
            $d = $db->quote($filterDate);

            $q->join('INNER', $db->quoteName('#__jem_events', 'e') . ' ON e.locid = v.id');

            // SINGLE-DAY: e.dates = d
            // MULTI-DAY: e.enddates IS NOT NULL AND e.dates <= d AND d <= e.enddates
            // (No comparisons with empty string or '0000-00-00' → prevents error 1525)
            $q->where('(
                e.dates = ' . $d . '
                OR (
                    e.enddates IS NOT NULL
                    AND e.dates <= ' . $d . '
                    AND ' . $d . ' <= e.enddates
                )
            )');
        }

        $q->order('v.venue ASC');

        $db->setQuery($q);
        return $db->loadObjectList();
    }
}
