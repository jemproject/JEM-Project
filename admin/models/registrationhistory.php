<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;

/**
 * Read-only registration audit history model.
 */
class JemModelRegistrationhistory extends ListModel
{
    public function __construct($config = array())
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'h.id',
                'occurred', 'h.occurred',
                'registration_reference', 'h.registration_reference',
                'operation_reference', 'h.operation_reference',
                'revision', 'h.revision',
                'event_title', 'h.event_title',
                'action', 'h.action',
                'new_status', 'h.new_status',
                'new_places', 'h.new_places',
                'holder_name',
                'actor_name',
                'source', 'h.source',
            );
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = null, $direction = null)
    {
        $filters = array(
            'search' => 'string',
            'action' => 'cmd',
            'source' => 'cmd',
            'status' => 'string',
            'event_id' => 'int',
            'actor_id' => 'int',
            'orphaned' => 'string',
            'begin' => 'string',
            'end' => 'string',
            'registration_id' => 'int',
        );

        foreach ($filters as $name => $type) {
            $value = $this->getUserStateFromRequest(
                $this->context . '.filter_' . $name,
                'filter_' . $name,
                $type === 'int' ? 0 : '',
                $type
            );
            $this->setState('filter_' . $name, $value);
        }

        parent::populateState('h.occurred', 'desc');
    }

    protected function getStoreId($id = '')
    {
        foreach (array('search', 'action', 'source', 'status', 'event_id', 'actor_id', 'orphaned', 'begin', 'end', 'registration_id') as $filter) {
            $id .= ':' . $this->getState('filter_' . $filter);
        }

        return parent::getStoreId($id);
    }

    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select(array(
                'h.*',
                'COALESCE(NULLIF(h.event_title, ' . $db->quote('') . '), e.title) AS event_display_title',
                'COALESCE(h.new_user_id, h.old_user_id) AS holder_user_id',
                'holder.name AS holder_name',
                'holder.username AS holder_username',
                'holder.email AS holder_email',
                'actor.name AS actor_name',
                'actor.username AS actor_username',
                'r.id AS current_registration_id',
                'e.id AS current_event_id',
            ))
            ->from($db->quoteName('#__jem_registration_history', 'h'))
            ->join('LEFT', $db->quoteName('#__jem_register', 'r')
                . ' ON ' . $db->quoteName('r.id') . ' = ' . $db->quoteName('h.registration_id')
                . ' AND ' . $db->quoteName('r.reference') . ' = ' . $db->quoteName('h.registration_reference'))
            ->join('LEFT', $db->quoteName('#__jem_events', 'e') . ' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('h.event_id'))
            ->join('LEFT', $db->quoteName('#__users', 'holder') . ' ON ' . $db->quoteName('holder.id') . ' = COALESCE(' . $db->quoteName('h.new_user_id') . ', ' . $db->quoteName('h.old_user_id') . ')')
            ->join('LEFT', $db->quoteName('#__users', 'actor') . ' ON ' . $db->quoteName('actor.id') . ' = ' . $db->quoteName('h.actor_user_id'));

        $search = trim((string) $this->getState('filter_search'));
        if ($search !== '') {
            if (stripos($search, 'id:') === 0) {
                $query->where($db->quoteName('h.id') . ' = ' . (int) substr($search, 3));
            } elseif (stripos($search, 'user:') === 0) {
                $userId = (int) substr($search, 5);
                $query->where('(' . $db->quoteName('h.old_user_id') . ' = ' . $userId . ' OR ' . $db->quoteName('h.new_user_id') . ' = ' . $userId . ')');
            } else {
                $like = $db->quote('%' . $db->escape($search, true) . '%');
                $query->where('('
                    . $db->quoteName('h.registration_reference') . ' LIKE ' . $like
                    . ' OR ' . $db->quoteName('h.operation_reference') . ' LIKE ' . $like
                    . ' OR ' . $db->quoteName('h.event_title') . ' LIKE ' . $like
                    . ' OR ' . $db->quoteName('e.title') . ' LIKE ' . $like
                    . ' OR ' . $db->quoteName('holder.name') . ' LIKE ' . $like
                    . ' OR ' . $db->quoteName('holder.username') . ' LIKE ' . $like
                    . ' OR ' . $db->quoteName('holder.email') . ' LIKE ' . $like
                    . ' OR ' . $db->quoteName('actor.name') . ' LIKE ' . $like
                    . ' OR ' . $db->quoteName('h.action') . ' LIKE ' . $like
                    . ' OR ' . $db->quoteName('h.source') . ' LIKE ' . $like
                    . ')');
            }
        }

        $action = (string) $this->getState('filter_action');
        if ($action !== '') {
            $query->where($db->quoteName('h.action') . ' = ' . $db->quote($action));
        }

        $source = (string) $this->getState('filter_source');
        if ($source !== '') {
            $query->where($db->quoteName('h.source') . ' = ' . $db->quote($source));
        }

        $status = (string) $this->getState('filter_status');
        if (in_array($status, array('-1', '0', '1', '2'), true)) {
            $query->where($db->quoteName('h.new_status') . ' = ' . (int) $status);
        }

        $eventId = (int) $this->getState('filter_event_id');
        if ($eventId > 0) {
            $query->where($db->quoteName('h.event_id') . ' = ' . $eventId);
        }

        $actorId = (int) $this->getState('filter_actor_id');
        if ($actorId > 0) {
            $query->where($db->quoteName('h.actor_user_id') . ' = ' . $actorId);
        }

        $orphaned = (string) $this->getState('filter_orphaned');
        if ($orphaned === '1') {
            $query->where($db->quoteName('r.id') . ' IS NULL');
        } elseif ($orphaned === '0') {
            $query->where($db->quoteName('r.id') . ' IS NOT NULL');
        }

        $begin = $this->normaliseDate((string) $this->getState('filter_begin'));
        if ($begin !== '') {
            $query->where($db->quoteName('h.occurred') . ' >= ' . $db->quote($begin . ' 00:00:00'));
        }

        $end = $this->normaliseDate((string) $this->getState('filter_end'));
        if ($end !== '') {
            $query->where($db->quoteName('h.occurred') . ' <= ' . $db->quote($end . ' 23:59:59'));
        }

        $registrationId = (int) $this->getState('filter_registration_id');
        if ($registrationId > 0) {
            $query->where($db->quoteName('h.registration_id') . ' = ' . $registrationId);
        }

        $orderCol = $this->state->get('list.ordering', 'h.occurred');
        $orderDir = strtoupper($this->state->get('list.direction', 'DESC'));
        if (!in_array($orderCol, $this->filter_fields, true)) {
            $orderCol = 'h.occurred';
        }
        if (!in_array($orderDir, array('ASC', 'DESC'), true)) {
            $orderDir = 'DESC';
        }

        return $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));
    }

    public function getFilterValues($column)
    {
        if (!in_array($column, array('action', 'source'), true)) {
            return array();
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName($column))
            ->from($db->quoteName('#__jem_registration_history'))
            ->where($db->quoteName($column) . ' <> ' . $db->quote(''))
            ->order($db->quoteName($column) . ' ASC');
        $db->setQuery($query);

        return (array) $db->loadColumn();
    }

    private function normaliseDate($value)
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }
}
