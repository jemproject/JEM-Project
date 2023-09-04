<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;

require_once __DIR__ . '/admin.php';

/**
 * Event model.
 */
class JemModelEvent extends JemModelAdmin
{
	/**
	 * Method to change the published state of one or more records.
	 *
	 * @param  array   &$pks  A list of the primary keys to change.
	 * @param  integer $value The value of the published state.
	 *
	 * @return boolean True on success.
	 *
	 * @since  2.2.2
	 */
	public function publish(&$pks, $value = 1)
	{
		// Additionally include the JEM plugins for the onContentChangeState event.
		JPluginHelper::importPlugin('jem');

		return parent::publish($pks, $value);
	}

	/**
	 * Method to test whether a record can be deleted.
	 *
	 * @param  object  A record object.
	 * @return boolean True if allowed to delete the record. Defaults to the permission set in the component.
	 */
	protected function canDelete($record)
	{
		$result = false;

		if (!empty($record->id) && ($record->published == -2)) {
			$user = JemFactory::getUser();

			$result = $user->can('delete', 'event', $record->id, $record->created_by, !empty($record->catid) ? $record->catid : false);
		}

		return $result;
	}

	/**
	 * Method to test whether a record can be published/unpublished.
	 *
	 * @param  object  A record object.
	 * @return boolean True if allowed to change the state of the record. Defaults to the permission set in the component.
	 */
	protected function canEditState($record)
	{
		$user = JemFactory::getUser();

		$id    = isset($record->id) ? $record->id : false; // isset ensures 0 !== false
		$owner = !empty($record->created_by) ? $record->created_by : false;
		$cats  = !empty($record->catid) ? array($record->catid) : false;

		return $user->can('publish', 'event', $id, $owner, $cats);
	}

	/**
	 * Returns a reference to the a Table object, always creating it.
	 *
	 * @param  type   The table type to instantiate
	 * @param  string A prefix for the table class name. Optional.
	 * @param  array  Configuration array for model. Optional.
	 * @return Table A database object
	 */
	public function getTable($type = 'Event', $prefix = 'JemTable', $config = array())
	{
		return Table::getInstance($type, $prefix, $config);
	}

	/**
	 * Method to get the record form.
	 *
	 * @param  array   $data     Data for the form.
	 * @param  boolean $loadData True if the form is to load its own data (default case), false if not.
	 * @return mixed   A JForm object on success, false on failure
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Get the form.
		$form = $this->loadForm('com_jem.event', 'event', array('control' => 'jform', 'load_data' => $loadData));
		if (empty($form)) {
			return false;
		}

		return $form;
	}

	/**
	 * Method to get a single record.
	 *
	 * @param  integer The id of the primary key.
	 *
	 * @return mixed   Object on success, false on failure.
	 */
	public function getItem($pk = null)
	{
		$jemsettings = JemAdmin::config();

		if ($item = parent::getItem($pk)){
			// Convert the params field to an array.
			// (this may throw an exception - but there is nothings we can do)
			$registry = new JRegistry;
			$registry->loadString($item->attribs ?? '{}');
			$item->attribs = $registry->toArray();

			// Convert the metadata field to an array.
			$registry = new JRegistry;
			$registry->loadString($item->metadata ?? '{}');
			$item->metadata = $registry->toArray();

			$item->articletext = ($item->fulltext && trim($item->fulltext) != '') ? $item->introtext . "<hr id=\"system-readmore\" />" . $item->fulltext : $item->introtext;

            $db = Factory::getContainer()->get('DatabaseDriver');

			$query = $db->getQuery(true);
			$query->select('SUM(places)');
			$query->from('#__jem_register');
			$query->where(array('event= '.$db->quote($item->id), 'status=1', 'waiting=0'));

			$db->setQuery($query);
			$res = $db->loadResult();
			$item->booked = $res;

			$files = JemAttachment::getAttachments('event'.$item->id);
			$item->attachments = $files;

			if ($item->id){
				// Store current recurrence values
				$item->recurr_bak = new stdClass;
				foreach (get_object_vars($item) as $k => $v) {
					if (strncmp('recurrence_', $k, 11) === 0) {
						$item->recurr_bak->$k = $v;
					}
				}

				$item->recurrence_type       = '';
				$item->recurrence_number     = '';
				$item->recurrence_byday      = '';
				$item->recurrence_counter    = '';
				$item->recurrence_first_id   = '';
				$item->recurrence_limit      = '';
				$item->recurrence_limit_date = '';
			}

			$item->author_ip = $jemsettings->storeip ? JemHelper::retrieveIP() : false;

			if (empty($item->id)){
				$item->country = $jemsettings->defaultCountry;
			}
		}

		return $item;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = Factory::getApplication()->getUserState('com_jem.edit.event.data', array());

		if (empty($data)){
			$data = $this->getItem();
		}

		return $data;
	}

