<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Filter\InputFilter;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

// Base this model on the backend version.
require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/event.php';

/**
 * Editevent Model
 */
class JemModelEditevent extends JemModelEvent
{
	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 */
	protected function populateState()
	{
		$app = Factory::getApplication();

		// Load state from the request.
		$pk = $app->input->getInt('a_id', 0);
		$this->setState('event.id', $pk);

		$fromId = $app->input->getInt('from_id', 0);
		$this->setState('event.from_id', $fromId);

		$catid = $app->input->getInt('catid', 0);
		$this->setState('event.catid', $catid);

		$locid = $app->input->getInt('locid', 0);
		$this->setState('event.locid', $locid);

		$date = $app->input->getCmd('date', '');
		$this->setState('event.date', $date);

		$return = $app->input->get('return', '', 'base64');
		$this->setState('return_page', base64_decode($return));

		// Load the parameters.
		$params = $app->getParams();
		$this->setState('params', $params);

		$this->setState('layout', $app->input->getCmd('layout', ''));
	}

	/**
	 * Method to get event data.
	 *
	 * @param  integer The id of the event.
	 *
	 * @return mixed item data object on success, false on failure.
	 */
	public function getItem($itemId = null)
	{
		$jemsettings = JemHelper::config();

		// Initialise variables.
		$itemId = (int) (!empty($itemId)) ? $itemId : $this->getState('event.id');

		$doCopy = false;
		if (!$itemId && $this->getState('event.from_id')) {
			$itemId = $this->getState('event.from_id');
			$doCopy = true;
		}

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
		$value = ArrayHelper::toObject($properties, 'stdClass');

		if ($doCopy) {
			$value->id = 0;
			$value->author_ip = '';
			$value->created = '';
			$value->created_by = '';
			$value->created_by_alias = '';
			$value->modified = '';
			$value->modified_by = '';
			$value->version = '';
			$value->hits = '';
			$value->recurrence_type = 0;
			$value->recurrence_first_id = 0;
			$value->recurrence_counter = 0;
		}

		// Backup current recurrence values
		if ($value->id) {
			$value->recurr_bak = new stdClass;
			foreach (get_object_vars($value) as $k => $v) {
				if (strncmp('recurrence_', $k, 11) === 0) {
					$value->recurr_bak->$k = $v;
				}
			}
		}

		// Convert attrib field to Registry.
		$registry = new Registry();
		$registry->loadString($value->attribs ?? '{}');

		$globalregistry = JemHelper::globalattribs();

		$value->params = clone $globalregistry;
		$value->params->merge($registry);

		// Compute selected asset permissions.
		$user = JemFactory::getUser();
		//$userId = $user->get('id');
		//$asset = 'com_jem.event.' . $value->id;
		//$asset = 'com_jem';

        $db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select(array('count(id)'));
		$query->from('#__jem_register');
		$query->where(array('event = ' . $db->quote($value->id), 'waiting = 0', 'status = 1'));

		$db->setQuery($query);
		$res = $db->loadResult();
		$value->booked = (int)$res;
		if (!empty($value->maxplaces)) {
			$value->avplaces = $value->maxplaces - $value->booked;
		}

		$value->reginvitedonly = !empty($value->registra) && ($value->registra & 2);

		// Get attachments - but not on copied events
		$files = JemAttachment::getAttachments('event' . $value->id);
		$value->attachments = $files;

		// Preset values on new events
		if (!$itemId) {
			$catid = (int) $this->getState('event.catid');
			$locid = (int) $this->getState('event.locid');
			$date  = $this->getState('event.date');

			// ???
			if (empty($value->catid) && !empty($catid)) {
				$value->catid = $catid;
			}

			if (empty($value->locid) && !empty($locid)) {
				$value->locid = $locid;
			}

			if (empty($value->dates) && JemHelper::isValidDate($date)) {
				$value->dates = $date;
			}
		}

		// Check edit permission.
		$value->params->set('access-edit', $user->can('edit', 'event', $value->id, $value->created_by));

		// Check edit state permission.
		if (!$itemId && ($catId = (int) $this->getState('event.catid'))) {
			// New item.
			$cats = array($catId);
		} else {
			// Existing item (or no category)
			$cats = false;
		}
		$value->params->set('access-change', $user->can('publish', 'event', $value->id, $value->created_by, $cats));

		$value->author_ip = $jemsettings->storeip ? JemHelper::retrieveIP() : false;

		$value->articletext = $value->introtext;
		if (!empty($value->fulltext)) {
			$value->articletext .= '<hr id="system-readmore" />' . $value->fulltext;
		}

		return $value;
	}

