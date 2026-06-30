<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;

class JemModelImportprofiles extends ListModel
{
    public function __construct($config = array())
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'title', 'a.title',
                'context', 'a.context',
                'source_format', 'a.source_format',
                'access', 'a.access', 'access_level',
                'published', 'a.published',
                'ordering', 'a.ordering',
                'created', 'a.created',
                'modified', 'a.modified',
            );
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = null, $direction = null)
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter_search', 'filter_search');
        $this->setState('filter.search', $search);

        $context = $this->getUserStateFromRequest($this->context . '.filter_context', 'filter_context', '', 'string');
        $this->setState('filter.context', $context);

        $format = $this->getUserStateFromRequest($this->context . '.filter_format', 'filter_format', '', 'string');
        $this->setState('filter.format', $format);

        $published = $this->getUserStateFromRequest($this->context . '.filter_state', 'filter_state', '', 'string');
        $this->setState('filter.state', $published);

        $access = $this->getUserStateFromRequest($this->context . '.filter.access', 'filter_access', 0, 'int');
        $this->setState('filter.access', $access);

        parent::populateState('a.context, a.ordering', 'asc');
    }

    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.context');
        $id .= ':' . $this->getState('filter.format');
        $id .= ':' . $this->getState('filter.state');
        $id .= ':' . $this->getState('filter.access');

        return parent::getStoreId($id);
    }

    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('a.*')
            ->select($db->quoteName('vl.title', 'access_level'))
            ->from($db->quoteName('#__jem_import_profiles', 'a'))
            ->join('LEFT', $db->quoteName('#__viewlevels', 'vl') . ' ON ' . $db->quoteName('vl.id') . ' = ' . $db->quoteName('a.access'));

        $search = trim((string) $this->getState('filter.search'));
        if ($search !== '') {
            $search = $db->quote('%' . $db->escape($search, true) . '%');
            $query->where('(a.title LIKE ' . $search . ' OR a.mapping LIKE ' . $search . ')');
        }

        $context = trim((string) $this->getState('filter.context'));
        if ($context !== '') {
            $query->where('a.context = ' . $db->quote($context));
        }

        $format = trim((string) $this->getState('filter.format'));
        if ($format !== '') {
            $query->where('a.source_format = ' . $db->quote($format));
        }

        $published = $this->getState('filter.state');
        if (is_numeric($published)) {
            $query->where('a.published = ' . (int) $published);
        } elseif ($published === '') {
            $query->where('a.published IN (0, 1)');
        }

        if ($access = (int) $this->getState('filter.access')) {
            $query->where('a.access = ' . $access);
        }

        $orderCol = $this->state->get('list.ordering', 'a.context, a.ordering');
        $orderDir = strtoupper($this->state->get('list.direction', 'asc'));

        if (!in_array($orderCol, $this->filter_fields, true)) {
            $orderCol = 'a.context, a.ordering';
        }

        if (!in_array($orderDir, array('ASC', 'DESC'), true)) {
            $orderDir = 'ASC';
        }

        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }

    public function getContexts()
    {
        return $this->getDistinctColumnOptions('context');
    }

    public function getFormats()
    {
        return $this->getDistinctColumnOptions('source_format');
    }

    private function getDistinctColumnOptions($column)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName($column))
            ->from($db->quoteName('#__jem_import_profiles'))
            ->where($db->quoteName($column) . ' <> ' . $db->quote(''))
            ->order($db->quoteName($column) . ' ASC');

        $db->setQuery($query);

        try {
            return $db->loadColumn();
        } catch (RuntimeException $e) {
            return array();
        }
    }
}