	/**
	 * Prepare and sanitise the table data prior to saving.
	 *
	 * @param  $table Table-object.
	 */
	protected function _prepareTable($table)
	{
		$jinput = Factory::getApplication()->input;

		$db = Factory::getContainer()->get('DatabaseDriver');
		$table->title = htmlspecialchars_decode($table->title, ENT_QUOTES);

		// Increment version number.
		$table->version ++;

		//get time-values from time selectlist and combine them accordingly
		$starthours   = $jinput->get('starthours','','cmd');
		$startminutes = $jinput->get('startminutes','','cmd');
		$endhours     = $jinput->get('endhours','','cmd');
		$endminutes   = $jinput->get('endminutes','','cmd');

		// StartTime
		if ($starthours != '' && $startminutes != '') {
			$table->times = $starthours.':'.$startminutes;
		} else if ($starthours != '' && $startminutes == '') {
			$startminutes = "00";
			$table->times = $starthours.':'.$startminutes;
		} else if ($starthours == '' && $startminutes != '') {
			$starthours = "00";
			$table->times = $starthours.':'.$startminutes;
		} else {
			$table->times = "";
		}

		// EndTime
		if ($endhours != '' && $endminutes != '') {
			$table->endtimes = $endhours.':'.$endminutes;
		} else if ($endhours != '' && $endminutes == '') {
			$endminutes = "00";
			$table->endtimes = $endhours.':'.$endminutes;
		} else if ($endhours == '' && $endminutes != '') {
			$endhours = "00";
			$table->endtimes = $endhours.':'.$endminutes;
		} else {
			$table->endtimes = "";
		}
	}

