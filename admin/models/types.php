<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;

class JemModelTypes extends ListModel
{
    public function __construct($config = array())
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'name', 'a.name',
                'alias', 'a.alias',
                'entity', 'a.entity',
                'access', 'a.access', 'access_level',
                'published', 'a.published',
                'ordering', 'a.ordering',
                'created', 'a.created',
                'created_by', 'a.created_by',
                'author_name', 'u.name',
            );
        }
        parent::__construct($config);
    }

    protected function populateState($ordering = null, $direction = null)
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter_search', 'filter_search');
        $this->setState('filter_search', $search);

        $published = $this->getUserStateFromRequest($this->context . '.filter_state', 'filter_state', '', 'string');
        $this->setState('filter_state', $published);

        $entity = $this->getUserStateFromRequest($this->context . '.filter_entity', 'filter_entity', 0, 'int');
        $this->setState('filter_entity', $entity);

        $access = $this->getUserStateFromRequest($this->context . '.filter.access', 'filter_access', 0, 'int');
        $this->setState('filter.access', $access);

        parent::populateState('a.entity, a.ordering', 'asc');

        if ((int) $entity > 0) {
            $this->setState('list.ordering', 'a.ordering');
            $this->setState('list.direction', 'asc');
        }
    }

    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter_search');
        $id .= ':' . $this->getState('filter_state');
        $id .= ':' . $this->getState('filter_entity');
        $id .= ':' . $this->getState('filter.access');
        return parent::getStoreId($id);
    }

    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('a.*')
              ->select($db->quoteName('vl.title', 'access_level'))
              ->select($db->quoteName('u.name', 'author_name'))
              ->from($db->quoteName('#__jem_types', 'a'))
              ->join('LEFT', $db->quoteName('#__viewlevels', 'vl') . ' ON ' . $db->quoteName('vl.id') . ' = ' . $db->quoteName('a.access'))
              ->join('LEFT', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('a.created_by'));

        $search = $this->getState('filter_search');
        if (!empty($search)) {
            $search = $db->quote('%' . $db->escape($search, true) . '%');
            $query->where('a.name LIKE ' . $search);
        }

        $published = $this->getState('filter_state');
        if (is_numeric($published)) {
            $query->where('a.published = ' . (int) $published);
        } elseif ($published === '') {
            $query->where('a.published IN (0, 1)');
        }

        $entity = $this->getState('filter_entity');
        if ($entity > 0) {
            $query->where('a.entity = ' . (int) $entity);
        }

        if ($access = $this->getState('filter.access')) {
            $query->where('a.access = ' . (int) $access);
        }

        $orderCol  = $this->state->get('list.ordering', 'a.entity, a.ordering');
        $orderDir  = strtoupper($this->state->get('list.direction', 'asc'));

        if (!in_array($orderCol, $this->filter_fields, true)) {
            $orderCol = 'a.entity, a.ordering';
        }

        if (!in_array($orderDir, array('ASC', 'DESC'), true)) {
            $orderDir = 'ASC';
        }

        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }

    public function getItems()
    {
        $items = parent::getItems();
        $eventStateCounts = $this->getTypeEventStateCounts($items);
        $categoryStateCounts = $this->getTypeItemStateCounts($items, 2, '#__jem_categories');
        $venueStateCounts = $this->getTypeItemStateCounts($items, 3, '#__jem_venues');
        $dayStateCounts = $this->getTypeDayStateCounts($items);
        $usageCounts = $this->getTypeUsageCounts($items);

        foreach ($items as $item) {
            $item->usage_count = $usageCounts[(int) $item->id] ?? 0;
            $item->event_state_counts = null;
            $item->item_state_counts = null;
            $item->day_state_counts = null;
            $item->attribs_data = json_decode((string) ($item->attribs ?? ''), true);

            if (!is_array($item->attribs_data)) {
                $item->attribs_data = array();
            }

            if ((int) $item->entity === 1) {
                $item->event_state_counts = $eventStateCounts[(int) $item->id] ?? (object) array(
                    'published' => 0,
                    'unpublished' => 0,
                    'archived' => 0,
                    'trashed' => 0,
                );
            } elseif ((int) $item->entity === 2) {
                $item->item_state_counts = $categoryStateCounts[(int) $item->id] ?? (object) array(
                    'published' => 0,
                    'unpublished' => 0,
                    'archived' => 0,
                    'trashed' => 0,
                );
            } elseif ((int) $item->entity === 3) {
                $item->item_state_counts = $venueStateCounts[(int) $item->id] ?? (object) array(
                    'published' => 0,
                    'unpublished' => 0,
                    'archived' => 0,
                    'trashed' => 0,
                );
            } elseif ((int) $item->entity === 4) {
                $item->day_state_counts = $dayStateCounts[(int) $item->id] ?? (object) array(
                    'published' => 0,
                    'unpublished' => 0,
                    'archived' => 0,
                    'trashed' => 0,
                );
            }
        }

        return $items;
    }

    /**
     * Count events by state for the listed event types.
     *
     * @param  array  $items  Type rows.
     *
     * @return array
     */
    private function getTypeEventStateCounts($items)
    {
        if (empty($items)) {
            return array();
        }

        $ids = array();

        foreach ($items as $item) {
            if ((int) $item->entity === 1) {
                $ids[] = (int) $item->id;
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if (empty($ids)) {
            return array();
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array(
                $db->quoteName('type_id'),
                'SUM(CASE WHEN ' . $db->quoteName('published') . ' = 1 THEN 1 ELSE 0 END) AS ' . $db->quoteName('published'),
                'SUM(CASE WHEN ' . $db->quoteName('published') . ' = 0 THEN 1 ELSE 0 END) AS ' . $db->quoteName('unpublished'),
                'SUM(CASE WHEN ' . $db->quoteName('published') . ' = 2 THEN 1 ELSE 0 END) AS ' . $db->quoteName('archived'),
                'SUM(CASE WHEN ' . $db->quoteName('published') . ' = -2 THEN 1 ELSE 0 END) AS ' . $db->quoteName('trashed'),
            ))
            ->from($db->quoteName('#__jem_events'))
            ->where($db->quoteName('type_id') . ' IN (' . implode(',', $ids) . ')')
            ->group($db->quoteName('type_id'));

        $db->setQuery($query);
        $rows = $db->loadObjectList('type_id');
        $counts = array();

        foreach ($rows as $typeId => $row) {
            $counts[(int) $typeId] = (object) array(
                'published' => (int) $row->published,
                'unpublished' => (int) $row->unpublished,
                'archived' => (int) $row->archived,
                'trashed' => (int) $row->trashed,
            );
        }

        return $counts;
    }

    /**
     * Count category or venue rows by state for the listed types.
     *
     * @param  array   $items   Type rows.
     * @param  int     $entity  Type entity id.
     * @param  string  $table   Table name.
     *
     * @return array
     */
    private function getTypeItemStateCounts($items, $entity, $table)
    {
        if (empty($items)) {
            return array();
        }

        $ids = array();

        foreach ($items as $item) {
            if ((int) $item->entity === (int) $entity) {
                $ids[] = (int) $item->id;
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if (empty($ids)) {
            return array();
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array(
                $db->quoteName('type_id'),
                'SUM(CASE WHEN ' . $db->quoteName('published') . ' = 1 THEN 1 ELSE 0 END) AS ' . $db->quoteName('published'),
                'SUM(CASE WHEN ' . $db->quoteName('published') . ' = 0 THEN 1 ELSE 0 END) AS ' . $db->quoteName('unpublished'),
                'SUM(CASE WHEN ' . $db->quoteName('published') . ' = 2 THEN 1 ELSE 0 END) AS ' . $db->quoteName('archived'),
                'SUM(CASE WHEN ' . $db->quoteName('published') . ' = -2 THEN 1 ELSE 0 END) AS ' . $db->quoteName('trashed'),
            ))
            ->from($db->quoteName($table))
            ->where($db->quoteName('type_id') . ' IN (' . implode(',', $ids) . ')')
            ->group($db->quoteName('type_id'));

        $db->setQuery($query);
        $rows = $db->loadObjectList('type_id');
        $counts = array();

        foreach ($rows as $typeId => $row) {
            $counts[(int) $typeId] = (object) array(
                'published' => (int) $row->published,
                'unpublished' => (int) $row->unpublished,
                'archived' => (int) $row->archived,
                'trashed' => (int) $row->trashed,
            );
        }

        return $counts;
    }

    /**
     * Count Special Day rules by state for the listed day types.
     *
     * @param  array  $items  Type rows.
     *
     * @return array
     */
    private function getTypeDayStateCounts($items)
    {
        if (empty($items)) {
            return array();
        }

        $ids = array();

        foreach ($items as $item) {
            if ((int) $item->entity === 4) {
                $ids[] = (int) $item->id;
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if (empty($ids)) {
            return array();
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array(
                $db->quoteName('day_type_id'),
                'SUM(CASE WHEN ' . $db->quoteName('published') . ' = 1 THEN 1 ELSE 0 END) AS ' . $db->quoteName('published'),
                'SUM(CASE WHEN ' . $db->quoteName('published') . ' = 0 THEN 1 ELSE 0 END) AS ' . $db->quoteName('unpublished'),
                'SUM(CASE WHEN ' . $db->quoteName('published') . ' = 2 THEN 1 ELSE 0 END) AS ' . $db->quoteName('archived'),
                'SUM(CASE WHEN ' . $db->quoteName('published') . ' = -2 THEN 1 ELSE 0 END) AS ' . $db->quoteName('trashed'),
            ))
            ->from($db->quoteName('#__jem_special_days'))
            ->where($db->quoteName('day_type_id') . ' IN (' . implode(',', $ids) . ')')
            ->group($db->quoteName('day_type_id'));

        $db->setQuery($query);
        $rows = $db->loadObjectList('day_type_id');
        $counts = array();

        foreach ($rows as $typeId => $row) {
            $counts[(int) $typeId] = (object) array(
                'published' => (int) $row->published,
                'unpublished' => (int) $row->unpublished,
                'archived' => (int) $row->archived,
                'trashed' => (int) $row->trashed,
            );
        }

        return $counts;
    }

    private function getTypeUsageCounts($items)
    {
        if (empty($items)) {
            return array();
        }

        $groups = array(
            1 => array(),
            2 => array(),
            3 => array(),
            4 => array(),
        );

        foreach ($items as $item) {
            $entity = (int) ($item->entity ?? 0);
            $id = (int) ($item->id ?? 0);

            if ($id > 0 && isset($groups[$entity])) {
                $groups[$entity][] = $id;
            }
        }

        $counts = array();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $queries = array(
            1 => array('#__jem_events', 'type_id'),
            2 => array('#__jem_categories', 'type_id'),
            3 => array('#__jem_venues', 'type_id'),
            4 => array('#__jem_special_days', 'day_type_id'),
        );

        foreach ($queries as $entity => $queryInfo) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $groups[$entity]))));

            if (!$ids) {
                continue;
            }

            [$table, $column] = $queryInfo;
            $query = $db->getQuery(true)
                ->select(array($db->quoteName($column), 'COUNT(*) AS ' . $db->quoteName('total')))
                ->from($db->quoteName($table))
                ->where($db->quoteName($column) . ' IN (' . implode(',', $ids) . ')')
                ->group($db->quoteName($column));

            try {
                $db->setQuery($query);
                $rows = $db->loadObjectList($column) ?: array();
            } catch (RuntimeException $e) {
                continue;
            }

            foreach ($rows as $typeId => $row) {
                $counts[(int) $typeId] = (int) $row->total;
            }
        }

        return $counts;
    }
}
