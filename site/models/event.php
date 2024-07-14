<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;

/**
 * Event-Model
 */
class JemModelEvent extends ItemModel
{
	/**
	 * Model context string.
	 *
	 * @var string
	 */
	protected $_context = 'com_jem.event';

	protected $_registers = null;

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 */
	protected function populateState()
	{
		$app = Factory::getApplication('site');

		// Load state from the request.
		$pk = $app->input->getInt('id', 0);
		$this->setState('event.id', $pk);

		$offset = $app->input->getInt('limitstart', 0);
		$this->setState('list.offset', $offset);

		// Load the parameters.
		$params = $app->getParams('com_jem');
		$this->setState('params', $params);

		$this->setState('filter.language', Multilanguage::isEnabled());
	}

	/**
	 * Method to get event data.
	 *
	 * @param  int  The id of the event.
	 * @return mixed  item data object on success, false on failure.
	 */
	public function getItem($pk = null)
	{
		// Initialise variables.
		$pk = (!empty($pk)) ? $pk : (int) $this->getState('event.id');

		if ($this->_item === null) {
			$this->_item = array();
		}

		if (!isset($this->_item[$pk]))
		{
			try
			{
				$settings = JemHelper::globalattribs();
				$user     = JemFactory::getUser();
				$levels   = $user->getAuthorisedViewLevels();

				$db    = Factory::getContainer()->get('DatabaseDriver');
				$query = $db->getQuery(true);

				# Event
				$query->select(
						$this->getState('item.select',
						                'a.id, a.id AS did, a.title, a.alias, a.dates, a.enddates, a.times, a.endtimes, a.access, a.attribs, a.metadata, ' .
						                'a.custom1, a.custom2, a.custom3, a.custom4, a.custom5, a.custom6, a.custom7, a.custom8, a.custom9, a.custom10, ' .
						                'a.created, a.created_by, a.published, a.registra, a.unregistra, a.unregistra_until, ' .
						                'CASE WHEN a.modified = 0 THEN a.created ELSE a.modified END as modified, a.modified_by, ' .
						                'a.checked_out, a.checked_out_time, a.datimage,  a.version, a.featured, ' .
						                'a.meta_keywords, a.meta_description, a.created_by_alias, a.introtext, a.fulltext, a.maxplaces, a.reservedplaces, a.minbookeduser, a.maxbookeduser, a.waitinglist, a.requestanswer, ' .
						                'a.hits, a.language, a.recurrence_type, a.recurrence_first_id'));
				$query->from('#__jem_events AS a');

				# Author
				$name = $settings->get('global_regname','1') ? 'u.name' : 'u.username';
				$query->select($name.' AS author');
				$query->join('LEFT', '#__users AS u on u.id = a.created_by');

				# Contact
				$query->select('con.id AS conid, con.name AS conname, con.telephone AS contelephone, con.email_to AS conemail');
				$query->join('LEFT', '#__contact_details AS con ON con.id = a.contactid');

				# Venue
				$query->select('l.custom1 AS venue1, l.custom2 AS venue2, l.custom3 AS venue3, l.custom4 AS venue4, l.custom5 AS venue5, ' .
				               'l.custom6 AS venue6, l.custom7 AS venue7, l.custom8 AS venue8, l.custom9 AS venue9, l.custom10 AS venue10, ' .
				               'l.id AS locid, l.alias AS localias, l.venue, l.city, l.state, l.url, l.locdescription, l.locimage, ' .
				               'l.postalCode, l.street, l.country, l.map, l.created_by AS venueowner, l.latitude, l.longitude, ' .
				               'l.checked_out AS vChecked_out, l.checked_out_time AS vChecked_out_time, l.published as locpublished');
				$query->join('LEFT', '#__jem_venues AS l ON a.locid = l.id');

				# Join over the category tables
				$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.itemid = a.id');
				$query->join('LEFT', '#__jem_categories AS c ON c.id = rel.catid');

				# Get contact id
				$subQuery = $db->getQuery(true);
				$subQuery->select('MAX(contact.id) AS id');
				$subQuery->from('#__contact_details AS contact');
				$subQuery->where('contact.published = 1');
				$subQuery->where('contact.user_id = a.created_by');

				# Filter contact by language
				if ($this->getState('filter.language')) {
					$subQuery->where('(contact.language in (' . $db->quote(Factory::getApplication()->getLanguage()->getTag()) . ',' . $db->quote('*') . ') OR contact.language IS NULL)');
				}

				$query->select('(' . $subQuery . ') as contactid2');

				# Filter event by language
				/* commented out yet because it's incomplete
				if ($this->getState('filter.language')) {
					$query->where('a.language in (' . $db->quote(Factory::getApplication()->getLanguage()->getTag()) . ',' . $db->quote('*') . ')');
				}
				*/

				$query->where('a.id = ' . (int) $pk);

				# Filter by published state ==> later.
				//  It would result in too complicated query.
				//  It's easier to get data and check then e.g. for event owner etc.

				# Filter by categories
				$cats = $this->getCategories('all');
				if (!empty($cats)) {
					$query->where('c.id  IN (' . implode(',', $cats) . ')');
				}

				# Get the item
				//$query->group('a.id');
				
				// if ($error = $db->getErrorMsg()) {
				// 	throw new Exception($error);
				// }
				try
				{
					$db->setQuery($query);
					$data = $db->loadObject();

				}
				catch (RuntimeException $e)
				{			
					Factory::getApplication()->enqueueMessage($e->getMessage(), 'notice');
				}

				if (empty($data)) {
					throw new Exception(Text::_('COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND'), 404);
				}

				# Convert parameter fields to objects.
				$registry = new JRegistry;
				$registry->loadString($data->attribs);
				$data->params = JemHelper::globalattribs(); // returns JRegistry object
				$data->params->merge($registry);

				$registry = new JRegistry;
				$registry->loadString($data->metadata);
				$data->metadata = $registry;

				$data->categories = $this->getCategories($pk);

				# Compute selected asset permissions.
				$access_edit = $user->can('edit', 'event', $data->id, $data->created_by);
				$access_view = (($data->published == 1) || ($data->published == 2) ||          // published and archived event
				                (($data->published == 0) && $access_edit) ||                   // unpublished for editors,
				                $user->can('publish', 'event', $data->id, $data->created_by)); // all for publishers

				$data->params->set('access-edit', $access_edit);

				# Compute view access permissions.

				# event can be shown if
				#  - user has matching view access level and
				#  - there is at least one category attached user can see and
				#  - publishing state and user permissions allow that (e.g. unpublished event but user is editor, owner, or publisher)
				$data->params->set('access-view', $access_view && !empty($data->categories) && in_array($data->access, $levels));

				$this->_item[$pk] = $data;
			}
			catch (Exception $e)
			{
				if ($e->getCode() == 404) {
					// Need to go thru the error handler to allow Redirect to
					// work.
					Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
					return false;
				}
				else {
					$this->setError($e);
					$this->_item[$pk] = false;
					return false;
				}
			}
		}

		# Get event attachments
		$this->_item[$pk]->attachments = JemAttachment::getAttachments('event' . $this->_item[$pk]->did);

		# Get venue attachments
		$this->_item[$pk]->vattachments = JemAttachment::getAttachments('venue' . $this->_item[$pk]->locid);

		// Define Booked
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select('SUM(places)');
		$query->from('#__jem_register');
		$query->where(array('event = ' . $db->quote($this->_item[$pk]->did), 'waiting = 0', 'status = 1'));
		$db->setQuery($query);
		try {
			$res = $db->loadResult();
		}
		catch (Exception $e) {
			$res = 0;
		}
		$this->_item[$pk]->booked = $res;

		return $this->_item[$pk];
	}

