<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

require_once dirname(__FILE__) . '/eventslist.php';

/**
 * Venue-Model
*/
class JemModelVenue extends JemModelEventslist
{
	public function __construct()
	{
		$app 			= JFactory::getApplication();
		$jinput			= $app->input;
		$params			= $app->getParams();

		# determing the id to load
		if ($jinput->get('id',null,'int')) {
			$id = $jinput->get('id',null,'int');
		} else {
			$id = $params->get('id');
		}
		$this->setId((int)$id);

		parent::__construct();
	}

	/**
	 * Method to auto-populate the model state.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// parent::populateState($ordering, $direction);

		$app 			= JFactory::getApplication();
		$jemsettings	= JemHelper::config();
		$jinput			= JFactory::getApplication()->input;
		$itemid 		= JRequest::getInt('id', 0) . ':' . JRequest::getInt('Itemid', 0);
		$params 		= $app->getParams();
		$task           = $jinput->get('task','','cmd');

		// List state information
		$limitstart = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.limitstart', 'limitstart', 0, 'int');
		$this->setState('list.start', $limitstart);

		$limit		= $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.limit', 'limit', $jemsettings->display_num, 'int');
		$this->setState('list.limit', $limit);

		# Search
		$search = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.filter_search', 'filter_search', '', 'string');
		$this->setState('filter.filter_search', $search);

		# FilterType
		$filtertype = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.filter_type', 'filter_type', '', 'int');
		$this->setState('filter.filter_type', $filtertype);

		# filter_order
		$orderCol = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.filter_order', 'filter_order', 'a.dates', 'cmd');
		$this->setState('filter.filter_ordering', $orderCol);

		# filter_direction
		$listOrder = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.filter_order_Dir', 'filter_order_Dir', 'ASC', 'word');
		$this->setState('filter.filter_direction', $listOrder);

		if ($orderCol == 'a.dates') {
			$orderby = array('a.dates ' . $listOrder, 'a.times ' . $listOrder);
		} else {
			$orderby = $orderCol . ' ' . $listOrder;
		}
		$this->setState('filter.orderby', $orderby);

		# params
		$this->setState('params', $params);

		if ($task == 'archive') {
			$this->setState('filter.published',2);
		} else {
			$this->setState('filter.published',1);
		}

		$this->setState('filter.access', true);
		$this->setState('filter.groupby',array('a.id'));
	}


	/**
	 * Method to get a list of events.
	 */
	public function getItems()
	{
		$items	= parent::getItems();
		/* no additional things to do yet - place holder */
		return $items;
	}


	/**
	 * @return	JDatabaseQuery
	 */
	function getListQuery()
	{
		$params  = $this->state->params;
		$jinput  = JFactory::getApplication()->input;
		$task    = $jinput->get('task','','cmd');

		// Create a new query object.
		$query = parent::getListQuery();

		$query->where('a.locid = '.$this->_id);

		// here we can extend the query of the Eventslist model
		return $query;
	}


	/**
	 * Method to set the venue id
	 *
	 * The venue-id can be set by a menu-parameter
	 */
	function setId($id)
	{
		// Set new venue ID and wipe data
		$this->_id			= $id;
		$this->_data		= null;
	}

	/**
	 * set limit
	 * @param int value
	 */
	function setLimit($value)
	{
		$this->setState('limit', (int) $value);
	}

	/**
	 * set limitstart
	 * @param int value
	 */
	function setLimitStart($value)
	{
		$this->setState('limitstart', (int) $value);
	}

	/**
	 * Method to get a specific Venue
	 *
	 * @access public
	 * @return array
	 */
	function getVenue()
	{
		$user  = JFactory::getUser();

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);
		$_venue = array();

		$query->select('id, venue, published, city, state, url, street, custom1, custom2, custom3, custom4, custom5, '.
				' custom6, custom7, custom8, custom9, custom10, locimage, meta_keywords, meta_description, '.
				' created, locdescription, country, map, latitude, longitude, postalCode, checked_out AS vChecked_out, checked_out_time AS vChecked_out_time, '.
				' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as slug');
		$query->from($db->quoteName('#__jem_venues'));
		$query->where('id = '.$this->_id);

		$db->setQuery($query);
		$_venue = $db->loadObject();

		if (empty($_venue)) {
			return JError::raiseError(404, JText::_('COM_JEM_VENUE_NOTFOUND'));
		}

		$_venue->attachments = JEMAttachment::getAttachments('venue'.$_venue->id);

		return $_venue;
	}
}
?>