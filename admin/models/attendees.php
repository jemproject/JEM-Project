<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component attendees Model
 *
 * @package JEM
 *
 */
class JEMModelAttendees extends JModelLegacy
{
	/**
	 * Events data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * Events total
	 *
	 * @var integer
	 */
	var $_total = null;

	/**
	 * Events total
	 *
	 * @var integer
	 */
	var $_event = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination = null;

	/**
	 * Events id
	 *
	 * @var int
	 */
	var $_id = null;

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();

		$app =  JFactory::getApplication();;

		$limit		= $app->getUserStateFromRequest( 'com_jem.attendees.limit', 'limit', $app->getCfg('list_limit'), 'int');
		$limitstart = $app->getUserStateFromRequest( 'com_jem.attendees.limitstart', 'limitstart', 0, 'int' );

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);

		//set unlimited if export or print action | task=export or task=print
		$this->setState('unlimited', JRequest::getString('task'));


		$jinput = JFactory::getApplication()->input;
		$id = $jinput->get('id','','int');

		$this->setId($id);


	}

	/**
	 * Method to set the category identifier
	 *
	 * @access	public
	 * @param	int Category identifier
	 */
	function setId($id)
	{
		// Set id and wipe data
		$this->_id	    = $id;
		$this->_data 	= null;
	}

	/**
	 * Method to get categories item data
	 *
	 * @access public
	 * @return array
	 */
	function getData()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_data))
		{
			$query = $this->_buildQuery();

			if ($this->getState('unlimited') == '') {
				$this->_data = $this->_getList($query, $this->getState('limitstart'), $this->getState('limit'));
			} else {
				$this->_data = $this->_getList($query);
			}
		}

		return $this->_data;
	}

	/**
	 * Method to get the total nr of the attendees
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_total))
		{
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}

	/**
	 * Method to get a pagination object for the events
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_pagination))
		{
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination( $this->getTotal(), $this->getState('limitstart'), $this->getState('limit') );
		}

		return $this->_pagination;
	}

	/**
	 * Method to build the query for the attendees
	 *
	 * @access private
	 * @return integer
	 *
	 */
	function _buildQuery()
	{

		$app =  JFactory::getApplication();
		$db = JFactory::getDbo();

		// Filter by search in title
		$filter = $app->getUserStateFromRequest( 'com_jem.attendees.filter', 'filter', '', 'int' );
		$search = $app->getUserStateFromRequest( 'com_jem.attendees.filter_search', 'filter_search', '', 'string' );
		$search = $db->Quote('%'.$db->escape($search, true).'%');
		$filter_waiting	= $app->getUserStateFromRequest( 'com_jem.attendees.waiting',	'filter_waiting',	0, 'int' );
		$filter_order		= $app->getUserStateFromRequest( 'com_jem.attendees.filter_order', 		'filter_order', 	'u.username', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.attendees.filter_order_Dir',	'filter_order_Dir',	'', 'word' );
		$filter_order		= JFilterInput::getinstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= JFilterInput::getinstance()->clean($filter_order_Dir, 'word');



		$query = $db->getQuery(true);

		$query->select(array('r.*','u.username','u.name','u.email'));
		$query->from('#__jem_register AS r');
		$query->join('LEFT', '#__jem_events AS a ON (r.event = a.id)');
		$query->join('LEFT', '#__users AS u ON (u.id = r.uid)');


		$query->where('r.event = '.$this->_id);


		if ($filter_waiting) {
			$query->where('(a.waitinglist = 0 OR r.waiting = '.$db->quote($filter_waiting-1).')');


		}

				// search name
				if ($search && $filter == 1) {
					$query->where('u.name LIKE '.$search);
				}

				// search username
				if ($search && $filter == 2) {
					$query->where('u.username LIKE '.$search);
				}



		// Add the list ordering clause.
		$orderCol	= $filter_order;
		$orderDirn	= $filter_order_Dir;

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
		$query->where('id = '.$this->_id);
		$db->setQuery( $query );
		$_event = $db->loadObject();

		return $_event;
	}

	/**
	 * Delete registered users
	 *
	 * @access public
	 * @return true on success
	 *
	 */
	function remove($cid = array())
	{
		if (count( $cid ))
		{
			$user = implode(',', $cid);
			$db = JFactory::getDbo();

			$query = $db->getQuery(true);
			$query->delete($db->quoteName('#__jem_register'));
			$query->where('id IN ('.$user.')');

			$db->setQuery($query);

			if (!$this->_db->query()) {
				JError::raiseError( 4711, $this->_db->getErrorMsg() );
			}
		}
		return true;
	}
}
?>