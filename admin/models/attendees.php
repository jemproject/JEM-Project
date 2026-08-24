<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

use Joomla\Utilities\ArrayHelper;

require_once JPATH_SITE . '/components/com_jem/classes/csv.class.php';

/**
 * Model: Attendees
 */
class JemModelAttendees extends ListModel
{
    protected $eventid = 0;

    /**
     * Constructor
     */
    public function __construct($config = array())
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                    'u.name', 'u.username',
                    'r.uid', 'r.waiting',
                    'r.uregdate','r.id'
            );
        }

        parent::__construct($config);

        $app = Factory::getApplication();
        $eventid = $app->input->getInt('eventid', 0);
        $this->setId($eventid);
    }

    public function setId($eventid)
    {
        $this->eventid = $eventid;
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app = Factory::getApplication();

        $limit      = $app->getUserStateFromRequest('com_jem.attendees.limit', 'limit', $app->get('list_limit'), 'int');
        $limitstart = $app->getUserStateFromRequest('com_jem.attendees.limitstart', 'limitstart', 0, 'int');
        $limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);

        //set unlimited if export or print action | task=export or task=print
        $task = $app->input->getCmd('task');
        $this->setState('unlimited', ($task == 'export' || $task == 'print') ? '1' : '');

        $filter_type      = $app->getUserStateFromRequest( 'com_jem.attendees.filter_type',      'filter_type',      0, 'int' );
        $this->setState('filter_type', $filter_type);
        $filter_search    = $app->getUserStateFromRequest( 'com_jem.attendees.filter_search',    'filter_search',   '', 'string' );
        $this->setState('filter_search', $filter_search);
        $filter_status    = $app->getUserStateFromRequest( 'com_jem.attendees.filter_status',    'filter_status',   -2, 'int' );
        $this->setState('filter_status', $filter_status);

        parent::populateState('u.username', 'asc');
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * This is necessary because the model is used by the component and
     * different modules that might need different sets of data or different
     * ordering requirements.
     *
     * @param  string  $id  A prefix for the store id.
     * @return string  A store id.
     *
     */
    protected function getStoreId($id = '')
    {
        // Compile the store id.
        $id.= ':' . $this->getState('filter_search');
        $id.= ':' . $this->getState('filter_status');
        $id.= ':' . $this->getState('filter_type');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return JDatabaseQuery
     */
    protected function getListQuery()
    {
        // Create a new query object.
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select($this->getState('list.select', 'r.*'));
        $query->from($db->quoteName('#__jem_register').' AS r');

        // Join event data
        $query->select('a.waitinglist AS waitinglist');
        $query->join('LEFT', '#__jem_events   AS a ON (r.event = a.id)');

        // Join user info
        $query->select(array('u.username','u.name','u.email'));
        $query->join('LEFT', '#__users        AS u ON (u.id = r.uid)');

        // load only data from current event
        $query->where('r.event = '.$db->Quote($this->eventid));

    // TODO: filter status
        $filter_status = $this->getState('filter_status', -2);
        if ($filter_status > -2) {
            if ($filter_status >= 1) {
                $waiting = $filter_status == 2 ? 1 : 0;
                $filter_status = 1;
                $query->where('(a.waitinglist = 0 OR r.waiting = '.$db->quote($waiting).')');
            }
            $query->where('r.status = '.$db->quote($filter_status));
        }

        // search name
        $filter_type   = $this->getState('filter_type');
        $filter_search = $this->getState('filter_search');

        if (!empty($filter_search) && $filter_type == 1) {
            $filter_search = $db->Quote('%'.$db->escape($filter_search, true).'%');
            $query->where('u.name LIKE '.$filter_search);
        }

        // search username
        if (!empty($filter_search) && $filter_type == 2) {
            $filter_search = $db->Quote('%'.$db->escape($filter_search, true).'%');
            $query->where('u.username LIKE '.$filter_search);
        }

        // Add the list ordering clause.
        $orderCol  = $this->state->get('list.ordering');
        $orderDirn = $this->state->get('list.direction');
        $allowedOrder = array('u.name', 'u.username', 'r.uid', 'r.waiting', 'r.uregdate', 'r.id');
        if (!in_array($orderCol, $allowedOrder, true)) {
            $orderCol = 'u.username';
        }
        $orderDirn = strtoupper($orderDirn) === 'DESC' ? 'DESC' : 'ASC';

        $query->order($db->escape($orderCol.' '.$orderDirn));

        return $query;
    }

    /**
     * Get event data
     *
     * @access public
     * @return object
     */
    public function getEvent()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select(array(
            'id', 'title', 'dates', 'maxplaces', 'reservedplaces', 'waitinglist',
            'pricing_mode', 'pricing_revision', 'currency', 'prices_include_tax',
            'capacity_mode',
        ));
        $query->from('#__jem_events');
        $query->where('id = '.$db->Quote($this->eventid));
        $db->setQuery( $query );
        $event = $db->loadObject();

        return $event;
    }

    public function getCommercialBreakdowns()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array(
                'i.register_id', 'i.line_number', 'i.item_name', 'i.quantity',
                'i.unit_gross', 'i.line_gross', 'i.currency', 'cp.name AS pool_name',
            ))
            ->from($db->quoteName('#__jem_register_items', 'i'))
            ->join('INNER', $db->quoteName('#__jem_register', 'r')
                . ' ON r.id = i.register_id AND r.revision = i.registration_revision')
            ->join('LEFT', $db->quoteName('#__jem_capacity_pools', 'cp') . ' ON cp.id = i.capacity_pool_id')
            ->where('r.event = ' . (int) $this->eventid)
            ->where("i.line_kind = 'admission'")
            ->order('i.register_id ASC, i.line_number ASC');
        $db->setQuery($query);
        $breakdowns = array();
        foreach ((array) $db->loadObjectList() as $line) {
            $breakdowns[(int) $line->register_id][] = $line;
        }

        return $breakdowns;
    }

    public function getPoolAvailability()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array(
                'p.id', 'p.name', 'p.capacity',
                'COALESCE(SUM(CASE WHEN r.status = 1 AND r.waiting = 0 THEN i.quantity ELSE 0 END), 0) AS used',
            ))
            ->from($db->quoteName('#__jem_capacity_pools', 'p'))
            ->join('LEFT', $db->quoteName('#__jem_register_items', 'i') . ' ON i.capacity_pool_id = p.id')
            ->join('LEFT', $db->quoteName('#__jem_register', 'r')
                . ' ON r.id = i.register_id AND r.revision = i.registration_revision')
            ->where('p.event_id = ' . (int) $this->eventid)
            ->group(array('p.id', 'p.name', 'p.capacity'))
            ->order('p.ordering ASC, p.id ASC');
        $db->setQuery($query);
        $pools = (array) $db->loadObjectList();
        foreach ($pools as $pool) {
            $pool->used = (int) $pool->used;
            $pool->remaining = max(0, (int) $pool->capacity - $pool->used);
        }

        return $pools;
    }

    public function getCapacityBreakdowns()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array(
                'a.register_id', 'a.space_name', 'a.area_name', 'a.quantity',
            ))
            ->from($db->quoteName('#__jem_register_capacity_allocations', 'a'))
            ->join('INNER', $db->quoteName('#__jem_register', 'r')
                . ' ON r.id = a.register_id AND r.revision = a.registration_revision')
            ->where('r.event = ' . (int) $this->eventid)
            ->order('a.register_id ASC, a.id ASC');
        $db->setQuery($query);
        $breakdowns = array();
        foreach ((array) $db->loadObjectList() as $row) {
            $breakdowns[(int) $row->register_id][] = $row;
        }

        return $breakdowns;
    }

    /**
     * Delete registered users
     *
     * @access public
     * @return true on success
     */
    public function remove($cid = array(), $eventId = 0)
    {
        if (is_array($cid) && count($cid))
        {
            ArrayHelper::toInteger($cid);
            $cid = array_filter($cid);

            if (empty($cid)) {
                return true;
            }

            if ((int) $eventId < 1) {
                throw new InvalidArgumentException('Event ID is required when cancelling registrations.');
            }

            (new JemRegistrationService())->cancelByIds(
                $cid,
                (int) $eventId,
                array(
                    'actorId'    => (int) Factory::getApplication()->getIdentity()->id,
                    'source'     => 'administrator.attendees.remove',
                    'reasonCode' => 'manager_cancelled',
                )
            );
        }
        return true;
    }

    /**
     * Returns a CSV file with Attendee data
     * @return boolean
     */
    public function getCsv()
    {
        $jemconfig = JemConfig::getInstance()->toRegistry();
        $separator = $jemconfig->get('csv_separator', ';');
        $delimiter = $jemconfig->get('csv_delimiter', '"');
        $csv_bom   = $jemconfig->get('csv_bom', '1');
        $comments  = $jemconfig->get('regallowcomments', 0);

        $event = $this->getEvent();
        $items = $this->getItems();
        $priced = in_array((string) ($event->pricing_mode ?? 'classic'), array('single', 'multiple', 'priced'), true);
        $breakdowns = $priced ? $this->getCommercialBreakdowns() : array();

        $waitinglist = $event->waitinglist ?? false;

        $csv = fopen('php://output', 'w');

        $header = array(
                Text::_('COM_JEM_NAME'),
                Text::_('COM_JEM_USERNAME'),
                Text::_('COM_JEM_EMAIL'),
                Text::_('COM_JEM_REGDATE'),
                Text::_($priced ? 'COM_JEM_PRICED_REGISTRATION_ORDER' : 'COM_JEM_ATTENDEES_PLACES'),
                Text::_('COM_JEM_HEADER_WAITINGLIST_STATUS')
            );
        if ($comments) {
            $header[] = Text::_('COM_JEM_COMMENT');
        }
        $header[] = Text::_('COM_JEM_ATTENDEES_REGID');

        JemCsv::putRow($csv, $header, $separator, $delimiter, '', "\n");

        foreach ($items as $item)
        {
            $status = $item->status ?? 1;
            if ($status < 0) {
                $txt_stat = 'COM_JEM_ATTENDEES_NOT_ATTENDING';
            } elseif ($status > 0) {
                $txt_stat = $item->waiting ? 'COM_JEM_ATTENDEES_ON_WAITINGLIST' : 'COM_JEM_ATTENDEES_ATTENDING';
            } else {
                $txt_stat = 'COM_JEM_ATTENDEES_INVITED';
            }
            $order = (string) $item->places;
            if ($priced) {
                $parts = array();
                foreach ($breakdowns[(int) $item->id] ?? array() as $line) {
                    $parts[] = (int) $line->quantity . 'x ' . $line->item_name
                        . ($line->pool_name ? ' (' . $line->pool_name . ')' : '');
                }
                $order = $parts
                    ? implode(' | ', $parts) . ' | ' . $item->currency . ' ' . $item->grand_total
                    : Text::_('COM_JEM_PRICED_REGISTRATION_NO_ORDER');
            }
            $data = array(
                    $item->name,
                    $item->username,
                    $item->email,
                    empty($item->uregdate) ? '' : HTMLHelper::_('date', $item->uregdate, Text::_('DATE_FORMAT_LC2')),
                    $order,
                    Text::_($txt_stat)
                );
            if ($comments) {
                $comment = strip_tags($item->comment);
                // comments are limited to 255 characters in db so we don't need to truncate them on export
                $data[] = $comment;
            }
            $data[] = $item->uid;

            JemCsv::putRow($csv, $data, $separator, $delimiter, '', "\n");
        }

        return fclose($csv);
    }
}
