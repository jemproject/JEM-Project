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
              ->from($db->quoteName('#__jem_types', 'a'))
              ->join('LEFT', $db->quoteName('#__viewlevels', 'vl') . ' ON ' . $db->quoteName('vl.id') . ' = ' . $db->quoteName('a.access'));

        $search = $this->getState('filter_search');
        if (!empty($search)) {
            $search = $db->quote('%' . $db->escape($search, true) . '%');
            $query->where('a.name LIKE ' . $search);
        }

        $published = $this->getState('filter_state');
        if ($published !== '') {
            $query->where('a.published = ' . (int) $published);
        }

        $entity = $this->getState('filter_entity');
        if ($entity > 0) {
            $query->where('a.entity = ' . (int) $entity);
        }

        if ($access = $this->getState('filter.access')) {
            $query->where('a.access = ' . (int) $access);
        }

        $orderCol  = $this->state->get('list.ordering', 'a.entity, a.ordering');
        $orderDir  = $this->state->get('list.direction', 'asc');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }

    public function getItems()
    {
        $items = parent::getItems();
        $counts = $this->getTypeEventStateCounts($items);

        foreach ($items as $item) {
            if ((int) $item->entity !== 1) {
                $item->event_state_counts = null;
                continue;
            }

            $item->event_state_counts = $counts[(int) $item->id] ?? (object) array(
                'published' => 0,
                'unpublished' => 0,
                'archived' => 0,
                'trashed' => 0,
            );
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
}
