<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

// No direct access
defined('_JEXEC') or die();

// Base this model on the backend version.
require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/event.php';

/**
 * Editevent Model
 */
class JEMModelEditevent extends JEMModelEvent
{

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 */
	protected function populateState()
	{
		$app = JFactory::getApplication();

		// Load state from the request.
		$pk = JRequest::getInt('a_id');
		$this->setState('event.id', $pk);

		$this->setState('event.catid', JRequest::getInt('catid'));

		$return = JRequest::getVar('return', null, 'default', 'base64');
		$this->setState('return_page', urldecode(base64_decode($return)));

		// Load the parameters.
		$params = $app->getParams();
		$this->setState('params', $params);

		$this->setState('layout', JRequest::getCmd('layout'));
	}

	/**
	 * Method to get event data.
	 *
	 * @param integer	The id of the event.
	 *
	 * @return mixed item data object on success, false on failure.
	 */
	public function getItem($itemId = null)
	{
		$jemsettings = JemHelper::config();

		// Initialise variables.
		$itemId = (int) (!empty($itemId)) ? $itemId : $this->getState('event.id');

		// Get a row instance.
		$table = $this->getTable();

		// Attempt to load the row.
		$return = $table->load($itemId);

		// Check for a table object error.
		if ($return === false && $table->getError()) {
			$this->setError($table->getError());
			return false;
		}

		$properties = $table->getProperties(1);
		$value = JArrayHelper::toObject($properties, 'JObject');

		// Backup current recurrence values
		if ($value->id){
			$value->recurr_bak = new stdClass;
			foreach (get_object_vars($value) as $k => $v) {
				if (strncmp('recurrence_', $k, 11) === 0) {
					$value->recurr_bak->$k = $v;
				}
			}
		}

		// Convert attrib field to Registry.
		$registry = new JRegistry();
		$registry->loadString($value->attribs);

		$globalsettings = JEMHelper::globalattribs();
		$globalregistry = new JRegistry();
		$globalregistry->loadString($globalsettings);

		$value->params = clone $globalregistry;
		$value->params->merge($registry);

		// Compute selected asset permissions.
		$user = JFactory::getUser();
		$userId = $user->get('id');
		//$asset = 'com_jem.event.' . $value->id;
		$asset = 'com_jem';

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select(array(
				'count(id)'
		));
		$query->from('#__jem_register');
		$query->where(array(
				'event= ' . $db->quote($itemId),
				'waiting= 0'
		));

		$db->setQuery($query);
		$res = $db->loadResult();
		$value->booked = $res;

		$files = JEMAttachment::getAttachments('event' . $itemId);
		$value->attachments = $files;

		// Check general edit permission first.
		if ($user->authorise('core.edit', $asset)) {
			$value->params->set('access-edit', true);
		}
		// Now check if edit.own is available.
		elseif (!empty($userId) && $user->authorise('core.edit.own', $asset)) {
			// Check for a valid user and that they are the owner.
			if ($userId == $value->created_by) {
				$value->params->set('access-edit', true);
			}
		}

		// Check edit state permission.
		if ($itemId) {
			// Existing item
			$value->params->set('access-change', $user->authorise('core.edit.state', $asset));
		}
		else {
			// New item.
			$catId = (int) $this->getState('event.catid');

			if ($catId) {
				$value->params->set('access-change', $user->authorise('core.edit.state', 'com_jem.category.' . $catId));
				$value->catid = $catId;
			}
			else {
				$value->params->set('access-change', $user->authorise('core.edit.state', 'com_jem'));
			}
		}
		
		$value->author_ip = $jemsettings->storeip ? JemHelper::retrieveIP() : false;
		
		$value->articletext = $value->introtext;
		if (!empty($value->fulltext)) {
			$value->articletext .= '<hr id="system-readmore" />' . $value->fulltext;
		}

		return $value;
	}

	protected function loadForm($name, $source = null, $options = array(), $clear = false, $xpath = false)
	{
		JForm::addFieldPath(JPATH_COMPONENT_ADMINISTRATOR . '/models/fields');

		return parent::loadForm($name, $source, $options, $clear, $xpath);
	}

	/**
	 * Get the return URL.
	 *
	 * @return string return URL.
	 *
	 */
	public function getReturnPage()
	{
		return base64_encode(urlencode($this->getState('return_page')));
	}

	############
	## VENUES ##
	############

	/**
	 * Get venues-data
	 */
	function getVenues()
	{
		$query 		= $this->buildQueryVenues();
		$pagination = $this->getVenuesPagination();

		$rows 		= $this->_getList($query, $pagination->limitstart, $pagination->limit);

		return $rows;
	}


