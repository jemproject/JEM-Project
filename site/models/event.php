<?php
/**
 * @version 2.0.2
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die();

jimport('joomla.application.component.modelitem');

/**
 * Event-Model
 */
class JemModelEvent extends JModelItem
{

	/**
	 * Model context string.
	 *
	 * @var string
	 */
	protected $_context = 'com_jem.event';

	var $_registers = null;

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 */
	protected function populateState()
	{
		$app = JFactory::getApplication('site');

		// Load state from the request.
		$pk = JRequest::getInt('id');
		$this->setState('event.id', $pk);

		$offset = JRequest::getUInt('limitstart');
		$this->setState('list.offset', $offset);

		// Load the parameters.
		$params = $app->getParams('com_jem');
		$this->setState('params', $params);

		// TODO: Tune these values based on other permissions.
		$user = JFactory::getUser();
		if ((!$user->authorise('core.edit.state', 'com_jem')) && (!$user->authorise('core.edit', 'com_jem'))) {
			$this->setState('filter.published', 1);
			$this->setState('filter.archived', 2);
		}

		$this->setState('filter.language', JLanguageMultilang::isEnabled());
	}

	/**
	 * Method to get event data.
	 *
	 * @param integer	The id of the event.
	 * @return mixed item data object on success, false on failure.
	 */
	public function &getItem($pk = null)
	{
		// Initialise variables.
		$pk = (!empty($pk)) ? $pk : (int) $this->getState('event.id');

		if ($this->_item === null) {
			$this->_item = array();
		}

		if (!isset($this->_item[$pk])) {

			try {
				$settings = JEMHelper::globalattribs();

				$db = $this->getDbo();
				$query = $db->getQuery(true);

				$query->select(
						$this->getState('item.select',
								'a.id, a.access, a.attribs, a.metadata, a.registra, a.custom1, a.custom2, a.custom3, a.custom4, a.custom5, a.custom6, a.custom7, a.custom8, a.custom9, a.custom10, a.times, a.endtimes, a.dates, a.enddates, a.id AS did, a.title, a.alias, ' .
										'a.created, a.unregistra, a.published, a.created_by, ' .
										'CASE WHEN a.modified = 0 THEN a.created ELSE a.modified END as modified, ' . 'a.modified_by, a.checked_out, a.checked_out_time, ' . 'a.datimage,  a.version, ' .
										'a.meta_keywords, a.created_by_alias, a.introtext, a.fulltext, a.maxplaces, a.waitinglist, a.meta_description, a.hits, a.language, ' .
										'a.recurrence_type, a.recurrence_first_id'));
				$query->from('#__jem_events AS a');

				// Join on user table.
				$name = $settings->get('global_regname','1') ? 'u.name' : 'u.username';
				$query->select($name.' AS author');
				$query->join('LEFT', '#__users AS u on u.id = a.created_by');

				// Join on contact-user table.
				$query->select('con.id AS conid, con.name AS conname, con.telephone AS contelephone, con.email_to AS conemail');
				$query->join('LEFT', '#__contact_details AS con ON con.id = a.contactid');

				// Join on venue table.
				$query->select('l.custom1 AS venue1, l.custom2 AS venue2, l.custom3 AS venue3, l.custom4 AS venue4, l.custom5 AS venue5, l.custom6 AS venue6, l.custom7 AS venue7, l.custom8 AS venue8, l.custom9 AS venue9, l.custom10 AS venue10, ' .
				               'l.id AS locid, l.alias AS localias, l.venue, l.city, l.state, l.url, l.locdescription, l.locimage, l.city, l.postalCode, l.street, l.country, l.map, l.created_by AS venueowner, l.latitude, l.longitude, l.checked_out AS vChecked_out, l.checked_out_time AS vChecked_out_time');
				$query->join('LEFT', '#__jem_venues AS l ON a.locid = l.id');

				# join over the category-tables
				$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
				$query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');

				// Get contact id
				$subQuery = $db->getQuery(true);
				$subQuery->select('MAX(contact.id) AS id');
				$subQuery->from('#__contact_details AS contact');
				$subQuery->where('contact.published = 1');
				$subQuery->where('contact.user_id = a.created_by');

				// Filter by language
				if ($this->getState('filter.language')) {
					$subQuery->where('(contact.language in (' . $db->quote(JFactory::getLanguage()->getTag()) . ',' . $db->quote('*') . ') OR contact.language IS NULL)');
				}

				$query->select('(' . $subQuery . ') as contactid2');

				// Filter by language
				/* commented out yet because it's incomplete
				if ($this->getState('filter.language')) {
					$query->where('a.language in (' . $db->quote(JFactory::getLanguage()->getTag()) . ',' . $db->quote('*') . ')');
				}
				*/

				$query->where('a.id = ' . (int) $pk);

				// Filter by start and end dates.
				$nullDate = $db->Quote($db->getNullDate());
				$date = JFactory::getDate();

				$nowDate = $db->Quote($date->toSql());


				// Filter by published state.
				$published = $this->getState('filter.published');

				$archived = $this->getState('filter.archived');

				/** @todo: Is that correct? What's about $archived? Could it wrongly be 0 (=unpublished)? */
				if (is_numeric($published)) {
					$query->where('(a.published = ' . (int) $published . ' OR a.published =' . (int) $archived . ')');
				}


				#####################
				### FILTER - BYCAT ##
				#####################

				$cats = $this->getCategories('all');
				if (!empty($cats)) {
					$query->where('c.id  IN (' . implode(',', $cats) . ')');
				}

				//$query->group('a.id');
				$db->setQuery($query);
				$data = $db->loadObject();

				if ($error = $db->getErrorMsg()) {
					throw new Exception($error);
				}

				if (empty($data)) {
					throw new Exception(JText::_('COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND'), 404);
				}

				// Convert parameter fields to objects.
				$registry = new JRegistry;
				$registry->loadString($data->attribs);

				$globalattribs = JEMHelper::globalattribs();
				$globalregistry = new JRegistry;
				$globalregistry->loadString($globalattribs);

				$data->params = clone $globalregistry;
				$data->params->merge($registry);

				$registry = new JRegistry;
				$registry->loadString($data->metadata);
				$data->metadata = $registry;

				// Compute selected asset permissions.
				$user = JFactory::getUser();
				$groups = $user->getAuthorisedViewLevels();

				// Technically guest could edit an event, but lets not check
				// that to improve performance a little.
				if (!$user->get('guest')) {
					$userId = $user->get('id');
					$asset = 'com_jem.event.' . $data->id;

					// Check general edit permission first.
					if ($user->authorise('core.edit', $asset)) {
						$data->params->set('access-edit', true);
					}
					// Now check if edit.own is available.
					elseif (!empty($userId) && $user->authorise('core.edit.own', $asset)) {
						// Check for a valid user and that they are the owner.
						if ($userId == $data->created_by) {
							$data->params->set('access-edit', true);
						}
					}
				}

				// Compute view access permissions.

				# retrieve category's that the user is able to see
				# if there is no category the event should not be displayed

				$category_viewable = $this->getCategories($pk);

				if (!empty($category_viewable)) {
					// Event's access value must also be checked
					$data->params->set('access-view', in_array($data->access, $groups));
				}

				$this->_item[$pk] = $data;
			}
			catch (JException $e) {
				if ($e->getCode() == 404) {
					// Need to go thru the error handler to allow Redirect to
					// work.
					JError::raiseError(404, $e->getMessage());
					return false;
				}
				else {
					$this->setError($e);
					$this->_item[$pk] = false;
					return false;
				}
			}
		}

		// Define Attachments
		$user = JFactory::getUser();
		$this->_item[$pk]->attachments = JEMAttachment::getAttachments('event' . $this->_item[$pk]->did);

		// Define Venue-Attachments
		$this->_item[$pk]->vattachments = JEMAttachment::getAttachments('venue' . $this->_item[$pk]->locid);

		// Define Booked
		$db = $this->getDbo();
		$query = $db->getQuery(true);
		$query->select(array('COUNT(*)'));
		$query->from('#__jem_register');
		$query->where(array(
				'event= ' . $db->quote($this->_item[$pk]->did),
				'waiting= 0'
		));
		$db->setQuery($query);
		$res = $db->loadResult();
		$this->_item[$pk]->booked = $res;


		return $this->_item[$pk];
	}

