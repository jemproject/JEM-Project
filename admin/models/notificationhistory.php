<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;

/** Read-only notification journal list. */
class JemModelNotificationhistory extends ListModel
{
    public function __construct($config = array())
    {
        $config['filter_fields'] = $config['filter_fields'] ?? array(
            'id', 'n.id', 'created', 'n.created', 'state', 'n.state',
            'notification_type', 'n.notification_type', 'recipient_email', 'n.recipient_email',
            'event_title', 'registration_reference', 'n.registration_reference',
        );
        parent::__construct($config);
    }

    protected function populateState($ordering = null, $direction = null)
    {
        foreach (array('search', 'state', 'type', 'language', 'begin', 'end') as $name) {
            $this->setState(
                'filter_' . $name,
                $this->getUserStateFromRequest($this->context . '.filter_' . $name, 'filter_' . $name, '', 'string')
            );
        }
        foreach (array('event_id', 'registration_id') as $name) {
            $this->setState(
                'filter_' . $name,
                $this->getUserStateFromRequest($this->context . '.filter_' . $name, 'filter_' . $name, 0, 'int')
            );
        }
        parent::populateState('n.created', 'DESC');
    }

    protected function getStoreId($id = '')
    {
        foreach (array('search', 'state', 'type', 'language', 'begin', 'end', 'event_id', 'registration_id') as $filter) {
            $id .= ':' . $this->getState('filter_' . $filter);
        }
        return parent::getStoreId($id);
    }

    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select(array(
                'n.*', 'e.title AS event_title', 'u.name AS recipient_account_name',
                '(SELECT COUNT(*) FROM ' . $db->quoteName('#__jem_notifications_attempts', 'na') . ' WHERE na.notification_id = n.id) AS attempts_total',
                '(SELECT na2.error_message FROM ' . $db->quoteName('#__jem_notifications_attempts', 'na2') . ' WHERE na2.notification_id = n.id ORDER BY na2.attempt_number DESC LIMIT 1) AS last_error',
            ))
            ->from($db->quoteName('#__jem_notifications', 'n'))
            ->join('LEFT', $db->quoteName('#__jem_events', 'e') . ' ON e.id = n.event_id')
            ->join('LEFT', $db->quoteName('#__users', 'u') . ' ON u.id = n.recipient_user_id');

        $search = trim((string) $this->getState('filter_search'));
        if ($search !== '') {
            if (stripos($search, 'id:') === 0) {
                $query->where('n.id = ' . (int) substr($search, 3));
            } else {
                $like = $db->quote('%' . $db->escape($search, true) . '%');
                $query->where('(' . implode(' OR ', array(
                    'n.notification_uuid LIKE ' . $like,
                    'n.registration_reference LIKE ' . $like,
                    'n.recipient_name LIKE ' . $like,
                    'n.recipient_email LIKE ' . $like,
                    'n.subject LIKE ' . $like,
                    'e.title LIKE ' . $like,
                )) . ')');
            }
        }

        foreach (array('state' => 'state', 'type' => 'notification_type', 'language' => 'resolved_language') as $filter => $column) {
            $value = trim((string) $this->getState('filter_' . $filter));
            if ($value !== '') {
                $query->where('n.' . $db->quoteName($column) . ' = ' . $db->quote($value));
            }
        }
        foreach (array('event_id', 'registration_id') as $column) {
            $value = (int) $this->getState('filter_' . $column);
            if ($value > 0) {
                $query->where('n.' . $db->quoteName($column) . ' = ' . $value);
            }
        }
        $begin = $this->normaliseDate($this->getState('filter_begin'));
        $end = $this->normaliseDate($this->getState('filter_end'));
        if ($begin !== '') {
            $query->where('n.created >= ' . $db->quote($begin . ' 00:00:00'));
        }
        if ($end !== '') {
            $query->where('n.created <= ' . $db->quote($end . ' 23:59:59'));
        }

        $order = (string) $this->state->get('list.ordering', 'n.created');
        $direction = strtoupper((string) $this->state->get('list.direction', 'DESC'));
        if (!in_array($order, $this->filter_fields, true)) {
            $order = 'n.created';
        }
        if (!in_array($direction, array('ASC', 'DESC'), true)) {
            $direction = 'DESC';
        }
        return $query->order($db->escape($order) . ' ' . $direction);
    }

    public function getFilterValues($column)
    {
        if (!in_array($column, array('state', 'notification_type', 'resolved_language'), true)) {
            return array();
        }
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName($column))
            ->from($db->quoteName('#__jem_notifications'))
            ->where($db->quoteName($column) . ' <> ' . $db->quote(''))
            ->order($db->quoteName($column));
        $db->setQuery($query);
        return (array) $db->loadColumn();
    }

    private function normaliseDate($value)
    {
        $value = (string) $value;
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }
}
