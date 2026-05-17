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
     * @param   string|null $filterStartDate    The start date of the filter range ('YYYY-MM-DD'). If null, no date filter is applied.
     * @param   string|null $filterEndDate      The end date of the filter range ('YYYY-MM-DD'). If null, the range is open-ended (from start date to infinity).
     * @param   int         $selectedCategoryId Category selected in the frontend filter.
     *
     * @return  array<object> An array of venue objects.
     * @throws  \Exception if a database error occurs.
     */
    public static function getVenues($params, $filterStartDate, $filterEndDate = null, $selectedCategoryId = 0)
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true);
        $dateFilterApplied = ($filterStartDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterStartDate));

        // filter category's
        $catids = JemHelper::getValidIds($params->get('catid'));
        $selectedCategoryId = (int) $selectedCategoryId;
        $filterCatids = $catids;

        if ($selectedCategoryId > 0) {
            if (empty($catids) || in_array($selectedCategoryId, $catids, true)) {
                $filterCatids = [$selectedCategoryId];
            } else {
                // Request tampering or a stale URL outside the module's configured category scope.
                $filterCatids = [0];
            }
        }

        $categoryFilterApplied = !empty($filterCatids);

        $query->select('DISTINCT v.id, v.venue, v.alias, v.city, v.latitude, v.longitude, v.country')
            ->from($db->quoteName('#__jem_venues', 'v'))
            ->where(['v.latitude IS NOT NULL',"v.latitude <> ''",'v.longitude IS NOT NULL',"v.longitude <> ''"]);

        // Apply date filtering only if a start date is provided.
        if ($dateFilterApplied || $categoryFilterApplied) {
            $query->join('INNER', $db->quoteName('#__jem_events', 'e'), 'e.locid = v.id');

            if ($categoryFilterApplied) {
                $catidIn = implode(',', array_map('intval', $filterCatids));
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

    /**
     * Fetch categories available for the frontend filter.
     *
     * @return  array<object>
     */
    public static function getCategories($params)
    {
        $db     = Factory::getDbo();
        $user   = Factory::getApplication()->getIdentity();
        $levels = $user->getAuthorisedViewLevels();
        $catids = JemHelper::getValidIds($params->get('catid'));
        $query  = $db->getQuery(true);

        $query->select($db->quoteName(['id', 'catname']))
            ->from($db->quoteName('#__jem_categories'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('catname') . ' <> ' . $db->quote('root'))
            ->order($db->quoteName('lft') . ' ASC');

        if (!empty($levels)) {
            $query->where($db->quoteName('access') . ' IN (' . implode(',', array_map('intval', $levels)) . ')');
        }

        if (!empty($catids)) {
            $query->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $catids)) . ')');
        }

        $db->setQuery($query);

        return $db->loadObjectList();
    }
}