	/**
	 * Increment the hit counter for the event.
	 *
	 * @param  int  Optional primary key of the event to increment.
	 * @return boolean  if successful; false otherwise and internal error set.
	 */
	public function hit($pk = 0)
	{
		$hitcount = Factory::getApplication()->input->getInt('hitcount', 1);

		if ($hitcount) {
			// Initialise variables.
			$pk = (!empty($pk)) ? $pk : (int) $this->getState('event.id');
			$db = Factory::getContainer()->get('DatabaseDriver');

			$db->setQuery('UPDATE #__jem_events' . ' SET hits = hits + 1' . ' WHERE id = ' . (int) $pk);

			try {
				if ($db->execute() === false) {
					$this->setError($db->getErrorMsg());
					return false;
				}
			}
			catch (Exception $e) {
				$this->setError($e);
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
	public function getCategories($id = 0)
	{
		$id = (!empty($id)) ? $id : (int) $this->getState('event.id');

		$user      = JemFactory::getUser();
	//	$userid    = (int)$user->get('id');
		$levels    = $user->getAuthorisedViewLevels();
	//	$app       = Factory::getApplication();
	//	$params    = $app->getParams();
	//	$catswitch = $params->get('categoryswitch', '0');
		$settings  = JemHelper::globalattribs();

		// Query
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		$case_when_c  = ' CASE WHEN ';
		$case_when_c .= $query->charLength('c.alias');
		$case_when_c .= ' THEN ';
		$id_c = $query->castAsChar('c.id');
		$case_when_c .= $query->concatenate(array($id_c, 'c.alias'), ':');
		$case_when_c .= ' ELSE ';
		$case_when_c .= $id_c.' END as catslug';

		$query->select(array('DISTINCT c.id','c.catname','c.access','c.checked_out AS cchecked_out','c.color',$case_when_c,'c.groupid'));
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
			\Joomla\Utilities\ArrayHelper::toInteger($categoryId);
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

		try {
			if ($id == 'all'){
				$cats = $db->loadColumn(0);
				$cats = array_unique($cats);
			} else {
				$cats = $db->loadObjectList();
			}
		}
		catch (Exception $e) {
			$cats = false;
		}

		return $cats;
	}

	/**
	 * Method to get user registration data if available.
	 *
	 * @access public
	 * @return mixed false if not registered, object with id, status, comment else
	 *               status -1 if not attending, 0 if invited,
	 *               1 if attending, 2 if on waiting list
	 */
	public function getUserRegistration($eventId = null, $userid = 0)
	{
		// Initialize variables
		$userid = (int)$userid;
		if (empty($userid)) {
			$user = JemFactory::getUser();
			$userid = (int) $user->get('id', 0);
		}

		$eventId = (int)$eventId;
		if (empty($eventId)) {
			$eventId = $this->getState('event.id');
		}

		// usercheck
		// -1 if user will not attend, 0 if invened/unknown, 1 if registeredm 2 if on waiting list
		$query = 'SELECT IF (status > 0, waiting + 1, status) AS status, id, comment, places'
		       . ' FROM #__jem_register'
		       . ' WHERE uid = ' . $this->_db->quote($userid)
		       . ' AND event = ' . $this->_db->quote($eventId);
		$this->_db->setQuery($query);

		try {
			$result = $this->_db->loadObject();
		}
		catch (Exception $e) {
			$result = false;
		}

		return $result;
	}

	/**
	 * Method to check if the user is already registered
	 *
	 * @access public
	 * @return mixed false if not registered, -1 if not attending,
	 *               0 if invited, 1 if attending, 2 if on waiting list
	 */
	public function getUserIsRegistered($eventId = null)
	{
		$obj = $this->getUserRegistration($eventId);
		if (is_object($obj) && isset($obj->status)) {
			return $obj->status;
		} else {
			return false;
		}
	}

	/**
	 * Method to get the registered users
	 *
	 * @access public
	 * @return object
	 */
	public function getRegisters($event = false, $status = 1)
	{
		if (empty($event)) {
			return false;
		}

		// avatars should be displayed
		$settings = JemHelper::globalattribs();
		$user     = JemFactory::getUser();
		$db       = Factory::getContainer()->get('DatabaseDriver');

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
			case 4: // show only to user
				$where[] = 'u.id = ' . $user->id;
				break;
		}

		$avatar = '';
		$join = '';

		if ($settings->get('event_comunoption','0') == 1 && $settings->get('event_comunsolution','0') == 1) {
			$avatar = ', c.avatar';
			$join = ' LEFT JOIN #__comprofiler as c ON c.user_id = r.uid';
		}

		$name = $settings->get('global_regname','1') ? 'u.name' : 'u.username';

		$where[] = 'event = '. $db->quote($event);
		if (is_numeric($status)) {
			if ($status == 2) {
				$where[] = 'waiting = 1';
				$where[] = 'status = 1';
			} else {
				$where[] = 'waiting = 0';
				$where[] = 'status = ' . (int)$status;
			}
		} elseif ($status !== 'all') {
			$where[] = 'waiting = 0';
			$where[] = 'status = 1';
		}

    // Get registered users
    $query = $db->getQuery(true);
    $query = 'SELECT IF(r.status = 1 AND r.waiting = 1, 2, r.status) as status, '
           . $name . ' AS name, r.uid' . $avatar . ', r.comment, r.places'
           . ' FROM #__jem_register AS r'
           . ' LEFT JOIN #__users AS u ON u.id = r.uid'
           . $join
           . ' WHERE ' . implode(' AND ', $where);
    $db->setQuery($query);

		try {
			$registered = $db->loadObjectList();
		}
		catch (Exception $e) {
			$registered = false;
		}

		return $registered;
	}

	public function setId($id)
	{
		// Set new event ID and wipe data
		$this->_registerid = $id;
	}

	/**
	 * Internal helper to store registration on database
	 *
	 * @param  int     $eventId  id of event
	 * @param  int     $uid      id of user to register
	 * @param  mixed   $uip      ip address or false
	 * @param  int     $status   registration status
	 * @param  int     $places   number to add/cancel places of registration
	 * @param  string  $comment  optional comment
	 * @param  string &$errMsg   gets a message in error cases
	 * @param  int     $regid    id of registration record to change or 0 if new (default)
	 * @param  bool    $respectPlaces  if true adapt status/waiting depending on event's free places,
	 *                           may return error if no free places and no waiting list
	 *
	 * @access protected
	 * @return int register id on success, else false
	 */
	protected function _doRegister($eventId, $uid, $uip, $status, $places, $comment, &$errMsg, $regid = 0, $respectPlaces = true)
	{
	//	$app = Factory::getApplication('site');
	//	$user = JemFactory::getUser();
	//	$jemsettings = JemHelper::config();
		$registration = (empty($uid) || empty($eventId)) ? false : $this->getUserRegistration($eventId, $uid);
		$onwaiting = 0;

		try {
			$event = $this->getItem($eventId);
		}
		// some gently error handling
		catch (Exception $e) {
			$event = false;
		}

		if (empty($event)) {
			$errMsg = Text::_('COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND');
			return false;
		}

		$oldstat = is_object($registration) ? $registration->status : 0;
		if ($status == 1 && $status != $oldstat) {
			if ($respectPlaces && ($event->maxplaces > 0)) {	// there is a max
				// check if the user should go on waiting list
				if ($event->booked >= $event->maxplaces) {
					if (!$event->waitinglist) {
						$this->setError(Text::_('COM_JEM_EVENT_FULL_NOTICE'));
						return false;
					}
					$onwaiting = 1;
				}
			}
		}
		elseif ($status == 2) {
			if ($respectPlaces && !$event->waitinglist) {
				$errMsg = Text::_('COM_JEM_NO_WAITINGLIST');
				return false;
			}
			$onwaiting = 1;
			$status = 1;
		}
		elseif ($respectPlaces && ($oldstat == 1) && ($status == -1) && !$event->unregistra) {
			$errMsg = Text::_('COM_JEM_ERROR_ANNULATION_NOT_ALLOWED');
			return false;
		}

		$obj = new stdClass();
		$obj->event = (int)$eventId;
		$obj->status = (int)$status;
		$obj->places = (int)$places;
		$obj->waiting = $onwaiting;
		$obj->uid = (int)$uid;
		$obj->uregdate = gmdate('Y-m-d H:i:s');
		$obj->uip = $uip;
		$obj->comment = $comment;

		$result = false;
		try {
			if ($regid) {
				$obj->id = $regid;
				$this->_db->updateObject('#__jem_register', $obj, 'id');
				$result = $regid;
			} else {
				$this->_db->insertObject('#__jem_register', $obj);
				$result = $this->_db->insertid();
			}
		}
		catch (Exception $e) {
			// we have a unique user-event key so registering twice will fail
			$errMsg = Text::_(($e->getCode() == 1062) ? 'COM_JEM_ALLREADY_REGISTERED'
				                                       : 'COM_JEM_ERROR_REGISTRATION');
			return false;
		}

		return $result;
	}

	/**
	 * Saves the registration to the database
	 *
	 * @access public
	 * @return int register id on success, else false
	 */
	public function userregister()
	{
		$app = Factory::getApplication('site');
		$user = JemFactory::getUser();
		$jemsettings = JemHelper::config();

		$status  = $app->input->getInt('reg_check', 0);
	//	$noreg   = ($status == -1) ? 'on' : 'off';//$app->input->getString('noreg_check', 'off');
		$comment = $app->input->getString('reg_comment', '');
		$comment = OutputFilter::cleanText($comment);
		$regid   = $app->input->getInt('regid', 0);
		$addplaces = $app->input->getInt('addplaces', 0);
		$cancelplaces = $app->input->getInt('cancelplaces', 0);

		$eventId = (int) $this->_registerid;

		$uid = (int) $user->get('id');
		$reg = $this->getUserRegistration($eventId);
		$errMsg = '';

		try {
			$event = $this->getItem($eventId);
		}
			// some gently error handling
		catch (Exception $e) {
			$event = false;
		}

		if($status>0){
			if($addplaces>0)
			{
				if ($reg)
				{
					if ($reg->status > 0)
					{
						$places = $addplaces + $reg->places;
					}
					else
					{
						$places = $addplaces;
					}
				}
				else
				{
					$places = $addplaces;
				}
				//Detect if the reserve go to waiting list
				$placesavailableevent = $event->maxplaces - $event->reservedplaces - $event->booked;
				if ($reg->status != 0)
				{
					if($event->maxplaces){
						$placesavailableevent = $event->maxplaces - $event->reservedplaces - $event->booked;
						if ($event->waitinglist && $placesavailableevent <= 0) {
							$status = 2;
						}
					}else {
						$status = 1;
					}
				}
			}else{
				$places = 0;
			}
		}else{
			if($reg) {
				$places = $reg->places - $cancelplaces;
				if($reg->status>=0 && $places>0){
					$status=$reg->status;
				}
			}else{
				$places = 0;
			}
		}

		//Review max places per user
		if($event->maxbookeduser){
			if($places > $event->maxbookeduser){
				$places = $event->maxbookeduser;
			}
		}

		// Must be logged in
		if ($uid < 1) {
			Factory::getApplication()->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			return;
		}

		// IP
		$uip = $jemsettings->storeip ? JemHelper::retrieveIP() : false;

		$result = $this->_doRegister($eventId, $uid, $uip, $status, $places, $comment, $errMsg, $regid);
		if (!$result) {
			$this->setError( Text::_('COM_JEM_ERROR_REGISTRATION'));
		}

		return $result;
	}

	/**
	 * Saves the registration to the database
	 *
	 * @param  int     $eventId  id of event
	 * @param  int     $uid      id of user to register
	 * @param  int     $status   registration status
	 * @param  string  $comment  optional comment
	 * @param  string &$errMsg   gets a message in error cases
	 * @param  int     $regid    id of registration record to change or 0 if new (default)
	 * @param  bool    $respectPlaces  if true adapt status/waiting depending on event's free places,
	 *                           may return error if no free places and no waiting list
	 *
	 * @access public
	 * @return int register id on success, else false
	 */
	public function adduser($eventId, $uid, $status, $places, $comment, &$errMsg, $regid = 0, $respectPlaces = true)
	{
	//	$app = Factory::getApplication('site');
		$user = JemFactory::getUser();
		$jemsettings = JemHelper::config();

		// Acting user must be logged in
		if ($user->get('id') < 1) {
			\Joomla\CMS\Factory::getApplication()->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			return false;
		}

		// IP
		$uip = $jemsettings->storeip ? JemHelper::retrieveIP() : false;

		$result = $this->_doRegister($eventId, $uid, $uip, $status, $places, $comment, $errMsg, $regid, $respectPlaces);

		return $result;
	}

	/**
	 * Deletes a registered user
	 *
	 * @access public
	 * @return true on success
	 */
	public function delreguser()
	{
		$user   = JemFactory::getUser();
		$userid = (int)$user->get('id');
		$event  = (int)$this->_registerid;

		// Must be logged in
		if ($userid < 1) {
			Factory::getApplication()->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			return;
		}

		$query = 'DELETE FROM #__jem_register WHERE event = ' . $event . ' AND uid= ' . $userid;
		$this->_db->SetQuery($query);

		if ($this->_db->execute() === false) {
			throw new Exception($this->_db->getErrorMsg(), 500);
		}

		return true;
	}
}
?>