	/**
	 * Method to save the form data.
	 *
	 * @param  $data array
	 */
	public function save($data)
	{
		// Variables
		$app         = Factory::getApplication();
		$jinput      = $app->input;
		$jemsettings = JemHelper::config();
		$table       = $this->getTable();

		// Check if we're in the front or back
		$backend = (bool)$app->isClient('administrator');
		$new     = (bool)empty($data['id']);

		// Variables
		$cats             = $data['cats'];
		$invitedusers     = isset($data['invited']) ? $data['invited'] : '';
		$recurrencenumber = $jinput->get('recurrence_number', '', 'int');
		$recurrencebyday  = $jinput->get('recurrence_byday', '', 'string');
		$metakeywords     = $jinput->get('meta_keywords', '', '');
		$metadescription  = $jinput->get('meta_description', '', '');
		$task             = $jinput->get('task', '', 'cmd');
		$data['metadata']  = isset($data['metadata']) ? $data['metadata'] : '';
		$data['attribs']  = isset($data['attribs']) ? $data['attribs'] : '';
		$data['ordering']  = isset($data['ordering']) ? $data['ordering'] : '';
	
		// event maybe first of recurrence set -> dissolve complete set
		if (JemHelper::dissolve_recurrence($data['id'])) {
			$this->cleanCache();
		}

		// on frontend we have dedicated field for 'reginvitedonly' -> set 'registra' to +2 then
		if (array_key_exists('reginvitedonly', $data) && ($data['reginvitedonly'] == 1)) {
			$data['registra'] = ($data['registra'] == 1) ? 3 : 2;
		}

		// convert international date formats...
        $db = Factory::getContainer()->get('DatabaseDriver');
		if (!empty($data['dates']) && ($data['dates'] != null)) {
			$d = Factory::getDate($data['dates'], 'UTC');
			$data['dates'] = $d->format('Y-m-d', true, false);
		}
		if (!empty($data['enddates']) && ($data['enddates'] != null)) {
			$d = Factory::getDate($data['enddates'], 'UTC');
			$data['enddates'] = $d->format('Y-m-d', true, false);
		}

		if ($data['dates'] == null || $data['recurrence_type'] == '0')
		{
			$data['recurrence_number']     = '0';
			$data['recurrence_byday']      = '0';
			$data['recurrence_counter']    = '0';
			$data['recurrence_type']       = '0';
			$data['recurrence_limit']      = '0';
			$data['recurrence_limit_date'] = null;
			$data['recurrence_first_id']   = '0';
		} else {
			if (!$new) {
				// edited event maybe part of a recurrence set
				// -> drop event from set
				$data['recurrence_first_id'] = '0';
				$data['recurrence_counter']  = '0';
			}

			$data['recurrence_number'] = $recurrencenumber;
			$data['recurrence_byday']  = $recurrencebyday;

			if (!empty($data['recurrence_limit_date']) && ($data['recurrence_limit_date'] != null)) {
				$d = Factory::getDate($data['recurrence_limit_date'], 'UTC');
				$data['recurrence_limit_date'] = $d->format('Y-m-d', true, false);
			}
		}

		$data['meta_keywords']    = $metakeywords;
		$data['meta_description'] = $metadescription;

		// Store IP of author only.
		if ($new) {
			$author_ip = $jinput->get('author_ip', '', 'string');
			$data['author_ip'] = $author_ip;
		}

		// Store as copy - reset creation date, modification fields, hit counter, version
		if ($task == 'save2copy') {
			unset($data['created']);
			unset($data['modified']);
			unset($data['modified_by']);
			unset($data['version']);
			unset($data['hits']);
		}

		// Save the event
		$saved = parent::save($data);

		if ($saved) {
			// At this point we do have an id.
			$pk = $this->getState($this->getName() . '.id');

			if (isset($data['featured'])) {
				$this->featured($pk, $data['featured']);
			}

			// on frontend attachment uploads maybe forbidden
			// so allow changing name or description only
			$allowed = $backend || ($jemsettings->attachmentenabled > 0);

			if ($allowed) {
				// attachments, new ones first
				$attachments   = $jinput->files->get('attach', array(), 'array');
				$attach_name   = $jinput->post->get('attach-name', array(), 'array');
				$attach_descr  = $jinput->post->get('attach-desc', array(), 'array');
				$attach_access = $jinput->post->get('attach-access', array(), 'array');
				foreach($attachments as $n => &$a) {
					$a['customname']  = array_key_exists($n, $attach_access) ? $attach_name[$n]   : '';
					$a['description'] = array_key_exists($n, $attach_access) ? $attach_descr[$n]  : '';
					$a['access']      = array_key_exists($n, $attach_access) ? $attach_access[$n] : '';
				}
				JemAttachment::postUpload($attachments, 'event' . $pk);
			}

			// and update old ones
			$old = array();
			$old['id']          = $jinput->post->get('attached-id', array(), 'array');
			$old['name']        = $jinput->post->get('attached-name', array(), 'array');
			$old['description'] = $jinput->post->get('attached-desc', array(), 'array');
			$old['access']      = $jinput->post->get('attached-access', array(), 'array');

			foreach ($old['id'] as $k => $id) {
				$attach = array();
				$attach['id']          = $id;
				$attach['name']        = $old['name'][$k];
				$attach['description'] = $old['description'][$k];
				if ($allowed) {
					$attach['access']  = $old['access'][$k];
				} // else don't touch this field
				JemAttachment::update($attach);
			}

			// Store cats
			if (!$this->_storeCategoriesSelected($pk, $cats, !$backend, $new)) {
			//	JemHelper::addLogEntry('Error storing categories for event ' . $pk, __METHOD__, JLog::ERROR);
				$this->setError(Text::_('COM_JEM_EVENT_ERROR_STORE_CATEGORIES'));
				$saved = false;
			}

			// Store invited users (frontend only, on backend no attendees on editevent view)
			if (!$backend && ($jemsettings->regallowinvitation == 1)) {
				if (!$this->_storeUsersInvited($pk, $invitedusers, !$backend, $new)) {
				//	JemHelper::addLogEntry('Error storing users invited for event ' . $pk, __METHOD__, JLog::ERROR);
					$this->setError(Text::_('COM_JEM_EVENT_ERROR_STORE_INVITED_USERS'));
					$saved = false;
				}
			}

			// check for recurrence
			// when filled it will perform the cleanup function
			$table->load($pk);
			if (($table->recurrence_number > 0) && ($table->dates != null)) {
				JemHelper::cleanup(2); // 2 = force on save, needs special attention
			}
		}

		return $saved;
	}

