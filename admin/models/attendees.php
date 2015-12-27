<?php
/**
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.modellist');

/**
 * Model: Attendees
 */
class JemModelAttendees extends JModelList
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
		
		$app = JFactory::getApplication();
		$jinput = $app->input;
		$id = $jinput->getInt('id', 0);
		$this->setId($id);
	}
	
	public function setId($eventid) {
		$this->eventid = $eventid;
	}

	
	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		$app = JFactory::getApplication();
		
		$app    = JFactory::getApplication();;
		$jinput = $app->input;
		
		$limit		= $app->getUserStateFromRequest( 'com_jem.attendees.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $app->getUserStateFromRequest( 'com_jem.attendees.limitstart', 'limitstart', 0, 'int' );
		$limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;
		
		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
		
		//set unlimited if export or print action | task=export or task=print
		$task = $jinput->getCmd('task');
		$this->setState('unlimited', ($task == 'export' || $task == 'print') ? '1' : '');
				
		$filter_type      = $app->getUserStateFromRequest( 'com_jem.attendees.filter_type',      'filter_type',     '', 'int' );
		$this->setState('filter_type', $filter_type);
		$filter_search    = $app->getUserStateFromRequest( 'com_jem.attendees.filter_search',    'filter_search',   '', 'string' );
		$this->setState('filter_search', $filter_search);
		$filter_waiting   = $app->getUserStateFromRequest( 'com_jem.attendees.waiting',          'filter_waiting',   0, 'int' );		
		$this->setState('filter_waiting', $filter_waiting);
		
		parent::populateState('u.username', 'asc');
	}
	
	
	/**
	 * Method to get a store id based on model configuration state.
	 *
	 * This is necessary because the model is used by the component and
	 * different modules that might need different sets of data or different
	 * ordering requirements.
	 *
	 * @param	string		$id	A prefix for the store id.
	 * @return	string		A store id.
	 *
	 */
	protected function getStoreId($id = '')
	{
		// Compile the store id.
		$id.= ':' . $this->getState('filter_search');
		$id.= ':' . $this->getState('filter_waitinglist');
		$id.= ':' . $this->getState('filter_type');
	
		return parent::getStoreId($id);
	}
	
	

	/**
	 * Build an SQL query to load the list data.
	 *
	 * @return	JDatabaseQuery
	 */
	protected function getListQuery()
	{
		$app = JFactory::getApplication();
		$jinput = $app->input;
		$eventid = $this->eventid;
		
		
		// Create a new query object.
		$db		= $this->getDbo();
		$query	= $db->getQuery(true);
		
		// Select the required fields from the table.
		$query->select(
				$this->getState(
						'list.select',
						'r.*'
						)
				);
		$query->from($db->quoteName('#__jem_register').' AS r');
		
		// Join event data
		$query->select('a.waitinglist AS waitinglist');
		$query->join('LEFT', '#__jem_events   AS a ON (r.event = a.id)');
		
		// Join user info
		$query->select(array('u.username','u.name','u.email'));
		$query->join('LEFT', '#__users        AS u ON (u.id = r.uid)');
		
		// load only data from current event
		$query->where('r.event = '.$db->Quote($eventid));
		
		$filter_waiting = $this->getState('filter_waiting');
		if ($filter_waiting > 0) {
			$query->where('(a.waitinglist = 0 OR r.waiting = '.$db->quote($filter_waiting-1).')');
		}
		
		// search name
		$filter_type = $this->getState('filter_type');
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
		$orderCol	= $this->state->get('list.ordering');
		$orderDirn	= $this->state->get('list.direction');

		$query->order($db->escape($orderCol.' '.$orderDirn));
		
		return $query;
		
	}
	

	/**
	 * Get event data
	 *
	 * @access public
	 * @return object
	 *
	 */
	function getEvent()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select(array('id','title','dates','maxplaces','waitinglist'));
		$query->from('#__jem_events');
		$query->where('id = '.$db->Quote($this->eventid));
		$db->setQuery( $query );
		$_event = $db->loadObject();

		return $_event;
	}

	/**
	 * Delete registered users
	 *
	 * @access public
	 * @return true on success
	 */
	function remove($cid = array())
	{
		if (count($cid))
		{
			JArrayHelper::toInteger($cid);
			$user = implode(',', $cid);
			$db = JFactory::getDbo();

			$query = $db->getQuery(true);
			$query->delete($db->quoteName('#__jem_register'));
			$query->where('id IN ('.$user.')');

			$db->setQuery($query);

			// TODO: use exception handling
			if ($db->execute() === false) {
				JError::raiseError( 4711, $db->getErrorMsg() );
			}
		}
		return true;
	}
}
