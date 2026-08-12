<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;

class JemModelReminders extends ListModel
{
    public function __construct($config = array())
    {
        $config['filter_fields'] = $config['filter_fields'] ?? array(
            'id', 'a.id', 'title', 'a.title', 'minutes', 'a.minutes',
            'published', 'a.published', 'default_new_event', 'a.default_new_event', 'ordering', 'a.ordering',
        );
        parent::__construct($config);
    }

    protected function populateState($ordering = null, $direction = null)
    {
        $this->setState('filter_search', $this->getUserStateFromRequest($this->context . '.filter_search', 'filter_search'));
        $this->setState('filter_state', $this->getUserStateFromRequest($this->context . '.filter_state', 'filter_state', '', 'string'));
        parent::populateState('a.ordering', 'asc');
    }

    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('a.*')
            ->select('(SELECT COUNT(*) FROM ' . $db->quoteName('#__jem_reminders', 'er')
                . ' WHERE er.event_id > 0 AND er.source_id = a.id) AS event_count')
            ->from($db->quoteName('#__jem_reminders', 'a'))
            ->where('a.event_id = 0');
        $search = trim((string) $this->getState('filter_search'));
        if ($search !== '') {
            $query->where('(a.title LIKE ' . $db->quote('%' . $db->escape($search, true) . '%', false)
                . ' OR a.code LIKE ' . $db->quote('%' . $db->escape($search, true) . '%', false) . ')');
        }
        $state = $this->getState('filter_state');
        if (is_numeric($state)) {
            $query->where('a.published = ' . (int) $state);
        }
        $order = $this->state->get('list.ordering', 'a.ordering');
        $direction = strtoupper((string) $this->state->get('list.direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        if (!in_array($order, $this->filter_fields, true)) {
            $order = 'a.ordering';
        }
        $query->order($db->escape($order) . ' ' . $direction);

        return $query;
    }

    public function getItems()
    {
        $items = parent::getItems();
        foreach ((array) $items as $item) {
            $minutes = max(1, (int) $item->minutes);
            if ((string) $item->code === 'default_7_days') {
                $item->amount = 7;
                $item->unit = 'day';
            } elseif ((string) $item->code === 'default_24_hours') {
                $item->amount = 24;
                $item->unit = 'hour';
            } elseif ((string) $item->code === 'default_2_hours') {
                $item->amount = 2;
                $item->unit = 'hour';
            } else {
                $item->amount = $minutes;
                $item->unit = 'minute';
                foreach (array('week' => 10080, 'day' => 1440, 'hour' => 60) as $unit => $factor) {
                    if ($minutes % $factor === 0) {
                        $item->amount = (int) ($minutes / $factor);
                        $item->unit = $unit;
                        break;
                    }
                }
            }
        }

        return $items;
    }
}
