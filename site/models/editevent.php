<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
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
	 * @param integer	The id of the article.
	 *
	 * @return mixed item data object on success, false on failure.
	 */
	public function getItem($itemId = null)
	{
		// $jemsettings = JEMAdmin::config();

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

	/**
	 * logic to get the venueslist
	 *
	 * @access public
	 * @return array
	 */
	function getVenues()
	{
		$app = JFactory::getApplication();

		$params = $app->getParams();

		$where = $this->_buildVenuesWhere();
		$orderby = $this->_buildVenuesOrderBy();

		$limit = $app->getUserStateFromRequest('com_jem.selectvenue.limit', 'limit', $params->def('display_num', 0), 'int');
		$limitstart = JRequest::getInt('limitstart');

		$query = 'SELECT l.id, l.venue, l.state, l.city, l.country, l.published' . ' FROM #__jem_venues AS l' . $where . $orderby;

		$this->_db->setQuery($query, $limitstart, $limit);
		$rows = $this->_db->loadObjectList();

		return $rows;
	}

	/**
	 * logic to get the venueslist but limited to the user owned venues
	 *
	 * @access public
	 * @return array
	 */
	function getUserVenues()
	{
		$user = JFactory::getUser();
		$userid = $user->get('id');

		$query = 'SELECT id AS value, venue AS text'
				. ' FROM #__jem_venues'
				. ' WHERE created_by = '
				. (int) $userid
				. ' AND published = 1'
				. ' ORDER BY venue';
		$this->_db->setQuery($query);
		$this->_venues = $this->_db->loadObjectList();

		return $this->_venues;
	}

	/**
	 * Method to build the ordering
	 *
	 * @access private
	 * @return array
	 */
	function _buildVenuesOrderBy()
	{
		$filter_order = JRequest::getCmd('filter_order');
		$filter_order_Dir = JRequest::getCmd('filter_order_Dir');

		$orderby = ' ORDER BY ';

		if ($filter_order && $filter_order_Dir) {
			$orderby .= $filter_order . ' ' . $filter_order_Dir . ', ';
		}

		$orderby .= 'l.ordering';

		return $orderby;
	}

	/**
	 * Method to build the WHERE clause
	 *
	 * @access private
	 * @return array
	 */
	function _buildVenuesWhere()
	{
		$jemsettings = JEMHelper::config();
		$filter_type = JRequest::getInt('filter_type');
		$filter = JRequest::getString('filter_search');
		$filter = $this->_db->escape(trim(JString::strtolower($filter)));

		$where = array();

		$where[] = 'l.published = 1';

		if ($filter && $filter_type == 1) {
			$where[] = 'LOWER(l.venue) LIKE "%' . $filter . '%"';
		}

		if ($filter && $filter_type == 2) {
			$where[] = 'LOWER(l.city) LIKE "%' . $filter . '%"';
		}

		if ($filter && $filter_type == 3) {
			$where[] = 'LOWER(l.state) LIKE "%' . $filter . '%"';
		}

		if ($jemsettings->ownedvenuesonly) {
			$user = JFactory::getUser();
			$userid = $user->get('id');
			$where[] = ' created_by = ' . (int) $userid;
		}

		$where = (count($where) ? ' WHERE ' . implode(' AND ', $where) : '');

		return $where;
	}

	/**
	 * Method to build the query for the contacts
	 *
	 * @access private
	 * @return string
	 */
	function getContact()
	{
		// Get the WHERE and ORDER BY clauses for the query
		$where = $this->_buildContactWhere();
		$orderby = $this->_buildContactOrderBy();

		$query = 'SELECT con.*'
				. ' FROM #__contact_details AS con'
				. $where
				. $orderby;
		$this->_db->setQuery($query);
		$contacts = $this->_db->loadObjectList();

		return $contacts;
	}

	/**
	 * Method to build the orderby clause of the query for the contacts
	 *
	 * @access private
	 * @return string
	 */
	function _buildContactOrderBy()
	{
		$app = JFactory::getApplication();

		$filter_order = $app->getUserStateFromRequest('com_jem.contactelement.filter_order', 'filter_order', 'con.ordering', 'cmd');
		$filter_order_Dir = $app->getUserStateFromRequest('com_jem.contactelement.filter_order_Dir', 'filter_order_Dir', '', 'word');

		$filter_order = JFilterInput::getinstance()->clean($filter_order, 'cmd');
		$filter_order_Dir = JFilterInput::getinstance()->clean($filter_order_Dir, 'word');

		if ($filter_order != '') {
			$orderby = ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir;
		}
		else {
			$orderby = ' ORDER BY con.name ';
		}

		return $orderby;
	}

	/**
	 * Method to build the where clause of the query for the contacts
	 *
	 * @access private
	 * @return string
	 */
	function _buildContactWhere()
	{
		$app = JFactory::getApplication();

		$filter = $app->getUserStateFromRequest('com_jem.contactelement.filter', 'filter', '', 'int');
		$filter_state = $app->getUserStateFromRequest('com_jem.contactelement.filter_state', 'filter_state', '', 'word');
		$search = $app->getUserStateFromRequest('com_jem.contactelement.filter_search', 'filter_search', '', 'string');
		$search = $this->_db->escape(trim(JString::strtolower($search)));

		$where = array();

		/*
		 * Filter state
		 */
		if ($filter_state) {
			if ($filter_state == 'P') {
				$where[] = 'con.published = 1';
			}
			else
				if ($filter_state == 'U') {
					$where[] = 'con.published = 0';
				}
		}

		/*
		 * Search venues
		 */
		if ($search && $filter == 1) {
			$where[] = ' LOWER(con.name) LIKE \'%' . $search . '%\' ';
		}

		/*
		 * Search address
		 */
		if ($search && $filter == 2) {
			$where[] = ' LOWER(con.address) LIKE \'%' . $search . '%\' ';
		}

		/*
		 * Search city
		 */
		if ($search && $filter == 3) {
			$where[] = ' LOWER(con.suburb) LIKE \'%' . $search . '%\' ';
		}

		/*
		 * Search state
		 */
		if ($search && $filter == 4) {
			$where[] = ' LOWER(con.state) LIKE \'%' . $search . '%\' ';
		}

		$where = (count($where) ? ' WHERE ' . implode(' AND ', $where) : '');

		return $where;
	}

	/**
	 * Method to get the total number
	 *
	 * @access public
	 * @return integer
	 */
	function getCountitems()
	{
		// Initialize variables
		$where = $this->_buildVenuesWhere();

		$query = 'SELECT count(*)'
				. ' FROM #__jem_venues AS l'
				. $where;
		$this->_db->SetQuery($query);

		return $this->_db->loadResult();
	}

	/**
	 * Method to get the total number of contacts
	 *
	 * @access public
	 * @return integer
	 */
	function getCountContactitems()
	{
		// Initialize variables
		$where = $this->_buildContactWhere();

		$query = 'SELECT count(*)'
				. ' FROM #__contact_details AS con'
				. $where;
		$this->_db->SetQuery($query);

		return $this->_db->loadResult();
	}
}