	/**
	 * venues-query
	 */
	function buildQueryVenues()
	{
		$app 				= JFactory::getApplication();
		$params		 		= JemHelper::globalattribs();
		
		$filter_order 		= $app->getUserStateFromRequest('com_jem.selectvenue.filter_order', 'filter_order', 'l.venue', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.selectvenue.filter_order_Dir', 'filter_order_Dir', 'ASC', 'word');

		$filter_order 		= JFilterInput::getinstance()->clean($filter_order, 'cmd');
		$filter_order_Dir 	= JFilterInput::getinstance()->clean($filter_order_Dir, 'word');

		$filter_type 		= $app->getUserStateFromRequest('com_jem.selectvenue.filter_type', 'filter_type', '', 'int');
		$search      		= $app->getUserStateFromRequest('com_jem.selectvenue.filter_search', 'filter_search', '', 'string');
		$search      		= $this->_db->escape(trim(JString::strtolower($search)));

		// Query
		$db 	= JFactory::getDBO();
		$query = $db->getQuery(true);
		$query->select(array('l.id','l.state','l.city','l.country','l.published','l.venue','l.ordering'));
		$query->from('#__jem_venues as l');

		// where
		$where = array();
		$where[] = 'l.published = 1';

		/* something to search for? (we like to search for "0" too) */
		if ($search || ($search === "0")) {
			switch ($filter_type) {
				case 1: /* Search venues */
					$where[] = 'LOWER(l.venue) LIKE "%' . $search . '%"';
					break;
				case 2: // Search city
					$where[] = 'LOWER(l.city) LIKE "%' . $search . '%"';
					break;
				case 3: // Search state
					$where[] = 'LOWER(l.state) LIKE "%' . $search . '%"';
			}
		}
		
		if ($params->get('global_show_ownedvenuesonly')) {
			$user = JFactory::getUser();
			$userid = $user->get('id');
			$where[] = ' created_by = ' . (int) $userid;
		}

		$query->where($where);

		if (strtoupper($filter_order_Dir) !== 'DESC') {
			$filter_order_Dir = 'ASC';
		}

		// ordering
		if ($filter_order && $filter_order_Dir) {
			$orderby = $filter_order . ' ' . $filter_order_Dir;
		} else {
			$orderby = array('l.venue ASC','l.ordering ASC');
		}
		$query->order($orderby);

		return $query;
	}

    /**
     * venues-Pagination
     **/
	function getVenuesPagination() {

		$jemsettings = JemHelper::config();
		$app         = JFactory::getApplication();
		$limit       = $app->getUserStateFromRequest('com_jem.selectvenue.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart  = JRequest::getInt('limitstart');
		// correct start value if required
		$limitstart  = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

		$query = $this->buildQueryVenues();
		$total = $this->_getListCount($query);

		// Create the pagination object
		jimport('joomla.html.pagination');
		$pagination = new JPagination($total, $limitstart, $limit);

		return $pagination;
	}


	##############
	## CONTACTS ##
	##############

	/**
	 * Get contacts-data
	 */
	function getContacts()
	{
		$query 		= $this->buildQueryContacts();
		$pagination = $this->getContactsPagination();

		$rows 		= $this->_getList($query, $pagination->limitstart, $pagination->limit);

		return $rows;
	}


	/**
	 * contacts-Pagination
	 **/
	function getContactsPagination() {

		$jemsettings = JemHelper::config();
		$app         = JFactory::getApplication();
		$limit       = $app->getUserStateFromRequest('com_jem.selectcontact.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart  = JRequest::getInt('limitstart');
		// correct start value if required
		$limitstart  = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

		$query = $this->buildQueryContacts();
		$total = $this->_getListCount($query);

		// Create the pagination object
		jimport('joomla.html.pagination');
		$pagination = new JPagination($total, $limitstart, $limit);

		return $pagination;
	}


	/**
	 * contacts-query
	 */
	function buildQueryContacts()
	{
		$app		  		= JFactory::getApplication();
		$jemsettings  		= JemHelper::config();

		$filter_order 		= $app->getUserStateFromRequest('com_jem.selectcontact.filter_order', 'filter_order', 'con.ordering', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.selectcontact.filter_order_Dir', 'filter_order_Dir', '', 'word');

		$filter_order 		= JFilterInput::getinstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= JFilterInput::getinstance()->clean($filter_order_Dir, 'word');

		$filter_type   		= $app->getUserStateFromRequest('com_jem.selectcontact.filter_type', 'filter_type', '', 'int');
		$search       		= $app->getUserStateFromRequest('com_jem.selectcontact.filter_search', 'filter_search', '', 'string');
		$search       		= $this->_db->escape(trim(JString::strtolower($search)));

		// Query
		$db 	= JFactory::getDBO();
		$query = $db->getQuery(true);
		$query->select(array('con.*'));
		$query->from('#__contact_details As con');
		
		// where
		$where = array();
		$where[] = 'con.published = 1';

		/* something to search for? (we like to search for "0" too) */
		if ($search || ($search === "0")) {
			switch ($filter_type) {
				case 1: /* Search name */
					$where[] = ' LOWER(con.name) LIKE \'%' . $search . '%\' ';
					break;
				case 2: /* Search address (not supported yet, privacy) */
					//$where[] = ' LOWER(con.address) LIKE \'%' . $search . '%\' ';
					break;
				case 3: // Search city
					$where[] = ' LOWER(con.suburb) LIKE \'%' . $search . '%\' ';
					break;
				case 4: // Search state
					$where[] = ' LOWER(con.state) LIKE \'%' . $search . '%\' ';
					break;
			}
		}
		$query->where($where);

		// ordering
	
		// ensure it's a valid order direction (asc, desc or empty)
		if (!empty($filter_order_Dir) && strtoupper($filter_order_Dir) !== 'DESC') {
			$filter_order_Dir = 'ASC';
		}

		if ($filter_order != '') {
			$orderby = $filter_order . ' ' . $filter_order_Dir;
			if ($filter_order != 'con.name') {
				$orderby = array($orderby, 'con.name'); // in case of city or state we should have a useful second ordering
			}
		} else {
			$orderby = 'con.name';
		}
		$query->order($orderby);
		
		return $query;
	}
}
