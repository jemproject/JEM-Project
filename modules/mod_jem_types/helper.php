<?php
/**
 * @package    JEM
 * @subpackage mod_jem_types
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Date\Date;

class ModJemTypesHelper
{
    /**
     * Summary mode: returns all published event types (entity=1) with event count.
     */
    public static function getTypeSummary($params)
    {
        $db     = Factory::getContainer()->get('DatabaseDriver');
        $app    = Factory::getApplication();
        $user   = JemFactory::getUser();
        $levels = $user->getAuthorisedViewLevels();
        $levels = array_map('intval', $levels);
        $levelsList = implode(',', $levels);
        $date   = new Date('now', $app->get('offset'));
        $today  = $date->format('Y-m-d');
        $now    = $date->toSql();
        $language = $app->getLanguage()->getTag();

        $query = $db->getQuery(true)
            ->select(array(
                't.id', 't.name', 't.alias', 't.icon', 't.color',
                'COUNT(DISTINCT CASE WHEN c.id IS NOT NULL THEN a.id END) AS event_count',
            ))
            ->from($db->quoteName('#__jem_types', 't'))
            ->join('LEFT',
                $db->quoteName('#__jem_events', 'a') . ' ON ' .
                $db->quoteName('a.type_id') . ' = ' . $db->quoteName('t.id') .
                ' AND ' . $db->quoteName('a.published') . ' = 1' .
                ' AND ' . $db->quoteName('a.access') . ' IN (' . $levelsList . ')' .
                ' AND ' . $db->quoteName('a.language') . ' IN (' . $db->quote('*') . ', ' . $db->quote($language) . ')' .
                ' AND ' . $db->quoteName('a.publish_up') . ' <= ' . $db->quote($now) .
                ' AND (' . $db->quoteName('a.publish_down') . ' > ' . $db->quote($now) . ' OR ' . $db->quoteName('a.publish_down') . ' IS NULL)' .
                ' AND (COALESCE(' . $db->quoteName('a.enddates') . ', ' . $db->quoteName('a.dates') . ') >= ' . $db->quote($today) . ')'
            )
            ->join('LEFT', $db->quoteName('#__jem_cats_event_relations', 'rel') . ' ON ' . $db->quoteName('rel.itemid') . ' = ' . $db->quoteName('a.id'))
            ->join('LEFT',
                $db->quoteName('#__jem_categories', 'c') . ' ON ' .
                $db->quoteName('c.id') . ' = ' . $db->quoteName('rel.catid') .
                ' AND ' . $db->quoteName('c.published') . ' = 1' .
                ' AND ' . $db->quoteName('c.access') . ' IN (' . $levelsList . ')' .
                ' AND ' . $db->quoteName('c.language') . ' IN (' . $db->quote('*') . ', ' . $db->quote($language) . ')'
            )
            ->where($db->quoteName('t.published') . ' = 1')
            ->where($db->quoteName('t.entity') . ' = 1')
            ->where($db->quoteName('t.access') . ' IN (' . $levelsList . ')')
            ->where($db->quoteName('t.language') . ' IN (' . $db->quote('*') . ', ' . $db->quote($language) . ')')
            ->group('t.id, t.name, t.alias, t.icon, t.color')
            ->order($db->quoteName('t.ordering') . ' ASC, ' . $db->quoteName('t.name') . ' ASC');

        if ($params->get('hide_empty', 0)) {
            $query->having('COUNT(DISTINCT a.id) > 0');
        }

        $db->setQuery($query);
        return $db->loadObjectList();
    }

    /**
     * Top-N mode: for each published type, returns the next N upcoming events.
     */
    public static function getTopNByType($params)
    {
        $db     = Factory::getContainer()->get('DatabaseDriver');
        $app    = Factory::getApplication();
        $user   = JemFactory::getUser();
        $levels = $user->getAuthorisedViewLevels();
        $levels = array_map('intval', $levels);
        $levelsList = implode(',', $levels);
        $date   = new Date('now', $app->get('offset'));
        $today  = $date->format('Y-m-d');
        $now    = $date->toSql();
        $language = $app->getLanguage()->getTag();
        $n      = max(1, (int) $params->get('top_n', 3));

        // Load all active types
        $typeQuery = $db->getQuery(true)
            ->select(array('id', 'name', 'alias', 'icon', 'color'))
            ->from($db->quoteName('#__jem_types'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('entity') . ' = 1')
            ->where($db->quoteName('access') . ' IN (' . $levelsList . ')')
            ->where($db->quoteName('language') . ' IN (' . $db->quote('*') . ', ' . $db->quote($language) . ')')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('name') . ' ASC');

        $db->setQuery($typeQuery);
        $types = $db->loadObjectList();

        if (empty($types)) {
            return array();
        }

        $result = array();

        foreach ($types as $type) {
            // CASE slug
            $caseSlug = 'CASE WHEN ' . $db->quoteName('a.alias') . ' != \'\' THEN CONCAT(' .
                $db->quoteName('a.id') . ', \':\', ' . $db->quoteName('a.alias') . ') ELSE ' .
                $db->quoteName('a.id') . ' END';

            $eventQuery = $db->getQuery(true)
                ->select(array(
                    'a.id', 'a.title', 'a.alias', 'a.dates', 'a.times', 'a.enddates', 'a.endtimes',
                    '(' . $caseSlug . ') AS slug',
                ))
                ->from($db->quoteName('#__jem_events', 'a'))
                ->join('INNER', $db->quoteName('#__jem_cats_event_relations', 'rel') . ' ON ' . $db->quoteName('rel.itemid') . ' = ' . $db->quoteName('a.id'))
                ->join('INNER', $db->quoteName('#__jem_categories', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('rel.catid'))
                ->where($db->quoteName('a.type_id') . ' = ' . (int) $type->id)
                ->where($db->quoteName('a.published') . ' = 1')
                ->where($db->quoteName('a.access') . ' IN (' . $levelsList . ')')
                ->where($db->quoteName('a.language') . ' IN (' . $db->quote('*') . ', ' . $db->quote($language) . ')')
                ->where($db->quoteName('a.publish_up') . ' <= ' . $db->quote($now))
                ->where('(' . $db->quoteName('a.publish_down') . ' > ' . $db->quote($now) . ' OR ' . $db->quoteName('a.publish_down') . ' IS NULL)')
                ->where('COALESCE(' . $db->quoteName('a.enddates') . ', ' . $db->quoteName('a.dates') . ') >= ' . $db->quote($today))
                ->where($db->quoteName('c.published') . ' = 1')
                ->where($db->quoteName('c.access') . ' IN (' . $levelsList . ')')
                ->where($db->quoteName('c.language') . ' IN (' . $db->quote('*') . ', ' . $db->quote($language) . ')')
                ->group('a.id, a.title, a.alias, a.dates, a.times, a.enddates, a.endtimes')
                ->order($db->quoteName('a.dates') . ' ASC')
                ->setLimit($n);

            $db->setQuery($eventQuery);
            $events = $db->loadObjectList();

            if (!empty($events) || !$params->get('hide_empty', 0)) {
                $type->events = $events;
                $result[]     = $type;
            }
        }

        return $result;
    }
}