	/**
	 * Increment the hit counter for the event.
	 *
	 * @param int		Optional primary key of the event to increment.
	 * @return boolean if successful; false otherwise and internal error set.
	 */
	public function hit($pk = 0)
	{
		$hitcount = JRequest::getInt('hitcount', 1);

		if ($hitcount) {
			// Initialise variables.
			$pk = (!empty($pk)) ? $pk : (int) $this->getState('event.id');
			$db = $this->getDbo();

			$db->setQuery('UPDATE #__jem_events' . ' SET hits = hits + 1' . ' WHERE id = ' . (int) $pk);

			if (!$db->query()) {
				$this->setError($db->getErrorMsg());
				return false;
			}
		}

		return true;
	}


	/**
	 * Retrieve Categories
	 *
	 * Due to multi-cat this function is needed
	 * filter-index (4) is pointing to the cats
	 */

	function getCategories($id = 0)
	{

		$id = (!empty($id)) ? $id : (int) $this->getState('event.id');

		$user 			= JFactory::getUser();
		$userid			= (int) $user->get('id');
		$levels 		= $user->getAuthorisedViewLevels();
		$app 			= JFactory::getApplication();
		$params 		= $app->getParams();
		$catswitch 		= $params->get('categoryswitch', '0');
		$settings 		= JemHelper::globalattribs();

		// Query
		$db 	= JFactory::getDBO();
		$query = $db->getQuery(true);

		$case_when_c = ' CASE WHEN ';
		$case_when_c .= $query->charLength('c.alias');
		$case_when_c .= ' THEN ';
		$id_c = $query->castAsChar('c.id');
		$case_when_c .= $query->concatenate(array($id_c, 'c.alias'), ':');
		$case_when_c .= ' ELSE ';
		$case_when_c .= $id_c.' END as catslug';

		$query->select(array('DISTINCT c.id','c.catname','c.access','c.checked_out AS cchecked_out','c.color',$case_when_c));
		$query->from('#__jem_categories as c');
		$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.catid = c.id');

		$query->select(array('a.id AS multi'));
		$query->join('LEFT','#__jem_events AS a ON a.id = rel.itemid');

		if ($id != 'all'){
			$query->where('rel.itemid ='.(int)$id);
		}

		$query->where('c.published = 1');


		###################################
		## FILTER - MAINTAINER/JEM GROUP ##
		###################################

		# as maintainter someone who is registered can see a category that has special rights
		# let's see if the user has access to this category.


	//	$query3	= $db->getQuery(true);
	//	$query3 = 'SELECT gr.id'
	//			. ' FROM #__jem_groups AS gr'
	//			. ' LEFT JOIN #__jem_groupmembers AS g ON g.group_id = gr.id'
	//			. ' WHERE g.member = ' . (int) $user->get('id')
	//			//. ' AND ' .$db->quoteName('gr.addevent') . ' = 1 '
	//			. ' AND g.member NOT LIKE 0';
	//	$db->setQuery($query3);
	//	$groupnumber = $db->loadColumn();

		$groups = implode(',', $levels);
	//	$jemgroups = implode(',',$groupnumber);

	//	if ($jemgroups) {
	//		$query->where('(c.access IN ('.$groups.') OR c.groupid IN ('.$jemgroups.'))');
	//	} else {
			$query->where('(c.access IN ('.$groups.'))');
	//	}


		#######################
		## FILTER - CATEGORY ##
		#######################

		# set filter for top_category
		$top_cat = $this->getState('filter.category_top');

		if ($top_cat) {
			$query->where($top_cat);
		}

		# filter set by day-view
		$requestCategoryId = (int)$this->getState('filter.req_catid');

		if ($requestCategoryId) {
			$query->where('c.id = '.$requestCategoryId);
		}

		# Filter by a single or group of categories.
		$categoryId = $this->getState('filter.category_id');

		if (is_numeric($categoryId)) {
			$type = $this->getState('filter.category_id.include', true) ? '= ' : '<> ';
			$query->where('c.id '.$type.(int) $categoryId);
		}
		elseif (is_array($categoryId) && count($categoryId)) {
			JArrayHelper::toInteger($categoryId);
			$categoryId = implode(',', $categoryId);
			$type = $this->getState('filter.category_id.include', true) ? 'IN' : 'NOT IN';
			$query->where('c.id '.$type.' ('.$categoryId.')');
		}

		###################
		## FILTER-SEARCH ##
		###################

		# define variables
		$filter = $this->getState('filter.filter_type');
		$search = $this->getState('filter.filter_search'); // not escaped

		if (!empty($search)) {
			if (stripos($search, 'id:') === 0) {
				$query->where('c.id = '.(int) substr($search, 3));
			} else {
				$search = $db->Quote('%'.$db->escape($search, true).'%', false); // escape once

				if($search && $settings->get('global_show_filter')) {
					if ($filter == 4) {
							$query->where('c.catname LIKE '.$search);
					}
				}
			}
		}

		$db->setQuery($query);

		if ($id == 'all'){
			$cats = $db->loadColumn(0);
			$cats = array_unique($cats);
		} else {
			$cats = $db->loadObjectList();
		}
		return $cats;
	}

