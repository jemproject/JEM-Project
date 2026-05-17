<?php
/**
 * @package    JEM
 * @subpackage JEM Map Module
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;

BaseDatabaseModel::addIncludePath(JPATH_SITE . '/components/com_jem/models', 'JemModel');
require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
if (is_file(JPATH_SITE . '/components/com_jem/helpers/map.php')) {
    require_once JPATH_SITE . '/components/com_jem/helpers/map.php';
}

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
        return JemMapHelper::getVenues($params, $filterStartDate, $filterEndDate, $selectedCategoryId);
    }

    /**
     * Fetch categories available for the frontend filter.
     *
     * @return  array<object>
     */
    public static function getCategories($params)
    {
        return JemMapHelper::getCategories($params);
    }
}