	/**
	 * Method to update cats_event_selections table.
	 * Records of previously selected categories will be removed
	 * and newly selected categories will be stored.
	 * Because user may not have permissions for all categories on frontend
	 * records with non-permitted categories will be untouched.
	 *
	 * @param  int     The event id.
	 * @param  array   The categories user has selected.
	 * @param  bool    Flag to indicate if we are on frontend
	 * @param  bool    Flag to indicate new event
	 *
	 * @return boolean True on success.
	 */
	protected function _storeCategoriesSelected($eventId, $categories, $frontend, $new)
	{
		$user = JemFactory::getUser();
		$db   = Factory::getContainer()->get('DatabaseDriver');

		$eventId = (int)$eventId;
		if (empty($eventId) || !is_array($categories)) {
			return false;
		}

		// get previous entries
		$query = $db->getQuery(true);
		$query->select('catid')
		      ->from('#__jem_cats_event_relations')
		      ->where('itemid = ' . $eventId)
		      ->order('catid');
		$db->setQuery($query);
		$cur_cats = $db->loadColumn();

		if (!is_array($cur_cats)) {
			return false;
		}

		$ret = true;
		$del_cats = array_diff($cur_cats, $categories);
		$add_cats = array_diff($categories, $cur_cats);

		/* Attention!
		 *  On frontend user maybe not permitted to see all categories attached.
		 *  But these categories must not removed from this event!
		 */
		if ($frontend) {
			// Note: JFormFieldCatOptions calls the same function to know which categories user is allowed (un)select.
			$limit_cats = array_keys($user->getJemCategories($new ? array('add') : array('add', 'edit'), 'event'));
			$del_cats = array_intersect($del_cats, $limit_cats);
			$add_cats = array_intersect($add_cats, $limit_cats);
		}

		if (!empty($del_cats)) {
			$query = $db->getQuery(true);
			$query->delete($db->quoteName('#__jem_cats_event_relations'));
			$query->where('itemid = ' . $eventId);
			$query->where('catid IN (' . implode(',', $del_cats) . ')');
			$db->setQuery($query);
			$ret &= ($db->execute() !== false);
		}

		if (!empty($add_cats)) {
			$query = $db->getQuery(true);
			$query->insert($db->quoteName('#__jem_cats_event_relations'))
			      ->columns($db->quoteName(array('catid', 'itemid','ordering')));
			foreach ($add_cats as $catid) {
				$query->values((int)$catid . ',' . $eventId.','.'0');
			}
			$db->setQuery($query);
			$ret &= ($db->execute() !== false);
		}

		return $ret;
	}