	/**
	 * Method to check if the user is already registered
	 * return false if not registered, 1 for registerd, 2 for waiting list
	 *
	 * @access public
	 * @return mixed false if not registered, 1 for registerd, 2 for waiting
	 *         list
	 *
	 */
	function getUserIsRegistered()
	{
		// Initialize variables
		$user = JFactory::getUser();
		$userid = (int) $user->get('id', 0);

		// usercheck
		$query = 'SELECT waiting+1' . 		// 1 if user is registered, 2 if on waiting
				// list
		' FROM #__jem_register'
		. ' WHERE uid = ' . $userid
		. ' AND event = ' . $this->_db->quote($this->getState('event.id'));
		$this->_db->setQuery($query);
		return $this->_db->loadResult();
	}

	/**
	 * Method to get the registered users
	 *
	 * @access public
	 * @return object
	 *
	 */
	function getRegisters($event = false)
	{
		if (empty($event)) {
			return false;
		}

		// avatars should be displayed
		$settings = JEMHelper::globalattribs();
		$user     = JFactory::getUser();

		switch ($settings->get('event_show_attendeenames', 2)) {
			case 0: // show to none
			default:
				return false;
			case 1: // show to admins
				if (!$user->authorise('core.manage', 'com_jem')) {
					return false;
				}
				break;
			case 2: // show to registered
				if ($user->get('guest')) {
					return false;
				}
				break;
			case 3: // show to all
				break;
		}

		$avatar = '';
		$join = '';

		if ($settings->get('event_comunoption','0') == 1 && $settings->get('event_comunsolution','0') == 1) {
			$avatar = ', c.avatar';
			$join = ' LEFT JOIN #__comprofiler as c ON c.user_id = r.uid';
		}

		$name = $settings->get('global_regname','1') ? 'u.name' : 'u.username';

		// Get registered users
		$db = $this->getDbo();
		$query = $db->getQuery(true);
		$query = 'SELECT '
				. $name . ' AS name, r.uid' . $avatar
				. ' FROM #__jem_register AS r'
				. ' LEFT JOIN #__users AS u ON u.id = r.uid'
				. $join
				. ' WHERE event = '. $db->quote($event)
				. '   AND waiting = 0 ';
		$db->setQuery($query);

		$registered = $db->loadObjectList();

		return $registered;
	}


