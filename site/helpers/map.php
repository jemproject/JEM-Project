<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

namespace Joomla\Component\Jem\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\File;

require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';

/**
 * Shared map data helper for JEM map module and map menu views.
 */
class JemMapHelper
{
    /**
     * Fetch venues with valid coordinates.
     *
     * Date and category filters join against events, but the default output is venues.
     *
     * @return  array<object>
     */
    public static function getVenues($params, $filterStartDate = null, $filterEndDate = null, $selectedCategoryId = 0, $country = '', $city = '')
    {
        $db       = Factory::getDbo();
        $user     = Factory::getApplication()->getIdentity();
        $levels   = $user->getAuthorisedViewLevels();
        $settings = \JemHelper::config();
        $eventAccess = self::accessList($levels, $settings->access_level_locked_events ?? '["1"]');
        $venueAccess = self::accessList($levels, $settings->access_level_locked_venues ?? '["1"]');
        $categoryAccess = self::accessList($levels, $settings->access_level_locked_categories ?? '["1"]');
        $query    = $db->getQuery(true);
        $catids   = self::getFilterCategoryIds($params, (int) $selectedCategoryId);
        $dateUsed = self::isDate($filterStartDate);
        $catUsed  = !empty($catids);
        $country  = trim((string) $country);
        $city     = trim((string) $city);

        $query->select('DISTINCT v.id, v.venue, v.alias, v.city, v.latitude, v.longitude, v.country, v.created_by, v.checked_out AS vChecked_out, v.checked_out_time AS vChecked_out_time')
            ->from($db->quoteName('#__jem_venues', 'v'))
            ->where($db->quoteName('v.published') . ' = 1')
            ->where([
                'v.latitude IS NOT NULL',
                "v.latitude <> ''",
                'v.longitude IS NOT NULL',
                "v.longitude <> ''",
            ]);

        if ($venueAccess !== '') {
            $query->where($db->quoteName('v.access') . ' IN (' . $venueAccess . ')');
        }

        if ($country !== '') {
            $query->where($db->quoteName('v.country') . ' = ' . $db->quote($country));
        }

        if ($city !== '') {
            $query->where($db->quoteName('v.city') . ' = ' . $db->quote($city));
        }

        if ($dateUsed || $catUsed) {
            $query->join('INNER', $db->quoteName('#__jem_events', 'e') . ' ON ' . $db->quoteName('e.locid') . ' = ' . $db->quoteName('v.id'));
            $query->join('INNER', $db->quoteName('#__jem_cats_event_relations', 'cr') . ' ON ' . $db->quoteName('cr.itemid') . ' = ' . $db->quoteName('e.id'));
            $query->join('INNER', $db->quoteName('#__jem_categories', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('cr.catid'));
            $query->join('LEFT', $db->quoteName('#__jem_types', 't') . ' ON ' . $db->quoteName('t.id') . ' = ' . $db->quoteName('e.type_id') . ' AND ' . $db->quoteName('t.entity') . ' = 1 AND ' . $db->quoteName('t.published') . ' = 1');
            $query->where($db->quoteName('e.published') . ' = 1');
            $query->where($db->quoteName('c.published') . ' = 1');
            self::applyPublishWindow($query, 'e');

            if ($eventAccess !== '') {
                $query->where($db->quoteName('e.access') . ' IN (' . $eventAccess . ')');
            }

            if ($categoryAccess !== '') {
                $query->where($db->quoteName('c.access') . ' IN (' . $categoryAccess . ')');
            }

            $typeAccess = self::accessList($levels);
            if ($typeAccess !== '') {
                $query->where('(' . $db->quoteName('e.type_id') . ' IS NULL OR ' . $db->quoteName('e.type_id') . ' = 0 OR ' . $db->quoteName('t.access') . ' IN (' . $typeAccess . '))');
            }

            if ($catUsed) {
                $query->where($db->quoteName('cr.catid') . ' IN (' . implode(',', array_map('intval', $catids)) . ')');
            }

            self::applyDateRange($query, 'e', $filterStartDate, $filterEndDate);
        }

        $query->order($db->quoteName('v.venue') . ' ASC');
        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Fetch countries that have published venues with valid coordinates.
     *
     * @return  array<object>
     */
    public static function getVenueCountries()
    {
        $db     = Factory::getDbo();
        $user   = Factory::getApplication()->getIdentity();
        $levels = $user->getAuthorisedViewLevels();
        $settings = \JemHelper::config();
        $venueAccess = self::accessList($levels, $settings->access_level_locked_venues ?? '["1"]');
        $countryColumns = $db->getTableColumns('#__jem_countries');
        $query  = $db->getQuery(true);

        $query->select('DISTINCT ' . $db->quoteName('v.country', 'country'))
            ->from($db->quoteName('#__jem_venues', 'v'))
            ->join('INNER', $db->quoteName('#__jem_countries', 'ct') . ' ON ' . $db->quoteName('ct.iso2') . ' = ' . $db->quoteName('v.country'))
            ->where($db->quoteName('v.published') . ' = 1')
            ->where($db->quoteName('v.country') . ' <> ' . $db->quote(''))
            ->where([
                'v.latitude IS NOT NULL',
                "v.latitude <> ''",
                'v.longitude IS NOT NULL',
                "v.longitude <> ''",
            ])
            ->order($db->quoteName('v.country') . ' ASC');

        if (isset($countryColumns['published'])) {
            $query->where($db->quoteName('ct.published') . ' = 1');
        }

        if ($venueAccess !== '') {
            $query->where($db->quoteName('v.access') . ' IN (' . $venueAccess . ')');
        }

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Fetch cities for a country that has published venues with valid coordinates.
     *
     * @return  array<object>
     */
    public static function getVenueCities($country)
    {
        $country = trim((string) $country);

        if ($country === '') {
            return [];
        }

        $db     = Factory::getDbo();
        $user   = Factory::getApplication()->getIdentity();
        $levels = $user->getAuthorisedViewLevels();
        $settings = \JemHelper::config();
        $venueAccess = self::accessList($levels, $settings->access_level_locked_venues ?? '["1"]');
        $query  = $db->getQuery(true);

        $query->select('DISTINCT ' . $db->quoteName('city'))
            ->from($db->quoteName('#__jem_venues'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('country') . ' = ' . $db->quote($country))
            ->where($db->quoteName('city') . ' <> ' . $db->quote(''))
            ->where([
                'latitude IS NOT NULL',
                "latitude <> ''",
                'longitude IS NOT NULL',
                "longitude <> ''",
            ])
            ->order($db->quoteName('city') . ' ASC');

        if ($venueAccess !== '') {
            $query->where($db->quoteName('access') . ' IN (' . $venueAccess . ')');
        }

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Fetch published events hosted at venues with valid coordinates.
     *
     * @return  array<object>
     */
    public static function getEvents($params, $filterStartDate = null, $filterEndDate = null, $selectedCategoryId = 0, $country = '')
    {
        $db       = Factory::getDbo();
        $user     = Factory::getApplication()->getIdentity();
        $levels   = $user->getAuthorisedViewLevels();
        $settings = \JemHelper::config();
        $eventAccess = self::accessList($levels, $settings->access_level_locked_events ?? '["1"]');
        $venueAccess = self::accessList($levels, $settings->access_level_locked_venues ?? '["1"]');
        $categoryAccess = self::accessList($levels, $settings->access_level_locked_categories ?? '["1"]');
        $query    = $db->getQuery(true);
        $catids   = self::getFilterCategoryIds($params, (int) $selectedCategoryId);
        $country  = trim((string) $country);

        $query->select([
                'DISTINCT e.id',
                'e.alias',
                'CASE WHEN CHAR_LENGTH(e.alias) THEN CONCAT_WS(\':\', e.id, e.alias) ELSE e.id END AS slug',
                'e.title',
                'e.dates',
                'e.times',
                'e.enddates',
                'e.endtimes',
                'e.event_status',
                'e.ticket_availability',
                'v.id AS venue_id',
                'v.venue',
                'v.alias AS venue_alias',
                'v.city',
                'v.state',
                'v.country',
                'v.latitude',
                'v.longitude',
            ])
            ->from($db->quoteName('#__jem_events', 'e'))
            ->join('INNER', $db->quoteName('#__jem_venues', 'v') . ' ON ' . $db->quoteName('v.id') . ' = ' . $db->quoteName('e.locid'))
            ->join('INNER', $db->quoteName('#__jem_cats_event_relations', 'cr') . ' ON ' . $db->quoteName('cr.itemid') . ' = ' . $db->quoteName('e.id'))
            ->join('INNER', $db->quoteName('#__jem_categories', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('cr.catid'))
            ->join('LEFT', $db->quoteName('#__jem_types', 't') . ' ON ' . $db->quoteName('t.id') . ' = ' . $db->quoteName('e.type_id') . ' AND ' . $db->quoteName('t.entity') . ' = 1 AND ' . $db->quoteName('t.published') . ' = 1')
            ->where($db->quoteName('e.published') . ' = 1')
            ->where($db->quoteName('v.published') . ' = 1')
            ->where($db->quoteName('c.published') . ' = 1')
            ->where([
                'v.latitude IS NOT NULL',
                "v.latitude <> ''",
                'v.longitude IS NOT NULL',
                "v.longitude <> ''",
            ]);

        self::applyPublishWindow($query, 'e');

        if ($eventAccess !== '') {
            $query->where($db->quoteName('e.access') . ' IN (' . $eventAccess . ')');
        }

        if ($venueAccess !== '') {
            $query->where($db->quoteName('v.access') . ' IN (' . $venueAccess . ')');
        }

        if ($categoryAccess !== '') {
            $query->where($db->quoteName('c.access') . ' IN (' . $categoryAccess . ')');
        }

        $typeAccess = self::accessList($levels);
        if ($typeAccess !== '') {
            $query->where('(' . $db->quoteName('e.type_id') . ' IS NULL OR ' . $db->quoteName('e.type_id') . ' = 0 OR ' . $db->quoteName('t.access') . ' IN (' . $typeAccess . '))');
        }

        if (!empty($catids)) {
            $query->where($db->quoteName('cr.catid') . ' IN (' . implode(',', array_map('intval', $catids)) . ')');
        }

        if ($country !== '') {
            $query->where($db->quoteName('v.country') . ' = ' . $db->quote($country));
        }

        self::applyDateRange($query, 'e', $filterStartDate, $filterEndDate);

        $query->order([
            $db->quoteName('e.dates') . ' ASC',
            $db->quoteName('e.times') . ' ASC',
            $db->quoteName('e.title') . ' ASC',
        ]);

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Fetch categories available for a map category filter.
     *
     * @return  array<object>
     */
    public static function getCategories($params)
    {
        $db     = Factory::getDbo();
        $user   = Factory::getApplication()->getIdentity();
        $levels = $user->getAuthorisedViewLevels();
        $settings = \JemHelper::config();
        $categoryAccess = self::accessList($levels, $settings->access_level_locked_categories ?? '["1"]');
        $catids = \JemHelper::getValidIds($params->get('catid'));
        $query  = $db->getQuery(true);

        $query->select($db->quoteName(['id', 'catname']))
            ->from($db->quoteName('#__jem_categories'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('catname') . ' <> ' . $db->quote('root'))
            ->order($db->quoteName('lft') . ' ASC');

        if ($categoryAccess !== '') {
            $query->where($db->quoteName('access') . ' IN (' . $categoryAccess . ')');
        }

        if (!empty($catids)) {
            $query->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $catids)) . ')');
        }

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Fetch categories that have published upcoming events.
     *
     * @return  array<object>
     */
    public static function getUpcomingEventCategories()
    {
        $db     = Factory::getDbo();
        $user   = Factory::getApplication()->getIdentity();
        $levels = $user->getAuthorisedViewLevels();
        $settings = \JemHelper::config();
        $eventAccess = self::accessList($levels, $settings->access_level_locked_events ?? '["1"]');
        $venueAccess = self::accessList($levels, $settings->access_level_locked_venues ?? '["1"]');
        $categoryAccess = self::accessList($levels, $settings->access_level_locked_categories ?? '["1"]');
        $today  = Factory::getDate()->format('Y-m-d');
        $query  = $db->getQuery(true);

        $query->select('DISTINCT ' . implode(', ', $db->quoteName(['c.id', 'c.catname', 'c.lft'])))
            ->from($db->quoteName('#__jem_categories', 'c'))
            ->join('INNER', $db->quoteName('#__jem_cats_event_relations', 'cr') . ' ON ' . $db->quoteName('cr.catid') . ' = ' . $db->quoteName('c.id'))
            ->join('INNER', $db->quoteName('#__jem_events', 'e') . ' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('cr.itemid'))
            ->join('INNER', $db->quoteName('#__jem_venues', 'v') . ' ON ' . $db->quoteName('v.id') . ' = ' . $db->quoteName('e.locid'))
            ->join('LEFT', $db->quoteName('#__jem_types', 't') . ' ON ' . $db->quoteName('t.id') . ' = ' . $db->quoteName('e.type_id') . ' AND ' . $db->quoteName('t.entity') . ' = 1 AND ' . $db->quoteName('t.published') . ' = 1')
            ->where($db->quoteName('c.published') . ' = 1')
            ->where($db->quoteName('c.catname') . ' <> ' . $db->quote('root'))
            ->where($db->quoteName('e.published') . ' = 1')
            ->where($db->quoteName('v.published') . ' = 1')
            ->where('COALESCE(' . $db->quoteName('e.enddates') . ', ' . $db->quoteName('e.dates') . ') >= ' . $db->quote($today))
            ->order($db->quoteName('c.lft') . ' ASC');

        self::applyPublishWindow($query, 'e');

        if ($categoryAccess !== '') {
            $query->where($db->quoteName('c.access') . ' IN (' . $categoryAccess . ')');
        }

        if ($eventAccess !== '') {
            $query->where($db->quoteName('e.access') . ' IN (' . $eventAccess . ')');
        }

        if ($venueAccess !== '') {
            $query->where($db->quoteName('v.access') . ' IN (' . $venueAccess . ')');
        }

        $typeAccess = self::accessList($levels);
        if ($typeAccess !== '') {
            $query->where('(' . $db->quoteName('e.type_id') . ' IS NULL OR ' . $db->quoteName('e.type_id') . ' = 0 OR ' . $db->quoteName('t.access') . ' IN (' . $typeAccess . '))');
        }

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Calculate the average center point from map items.
     *
     * @return  array{0: float, 1: float}
     */
    public static function getCenter(array $items)
    {
        $totalLat = 0;
        $totalLng = 0;
        $count    = 0;

        foreach ($items as $item) {
            if (!empty($item->latitude) && !empty($item->longitude)) {
                $totalLat += (float) $item->latitude;
                $totalLng += (float) $item->longitude;
                $count++;
            }
        }

        return $count ? [$totalLat / $count, $totalLng / $count] : [0, 0];
    }

    /**
     * Convert a saved marker path into a usable URL and fall back if the file is missing.
     */
    public static function resolveMarkerUrl($marker, $fallback = 'media/com_jem/images/marker-red.webp')
    {
        $marker = trim((string) $marker);

        if ($marker === '') {
            $marker = $fallback;
        }

        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $marker)) {
            return $marker;
        }

        $path = ltrim($marker, '/');
        $rootPath = trim((string) parse_url(Uri::root(), PHP_URL_PATH), '/');

        if ($rootPath !== '' && strpos($path, $rootPath . '/') === 0) {
            $path = substr($path, strlen($rootPath) + 1);
        }

        $candidates = [$path];

        if (strpos($path, 'media/com_jem/') === 0) {
            $candidates[] = 'media/' . substr($path, strlen('media/com_jem/'));
        } elseif (strpos($path, 'media/') === 0) {
            $candidates[] = 'media/com_jem/' . substr($path, strlen('media/'));
        }

        $fallback = ltrim($fallback, '/');
        $candidates[] = $fallback;

        if (strpos($fallback, 'media/com_jem/') === 0) {
            $candidates[] = 'media/' . substr($fallback, strlen('media/com_jem/'));
        } elseif (strpos($fallback, 'media/') === 0) {
            $candidates[] = 'media/com_jem/' . substr($fallback, strlen('media/'));
        }

        foreach (array_unique($candidates) as $candidate) {
            if (File::exists(JPATH_SITE . '/' . $candidate)) {
                return rtrim(Uri::root(), '/') . '/' . $candidate;
            }
        }

        return rtrim(Uri::root(), '/') . '/' . $fallback;
    }

    private static function getFilterCategoryIds($params, $selectedCategoryId = 0)
    {
        $catids = \JemHelper::getValidIds($params->get('catid'));

        if ($selectedCategoryId > 0) {
            if (empty($catids) || in_array($selectedCategoryId, $catids, true)) {
                return [$selectedCategoryId];
            }

            return [0];
        }

        return $catids;
    }

    private static function applyDateRange($query, $eventAlias, $filterStartDate = null, $filterEndDate = null)
    {
        if (!self::isDate($filterStartDate)) {
            return;
        }

        $db       = Factory::getDbo();
        $dates    = $db->quoteName($eventAlias . '.dates');
        $enddates = $db->quoteName($eventAlias . '.enddates');
        $effectiveEventEndDate = 'COALESCE(' . $enddates . ', ' . $dates . ')';

        if (self::isDate($filterEndDate)) {
            $query->where($dates . ' <= ' . $db->quote($filterEndDate));
            $query->where($effectiveEventEndDate . ' >= ' . $db->quote($filterStartDate));
        } else {
            $query->where($effectiveEventEndDate . ' >= ' . $db->quote($filterStartDate));
        }
    }

    private static function applyPublishWindow($query, $eventAlias)
    {
        $db = Factory::getDbo();
        $now = Factory::getDate()->toSql();

        $query->where($db->quoteName($eventAlias . '.publish_up') . ' <= ' . $db->quote($now));
        $query->where('(' . $db->quoteName($eventAlias . '.publish_down') . ' > ' . $db->quote($now) . ' OR ' . $db->quoteName($eventAlias . '.publish_down') . ' IS NULL)');
    }

    private static function accessList(array $levels, $lockedLevels = '["1"]')
    {
        $levels = array_map('intval', $levels);

        if ($lockedLevels !== '["1"]') {
            $extraLevels = json_decode((string) $lockedLevels, true);
            if (is_array($extraLevels)) {
                $levels = array_merge($levels, array_map('intval', $extraLevels));
            }
        }

        $levels = array_values(array_unique(array_filter($levels)));

        return $levels ? implode(',', $levels) : '';
    }

    private static function isDate($date)
    {
        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }
}