	/**
	 * Method to update cats_event_selections table.
	 * Records of previously selected categories will be removed
	 * and newly selected categories will be stored.
	 * Because user may not have permissions for all categories on frontend
	 * records with non-permitted categories will be untouched.
	 *
	 * @param  int     The event id.
	 * @param  mixed   The user ids as array or comma separated string.
	 * @param  bool    Flag to indicate if we are on frontend
	 * @param  bool    Flag to indicate new event
	 *
	 * @return boolean True on success.
	 */
	protected function _storeUsersInvited($eventId, $users, $frontend, $new)
	{
		$eventId = (int)$eventId;
		if (!is_array($users)) {
			$users = explode(',', $users);
		}
		$users = array_unique($users);
		$users = array_filter($users);

		if (empty($eventId)) {
			return false;
		}

		$db = Factory::getContainer()->get('DatabaseDriver');

		# Get current registrations
		$query = $db->getQuery(true);
		$query->select(array('reg.id, reg.uid, reg.status, reg.waiting'));
		$query->from('#__jem_register As reg');
		$query->where('reg.event = ' . $eventId);
		$db->setQuery($query);
		$regs = $db->loadObjectList('uid');

		JPluginHelper::importPlugin('jem');
		$dispatcher = JemFactory::getDispatcher();

		# Add new records, ignore users already registered
		foreach ($users AS $user)
		{
			if (!array_key_exists($user, $regs)) {
				$query = $db->getQuery(true);
				$query->insert('#__jem_register');
				$query->columns(array('event', 'uid', 'status'));
				$query->values($eventId.','.$user.',0');
				$db->setQuery($query);
				try {
					$ret = $db->execute();
				} catch (Exception $e) {
					JemHelper::addLogEntry('Exception: '. $e->getMessage(), __METHOD__, JLog::ERROR);
					$ret = false;
				}

				if ($ret !== false) {
					$id = $db->insertid();
					$dispatcher->triggerEvent('onEventUserRegistered', array($id));
				}
			}
		}

		# Remove obsolete invitations
		foreach ($regs as $reg)
		{
			if (($reg->status == 0) && (array_search($reg->uid, $users) === false)) {
				$query = $db->getQuery(true);
				$query->delete('#__jem_register');
				$query->where('id = '.$reg->id);
				$db->setQuery($query);
				try {
					$ret = $db->execute();
				} catch (Exception $e) {
					JemHelper::addLogEntry('Exception: '. $e->getMessage(), __METHOD__, JLog::ERROR);
					$ret = false;
				}

				if ($ret !== false) {
					$dispatcher->triggerEvent('onEventUserUnregistered', array($eventId, $reg));
				}
			}
		}

		$cache = Factory::getCache('com_jem');
		$cache->clean();

		return true;
	}

	/**
	 * Method to toggle the featured setting of articles.
	 *
	 * @param  array   The ids of the items to toggle.
	 * @param  int     The value to toggle to.
	 *
	 * @return boolean True on success.
	 */
	public function featured($pks, $value = 0)
	{
		// Sanitize the ids.
		$pks = (array)$pks;
		\Joomla\Utilities\ArrayHelper::toInteger($pks);

		if (empty($pks)) {
			$this->setError(Text::_('COM_JEM_EVENTS_NO_ITEM_SELECTED'));
			return false;
		}

		try {
			$db = Factory::getContainer()->get('DatabaseDriver');

			$db->setQuery(
					'UPDATE #__jem_events' .
					' SET featured = '.(int) $value.
					' WHERE id IN ('.implode(',', $pks).')'
			);
			$db->execute() ;

		} catch (Exception $e) {
			$this->setError($e->getMessage());
			return false;
		}

		$this->cleanCache();

		return true;
	}
}