	function setId($id)
	{
		// Set new event ID and wipe data
		$this->_registerid = $id;
	}

	/**
	 * Saves the registration to the database
	 *
	 * @access public
	 * @return int register id on success, else false
	 *
	 */
	function userregister()
	{
		$user = JFactory::getUser();
		$jemsettings = JEMHelper::config();

		$eventId = (int) $this->_registerid;

		$uid = (int) $user->get('id');
		$onwaiting = 0;

		// Must be logged in
		if ($uid < 1) {
			JError::raiseError(403, JText::_('COM_JEM_ALERTNOTAUTH'));
			return;
		}

		$this->setId($eventId);

		try {
			$event = $this->getItem($eventId);
		}
		// some gently error handling
		catch (Exception $e) {
			$event = false;
		}
		if (empty($event)) {
			$this->setError(JText::_('COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND'));
			return false;
		}

		if ($event->maxplaces > 0) 		// there is a max
		{
			// check if the user should go on waiting list
			if ($event->booked >= $event->maxplaces) {
				if (!$event->waitinglist) {
					$this->setError(JText::_('COM_JEM_EVENT_FULL_NOTICE'));
					return false;
				}
				$onwaiting = 1;
			}
		}

		// IP
		$uip = $jemsettings->storeip ? JemHelper::retrieveIP() : false;

		$obj = new stdClass();
		$obj->event = (int) $eventId;
		$obj->waiting = $onwaiting;
		$obj->uid = (int) $uid;
		$obj->uregdate = gmdate('Y-m-d H:i:s');
		$obj->uip = $uip;
		$this->_db->insertObject('#__jem_register', $obj);

		return $this->_db->insertid();
	}

	/**
	 * Deletes a registered user
	 *
	 * @access public
	 * @return true on success
	 *
	 */
	function delreguser()
	{
		$user = JFactory::getUser();

		$event  = (int)$this->_registerid;
		$userid = (int)$user->get('id');

		// Must be logged in
		if ($userid < 1) {
			JError::raiseError(403, JText::_('COM_JEM_ALERTNOTAUTH'));
			return;
		}

		$query = 'DELETE FROM #__jem_register WHERE event = ' . $event . ' AND uid= ' . $userid;
		$this->_db->SetQuery($query);

		if (!$this->_db->query()) {
			JError::raiseError(500, $this->_db->getErrorMsg());
		}

		return true;
	}
}
?>
