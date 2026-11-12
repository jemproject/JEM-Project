<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

require_once __DIR__ . '/frontendaccess.class.php';

/** Shared parent choices and preservation rules for the frontend event editor. */
final class JemEventParentPolicy
{
    /**
     * Load allowed parents and the authoritative relationship of an editable child.
     *
     * @param object $user Current JEM user.
     * @param int $childId Saved child, or zero for a new event or copy.
     * @return array{parentId:int, locked:bool, options:array} Safe editor context.
     * @throws RuntimeException When the saved child cannot be edited.
     */
    public static function selection($user, int $childId = 0): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $parentId = 0;
        if ($childId > 0) {
            $child = $db->setQuery('SELECT id, created_by, access, parent_event_id FROM #__jem_events WHERE id=' . $childId)->loadObject();
            if (!$child || !JemFrontendAccess::canEdit($user, 'event', $child)) {
                throw new RuntimeException('JERROR_ALERTNOAUTHOR');
            }
            $parentId = (int) $child->parent_event_id;
        }

        $levels = array_values(array_unique(array_filter(array_map('intval', $user->getAuthorisedViewLevels()))));
        $levelsList = $levels ? implode(',', $levels) : '0';
        $visibleVenueIds = JemHelper::getVisibleVenueHierarchyIds($levels);
        $visibleVenueList = $visibleVenueIds ? implode(',', array_map('intval', $visibleVenueIds)) : '0';
        $publication = '(' . JemHelper::getEventPublicationWhere('e') . ')';
        if ((int) $user->id > 0) {
            $publication .= ' OR (e.created_by = ' . (int) $user->id . ' AND e.published IN (0, 1))';
        }
        if ($parentId > 0) {
            $publication .= ' OR (e.id = ' . $parentId . ' AND e.published IN (0, 1))';
        }
        $query = $db->getQuery(true)
            ->select(array($db->quoteName('e.id', 'value'), $db->quoteName('e.title', 'text'), $db->quoteName('e.dates')))
            ->from($db->quoteName('#__jem_events', 'e'))
            ->join('LEFT', $db->quoteName('#__jem_types', 't') . ' ON t.id = e.type_id AND t.entity = 1')
            ->where('(' . $publication . ')')
            ->where($db->quoteName('e.dates') . ' IS NOT NULL')
            ->where($db->quoteName('e.access') . ' IN (' . $levelsList . ')')
            ->where('(' . $db->quoteName('e.parent_event_id') . ' IS NULL OR ' . $db->quoteName('e.parent_event_id') . ' = 0)')
            ->where('(e.locid IS NULL OR e.locid = 0 OR e.locid IN (' . $visibleVenueList . '))')
            ->where('(e.type_id IS NULL OR e.type_id = 0 OR (t.id IS NOT NULL AND t.published = 1 AND t.access IN (' . $levelsList . ')))')
            ->where('EXISTS (SELECT 1 FROM #__jem_cats_event_relations AS parent_rel'
                . ' INNER JOIN #__jem_categories AS parent_cat ON parent_cat.id = parent_rel.catid'
                . ' WHERE parent_rel.itemid = e.id AND parent_cat.published = 1'
                . ' AND parent_cat.access IN (' . $levelsList . '))')
            ->where($db->quoteName('e.id') . ' <> ' . $childId)
            ->order(array($db->quoteName('e.dates') . ' DESC', $db->quoteName('e.title') . ' ASC'));
        $options = (array) $db->setQuery($query)->loadObjectList('value');

        return array('parentId' => $parentId, 'locked' => $parentId > 0 && !isset($options[$parentId]), 'options' => $options);
    }

    /**
     * Preserve an unavailable saved parent; validate explicit changes before saving.
     *
     * @param object $user Current JEM user.
     * @param int $childId Existing child, or zero for a new event or copy.
     * @param mixed $requested Submitted parent id; null means the field was omitted.
     * @return int Authoritative parent id, including zero for an explicit unlink.
     * @throws RuntimeException When the requested parent is not selectable.
     */
    public static function resolveSubmission($user, int $childId, $requested): int
    {
        $selection = self::selection($user, $childId);
        if ($selection['locked'] || $requested === null) {
            return $selection['parentId'];
        }
        if ((!is_int($requested) && (!is_string($requested) || !ctype_digit($requested)))
            || (int) $requested < 0
            || ((int) $requested > 0 && !isset($selection['options'][(int) $requested]))) {
            throw new RuntimeException('COM_JEM_EVENT_PARENT_NOT_ALLOWED');
        }

        return (int) $requested;
    }
}
