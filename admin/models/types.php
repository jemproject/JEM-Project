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

        parent::populateState('a.entity, a.ordering', 'asc');
    }

    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter_search');
        $id .= ':' . $this->getState('filter_state');
        $id .= ':' . $this->getState('filter_entity');
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

        $orderCol  = $this->state->get('list.ordering', 'a.entity, a.ordering');
        $orderDir  = $this->state->get('list.direction', 'asc');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }
}
