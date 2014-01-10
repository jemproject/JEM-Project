<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die();

jimport('joomla.application.component.modelitem');

/**
 * JEM Component Event Model
 * 
 * @package JEM
 */
class JEMModelEvent extends JModelItem
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
	 * 
	 * @todo: alter badcats
	 * 
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
										// If badcats is not null, this means
										// that the event is inside an
										// unpublished category
										// In this case, the state is set to 0
										// to indicate Unpublished (even if the
										// event state is Published)
										'a.created, a.unregistra, a.published, a.created_by, ' .
										// use created if modified is 0
										'CASE WHEN a.modified = 0 THEN a.created ELSE a.modified END as modified, ' . 'a.modified_by, a.checked_out, a.checked_out_time, ' . 'a.datimage,  a.version, ' .
										 'a.meta_keywords, a.created_by_alias, a.introtext, a.fulltext, a.maxplaces, a.waitinglist, a.meta_description, a.hits, a.language'));
				$query->from('#__jem_events AS a');
				
				$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
				
				// Join on category table.
				$query->select('c.catname AS category_title, c.id AS catid, c.alias AS category_alias, c.access AS category_access');
				$query->join('LEFT', '#__jem_categories AS c on c.id = rel.catid');
				
				// Join on user table.
				$name = $settings->get('global_regname','1') ? 'u.name' : 'u.username';
				$query->select($name.' AS author');
				$query->join('LEFT', '#__users AS u on u.id = a.created_by');
				
				// Join on contact-user table.
				$query->select('con.id AS conid, con.name AS conname, con.telephone AS contelephone, con.email_to AS conemail');
				$query->join('LEFT', '#__contact_details AS con ON con.id = a.contactid');
				
				// Join on user table.
				$query->select(
						'l.custom1 AS venue1, l.alias, l.custom2 AS venue2, l.custom3 AS venue3, l.custom4 AS venue4, l.custom5 AS venue5, l.custom6 AS venue6, l.custom7 AS venue7, l.custom8 AS venue8, l.custom9 AS venue9, l.custom10 AS venue10, l.id AS locid, l.alias AS localias, l.venue, l.city, l.state, l.url, l.locdescription, l.locimage, l.city, l.postalCode, l.street, l.country, l.map, l.created_by AS venueowner, l.latitude, l.longitude');
				$query->join('LEFT', '#__jem_venues AS l ON a.locid = l.id');
				
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
				if ($this->getState('filter.language')) {
					$query->where('a.language in (' . $db->quote(JFactory::getLanguage()->getTag()) . ',' . $db->quote('*') . ')');
				}
				
				// Join over the categories to get parent category titles
				$query->select('parent.catname as parent_title, parent.id as parent_id, parent.path as parent_route, parent.alias as parent_alias');
				$query->join('LEFT', '#__jem_categories as parent ON parent.id = c.parent_id');
	
				$query->where('a.id = ' . (int) $pk);
				
				// Filter by start and end dates.
				$nullDate = $db->Quote($db->getNullDate());
				$date = JFactory::getDate();
				
				$nowDate = $db->Quote($date->toSql());
								
				$subquery = ' (SELECT cat.id as id FROM #__jem_categories AS cat JOIN #__jem_categories AS parent ';
				$subquery .= 'ON cat.lft BETWEEN parent.lft AND parent.rgt ';
				$subquery .= 'WHERE parent.published <= 0 GROUP BY cat.id)';
				$query->join('LEFT OUTER', $subquery . ' AS badcats ON badcats.id = c.id');
				
				// Filter by published state.
				$published = $this->getState('filter.published');
				
				$archived = $this->getState('filter.archived');
				
				if (is_numeric($published)) {
					$query->where('(a.published = ' . (int) $published . ' OR a.published =' . (int) $archived . ')');
				}
				
				//$query->group('a.id');
				//echo $query;
				$db->setQuery($query);
				
				$data = $db->loadObject();
				
				
				if ($error = $db->getErrorMsg()) {
					throw new Exception($error);
				}
				
				if (empty($data)) {
					return JError::raiseError(404, JText::_('COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND'));
				}
				
				// Check for published state if filter set.
				
				/*
				if (((is_numeric($published)) || (is_numeric($archived))) && (($data->published != $published) && ($data->published != $archived))) {
					return JError::raiseError(404, JText::_('COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND'));
				}
				*/
				
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
				if ($access = $this->getState('filter.access')) {
					// If the access filter has been set, we already know this
					// user can view.
					$data->params->set('access-view', true);
				}
				else {
					// If no access filter is set, the layout takes some
					// responsibility for display of limited information.
					$user = JFactory::getUser();
					$groups = $user->getAuthorisedViewLevels();
					
					if ($data->catid == 0 || $data->category_access === null)
				 {
					 $data->params->set('access-view', in_array($data->access,
				 $groups));
					 }
					 else {
					 $data->params->set('access-view', in_array($data->access,
				 $groups) && in_array($data->category_access, $groups));
					}
				}
				
				
				$this->_item[$pk] = $data;
			}
			catch (JException $e) {
				if ($e->getCode() == 404) {
					// Need to go thru the error handler to allow Redirect to
					// work.
					JError::raiseError(404, $e->getMessage());
				}
				else {
					$this->setError($e);
					$this->_item[$pk] = false;
				}
			}
		}
		
		// Define Attachments
		$user = JFactory::getUser();
			
		$this->_item[$pk]->attachments = JEMAttachment::getAttachments('event' . $this->_item[$pk]->did);
			
		// Define Booked
		$db = $this->getDbo();
		$query = $db->getQuery(true);
		$query->select(array(
				'COUNT(*)'
		));
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
	 * @param int		Optional primary key of the article to increment.   
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
	 * Method to get the categories
	 *
	 * @access public
	 * @return object
	 *
	 */
	function getCategories($pk = 0)
	{
		$user = JFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();
		
		$pk = (!empty($pk)) ? $pk : (int) $this->getState('event.id');
		
		$query = 'SELECT DISTINCT c.id, c.catname,' 
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as slug' 
				. ' FROM #__jem_categories AS c' 
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id' 
				. ' WHERE rel.itemid = ' . (int) $pk 
				. '  AND c.published = 1' . ' AND c.access IN (' . implode(',', $levels) . ')';
		$this->_db->setQuery($query);
		
		$this->_cats = $this->_db->loadObjectList();
		
		return $this->_cats;
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
		. ' AND event = ' . $this->getState('event.id');
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
		// avatars should be displayed
		$settings = JEMHelper::globalattribs();
	
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
				. ' WHERE event = '. $event
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
	
		$event = (int) $this->_registerid;
	
		$uid = (int) $user->get('id');
		$onwaiting = 0;
	
		// Must be logged in
		if ($uid < 1) {
			JError::raiseError(403, JText::_('COM_JEM_ALERTNOTAUTH'));
			return;
		}
	
		$this->setId($event);
	
		$event2 = $this->getItem($pk = $this->_registerid);
	
		if ($event2->maxplaces > 0) 		// there is a max
		{		
			// check if the user should go on waiting list
			$attendees = self::getRegisters($event);
			if (count($attendees) >= $event2->maxplaces) {
				if (!$event2->waitinglist) {
					$this->setError(JText::_('COM_JEM_ERROR_REGISTER_EVENT_IS_FULL'));
					return false;
				}
				$onwaiting = 1;
			}
		}
	
		// IP
		$uip = $jemsettings->storeip ? getenv('REMOTE_ADDR') : 'DISABLED';
	
		$obj = new stdClass();
		$obj->event = (int) $event;
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
	
		$event = (int) $this->_registerid;		
		$userid = $user->get('id');
	
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