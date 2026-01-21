<?php
/**
 * @package    JEM
 * @subpackage JEM Map Module
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\Query\SelectQuery;

BaseDatabaseModel::addIncludePath(JPATH_SITE . '/components/com_jem/models', 'JemModel');

/**
 * Helper class for the JEM Map module.
 */
class ModJemMapHelper
{
    /**
     * Fetches venues with valid coordinates.
     * Optionally filters venues to include only those hosting events within a given date range and categories.
     *
     * @param   string|null $filterStartDate  The start date of the filter range ('YYYY-MM-DD'). If null, no date filter is applied.
     * @param   string|null $filterEndDate    The end date of the filter range ('YYYY-MM-DD'). If null, the range is open-ended (from start date to infinity).
     *
     * @return  array<object> An array of venue objects.
     * @throws  \Exception if a database error occurs.
     */
    public static function getVenues($params, $filterStartDate, $filterEndDate = null)
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true);
        $dateFilterApplied = ($filterStartDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterStartDate));

        // filter category's
        $catids = JemHelper::getValidIds($params->get('catid'));
        $categoryFilterApplied = !empty($catids);
        
        $query->select('DISTINCT v.id, v.venue, v.alias, v.city, v.latitude, v.longitude, v.country')
            ->from($db->quoteName('#__jem_venues', 'v'))
            ->where(['v.latitude IS NOT NULL',"v.latitude <> ''",'v.longitude IS NOT NULL',"v.longitude <> ''"]);

        // Apply date filtering only if a start date is provided.
        if ($dateFilterApplied || $categoryFilterApplied) {
            $query->join('INNER', $db->quoteName('#__jem_events', 'e'), 'e.locid = v.id');

            if ($categoryFilterApplied) {
                $catidIn = implode(',', $catids);
                $query->join('INNER', $db->quoteName('#__jem_cats_event_relations', 'cr'), $db->quoteName('cr.itemid') . ' = ' . $db->quoteName('e.id'));
                $query->where($db->quoteName('cr.catid') . ' IN (' . $catidIn . ')');
            }

            $effectiveEventEndDate = 'COALESCE(' . $db->quoteName('e.enddates') . ', ' . $db->quoteName('e.dates') . ')';
            $conditions = [];
            if ($filterEndDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterEndDate)) {
                $conditions[] = $db->quoteName('e.dates') . ' <= ' . $db->quote($filterEndDate);
                $conditions[] = $effectiveEventEndDate . ' >= ' . $db->quote($filterStartDate);
            } else {
                $conditions[] = $effectiveEventEndDate . ' >= ' . $db->quote($filterStartDate);
            }

            $query->where($conditions);
        }

        $query->order($db->quoteName('v.venue') . ' ASC');
        $db->setQuery($query);

        return $db->loadObjectList();
    }
}