	protected function loadForm($name, $source = null, $options = array(), $clear = false, $xpath = false)
	{
	//	JForm::addFieldPath(JPATH_COMPONENT_ADMINISTRATOR . '/models/fields');

		return parent::loadForm($name, $source, $options, $clear, $xpath);
	}

	/**
	 * Get the return URL.
	 *
	 * @return string return URL.
	 */
	public function getReturnPage()
	{
		return base64_encode($this->getState('return_page'));
	}

	############
	## VENUES ##
	############

	/**
	 * Get venues-data
	 */
	public function getVenues()
	{
		$query      = $this->buildQueryVenues();
		$pagination = $this->getVenuesPagination();

		$rows = $this->_getList($query, $pagination->limitstart, $pagination->limit);

		return $rows;
	}

	/**
	 * venues-query
	 */
	protected function buildQueryVenues()
	{
		$app              = Factory::getApplication();
		$params           = JemHelper::globalattribs();

		$filter_order     = $app->getUserStateFromRequest('com_jem.selectvenue.filter_order', 'filter_order', 'l.venue', 'cmd');
		$filter_order_Dir = $app->getUserStateFromRequest('com_jem.selectvenue.filter_order_Dir', 'filter_order_Dir', 'ASC', 'word');

		$filter_order     = InputFilter::getinstance()->clean($filter_order, 'cmd');
		$filter_order_Dir = InputFilter::getinstance()->clean($filter_order_Dir, 'word');

		$filter_type      = $app->getUserStateFromRequest('com_jem.selectvenue.filter_type', 'filter_type', 0, 'int');
		$search           = $app->getUserStateFromRequest('com_jem.selectvenue.filter_search', 'filter_search', '', 'string');
		$search           = $this->_db->escape(trim(\Joomla\String\StringHelper::strtolower($search)));

		// Query
        $db = Factory::getContainer()->get('DatabaseDriver');
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
			$user = JemFactory::getUser();
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
	public function getVenuesPagination()
	{
		$jemsettings = JemHelper::config();
		$app         = Factory::getApplication();
		$limit       = $app->getUserStateFromRequest('com_jem.selectvenue.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart  = $app->input->getInt('limitstart', 0);
		// correct start value if required
		$limitstart  = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

		$query = $this->buildQueryVenues();
		$total = $this->_getListCount($query);

		// Create the pagination object
		$pagination = new Pagination($total, $limitstart, $limit);

		return $pagination;
	}

	##############
	## CONTACTS ##
	##############

	/**
	 * Get contacts-data
	 */
	public function getContacts()
	{
		$query      = $this->buildQueryContacts();
		$pagination = $this->getContactsPagination();

		$rows = $this->_getList($query, $pagination->limitstart, $pagination->limit);

		return $rows;
	}

	/**
	 * contacts-Pagination
	 **/
	public function getContactsPagination()
	{
		$jemsettings = JemHelper::config();
		$app         = Factory::getApplication();
		$limit       = $app->getUserStateFromRequest('com_jem.selectcontact.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart  = $app->input->getInt('limitstart', 0);
		// correct start value if required
		$limitstart  = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

		$query = $this->buildQueryContacts();
		$total = $this->_getListCount($query);

		// Create the pagination object
		$pagination = new Pagination($total, $limitstart, $limit);

		return $pagination;
	}

	/**
	 * contacts-query
	 */
	protected function buildQueryContacts()
	{
		$app              = Factory::getApplication();
		$jemsettings      = JemHelper::config();

		$filter_order     = $app->getUserStateFromRequest('com_jem.selectcontact.filter_order', 'filter_order', 'con.ordering', 'cmd');
		$filter_order_Dir = $app->getUserStateFromRequest('com_jem.selectcontact.filter_order_Dir', 'filter_order_Dir', '', 'word');

		$filter_order     = InputFilter::getinstance()->clean($filter_order, 'cmd');
		$filter_order_Dir = InputFilter::getinstance()->clean($filter_order_Dir, 'word');

		$filter_type      = $app->getUserStateFromRequest('com_jem.selectcontact.filter_type', 'filter_type', 0, 'int');
		$search           = $app->getUserStateFromRequest('com_jem.selectcontact.filter_search', 'filter_search', '', 'string');
		$search           = $this->_db->escape(trim(\Joomla\String\StringHelper::strtolower($search)));

		// Query
        $db = Factory::getContainer()->get('DatabaseDriver');
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

	###########
	## USERS ##
	###########

	/**
	 * Get users data
	 */
	public function getUsers()
	{
		$query      = $this->buildQueryUsers();
		$pagination = $this->getUsersPagination();

		$rows       = $this->_getList($query, $pagination->limitstart, $pagination->limit);

		// Add registration status if available
		$itemId     = (int)$this->getState('event.id');
        $db         = Factory::getContainer()->get('DatabaseDriver');
		$qry        = $db->getQuery(true);
		// #__jem_register (id, event, uid, waiting, status, comment)
		$qry->select(array('reg.uid, reg.status, reg.waiting, reg.places'));
		$qry->from('#__jem_register As reg');
		$qry->where('reg.event = ' . $itemId);
		$db->setQuery($qry);
		$regs = $db->loadObjectList('uid');

	//	JemHelper::addLogEntry((string)$qry . "\n" . print_r($regs, true), __METHOD__);

		foreach ($rows AS &$row) {
			if (array_key_exists($row->id, $regs)) {
				$row->status = $regs[$row->id]->status;
				$row->places = $regs[$row->id]->places;
				if ($row->status == 1 && $regs[$row->id]->waiting) {
					++$row->status;
				}
			} else {
				$row->status = -99;
				$row->places = 0;
			}
		}

		return $rows;
	}

	/**
	 * users-Pagination
	 **/
	public function getUsersPagination()
	{
		$jemsettings = JemHelper::config();
		$app         = Factory::getApplication();
		$limit       = 0;//$app->getUserStateFromRequest('com_jem.selectusers.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart  = 0;//$app->input->getInt('limitstart', 0);
		// correct start value if required
		$limitstart  = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;

		$query = $this->buildQueryUsers();
		$total = $this->_getListCount($query);

		// Create the pagination object
		$pagination = new Pagination($total, $limitstart, $limit);

		return $pagination;
	}

	/**
	 * users-query
	 */
	protected function buildQueryUsers()
	{
		$app              = Factory::getApplication();
		$jemsettings      = JemHelper::config();

		// no filters, hard-coded
		$filter_order     = 'usr.name';
		$filter_order_Dir = '';
		$filter_type      = '';
		$search           = '';

		// Query
        $db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select(array('usr.id, usr.name'));
		$query->from('#__users As usr');

		// where
		$where = array();
		$where[] = 'usr.block = 0';
		$where[] = 'NOT usr.activation > 0';

		/* something to search for? (we like to search for "0" too) */
		if ($search || ($search === "0")) {
			switch ($filter_type) {
				case 1: /* Search name */
					$where[] = ' LOWER(usr.name) LIKE \'%' . $search . '%\' ';
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
			if ($filter_order != 'usr.name') {
				$orderby = array($orderby, 'usr.name'); // in case of (???) we should have a useful second ordering
			}
		} else {
			$orderby = 'usr.name ' . $filter_order_Dir;
		}
		$query->order($orderby);

		return $query;
	}

	/**
	 * Get list of invited users.
	 */
	public function getInvitedUsers()
	{
		$itemId = (int)$this->getState('event.id');
        $db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		// #__jem_register (id, event, uid, waiting, status, comment)
		$query->select(array('reg.uid'));
		$query->from('#__jem_register As reg');
		$query->where('reg.event = ' . $itemId);
		$query->where('reg.status = 0');
		$db->setQuery($query);
		$regs = $db->loadColumn();

	//	JemHelper::addLogEntry((string)$query . "\n" . implode(',', $regs), __METHOD__);
		return $regs;
	}

}
