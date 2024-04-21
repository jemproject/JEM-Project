<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

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
		$query->select(array('id','title','dates','maxplaces','waitinglist'));
		$query->from('#__jem_events');
		$query->where('id = '.$db->Quote($this->eventid));
		$db->setQuery( $query );
		$event = $db->loadObject();

		return $event;
	}

	/**
	 * Delete registered users
	 *
	 * @access public
	 * @return true on success
	 */
	public function remove($cid = array())
	{
		if (is_array($cid) && count($cid))
		{
			\Joomla\Utilities\ArrayHelper::toInteger($cid);
			$user = implode(',', $cid);
            $db = Factory::getContainer()->get('DatabaseDriver');

			$query = $db->getQuery(true);
			$query->delete($db->quoteName('#__jem_register'));
			$query->where('id IN ('.$user.')');

			$db->setQuery($query);

			// TODO: use exception handling
			if ($db->execute() === false) {
				throw new Exception($db->getErrorMsg(), 500);
			}
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

		$waitinglist = isset($event->waitinglist) ? $event->waitinglist : false;

		$csv = fopen('php://output', 'w');

		$header = array(
				Text::_('COM_JEM_NAME'),
				Text::_('COM_JEM_USERNAME'),
				Text::_('COM_JEM_EMAIL'),
				Text::_('COM_JEM_REGDATE'),
				Text::_('COM_JEM_ATTENDEES_PLACES'),
				Text::_('COM_JEM_HEADER_WAITINGLIST_STATUS')
			);
		if ($comments) {
			$header[] = Text::_('COM_JEM_COMMENT');
		}
		$header[] = Text::_('COM_JEM_ATTENDEES_REGID');

		fputcsv($csv, $header, $separator, $delimiter);

		foreach ($items as $item)
		{
			$status = isset($item->status) ? $item->status : 1;
			if ($status < 0) {
				$txt_stat = 'COM_JEM_ATTENDEES_NOT_ATTENDING';
			} elseif ($status > 0) {
				$txt_stat = $item->waiting ? 'COM_JEM_ATTENDEES_ON_WAITINGLIST' : 'COM_JEM_ATTENDEES_ATTENDING';
			} else {
				$txt_stat = 'COM_JEM_ATTENDEES_INVITED';
			}
			$data = array(
					$item->name,
					$item->username,
					$item->email,
					empty($item->uregdate) ? '' : HTMLHelper::_('date', $item->uregdate, Text::_('DATE_FORMAT_LC2')),
					$item->places,
					Text::_($txt_stat)
				);
			if ($comments) {
				$comment = strip_tags($item->comment);
				// comments are limited to 255 characters in db so we don't need to truncate them on export
				$data[] = $comment;
			}
			$data[] = $item->uid;

			fputcsv($csv, $data, $separator, $delimiter);
		}

		return fclose($csv);
	}
}
