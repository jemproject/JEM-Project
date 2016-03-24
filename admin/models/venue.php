<?php
/**
 * @version 2.1.6
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

require_once dirname(__FILE__) . '/admin.php';

/**
 * Model: Venue
 */
class JemModelVenue extends JemModelAdmin
{
	/**
	 * Method to test whether a record can be deleted.
	 *
	 * @param	object	A record object.
	 * @return	boolean	True if allowed to delete the record. Defaults to the permission set in the component.
	 */
	protected function canDelete($record)
	{
		if (!empty($record->id))
		{
			$user = JemFactory::getUser();

			return $user->authorise('core.delete', 'com_jem');
		}
	}

	/**
	 * Method to delete a venue
	 */
	public function delete(&$pks = array())
	{
		$return = array();
		if($pks)
		{
			$pksTodelete = array();
			$errorNotice = array();
			$db = JFactory::getDbo();
			foreach($pks as $pk)
			{
				$result = array();

				$query = $db->getQuery(true);
				$query->select(array('COUNT(e.locid) as AssignedEvents'));
				$query->from($db->quoteName('#__jem_venues').' AS v');
				$query->join('LEFT', '#__jem_events AS e ON e.locid = v.id');
				$query->where(array('v.id = '.$pk));
				$query->group('v.id');
				$db->setQuery($query);
				$assignedEvents = $db->loadResult();

				if($assignedEvents > 0)
				{
					$result[] = JText::_('COM_JEM_VENUE_ASSIGNED_EVENT');
				}

				if($result)
				{
					$pkInfo = array("id:".$pk);
					$result = array_merge($pkInfo,$result);
					$errorNotice[] = $result;
				}
				else
				{
					$pksTodelete[] = $pk;
				}
			}

			if($pksTodelete)
			{
				$return['removed'] = parent::delete($pksTodelete);
				$return['removedCount'] = count($pksTodelete);
			}
			else
			{
				$return['removed'] = false;
				$return['removedCount'] = false;
			}

			if($errorNotice)
			{
				$return['error'] = $errorNotice;
			}
			else
			{
				$return['error'] = false;
			}

			return $return;
		}

		$return['removed'] = false;
		$return['error'] = false;
		$return['removedCount'] = false;

		return $return;
	}

	/**
	 * Method to test whether a record can be deleted.
	 *
	 * @param	object	A record object.
	 * @return	boolean	True if allowed to change the state of the record. Defaults to the permission set in the component.
	 */
	protected function canEditState($record)
	{
		$user = JemFactory::getUser();

		if (!empty($record->catid)) {
			return $user->authorise('core.edit.state', 'com_jem.category.'.(int) $record->catid);
		} else {
			return $user->authorise('core.edit.state', 'com_jem');
		}
	}

	/**
	 * Returns a reference to the a Table object, always creating it.
	 *
	 * @param	type	The table type to instantiate
	 * @param	string	A prefix for the table class name. Optional.
	 * @param	array	Configuration array for model. Optional.
	 * @return	JTable	A database object
	 */
	public function getTable($type = 'Venue', $prefix = 'JemTable', $config = array())
	{
		return JTable::getInstance($type, $prefix, $config);
	}

	/**
	 * Method to get the record form.
	 *
	 * @param	array	$data		Data for the form.
	 * @param	boolean	$loadData	True if the form is to load its own data (default case), false if not.
	 * @return	mixed	A JForm object on success, false on failure
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Get the form.
		$form = $this->loadForm('com_jem.venue', 'venue', array('control' => 'jform', 'load_data' => $loadData));
		if (empty($form)) {
			return false;
		}

		return $form;
	}


	/**
	 * Method to get a single record.
	 *
	 * @param	integer	The id of the primary key.
	 *
	 * @return	mixed	Object on success, false on failure.
	 */
	public function getItem($pk = null)
	{
		$jemsettings = JEMAdmin::config();

		if ($item = parent::getItem($pk)) {
			$files = JEMAttachment::getAttachments('venue'.$item->id);
			$item->attachments = $files;
		}

		$item->author_ip = $jemsettings->storeip ? JemHelper::retrieveIP() : false;

		if (empty($item->id)) {
			$item->country = $jemsettings->defaultCountry;
		}

		return $item;
	}


	/**
	 * Method to get the data that should be injected in the form.
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_jem.edit.venue.data', array());

		if (empty($data)) {
			$data = $this->getItem();
		}

		return $data;
	}


	/**
	 * Prepare and sanitise the table data prior to saving.
	 *
	 * @param $table JTable-object.
	 */
	protected function _prepareTable($table)
	{
		$db = $this->getDbo();
		$table->venue = htmlspecialchars_decode($table->venue, ENT_QUOTES);

		// Increment version number.
		$table->version ++;
	}


	/**
	 * Method to save the form data.
	 *
	 * @param $data array
	 */
	public function save($data)
	{
		// Variables
		$date        = JFactory::getDate();
		$app         = JFactory::getApplication();
		$jinput      = $app->input;
		$user        = JemFactory::getUser();
		$jemsettings = JemHelper::config();
		$fileFilter  = new JInput($_FILES);
		$table       = $this->getTable();
		$task        = $jinput->get('task', '', 'cmd');

		// Check if we're in the front or back
		if ($app->isAdmin())
			$backend = true;
		else
			$backend = false;

		// Store IP of author only.
		if (!$data['id']) {
			$author_ip = $jinput->get('author_ip', '', 'string');
			$data['author_ip'] = $author_ip;
		}

		// Store as copy - reset creation date, modification fields, hit counter, version
		if ($task == 'save2copy') {
			unset($data['created']);
			unset($data['modified']);
			unset($data['modified_by']);
			unset($data['version']);
		//	unset($data['hits']);
		}

		//uppercase needed by mapservices
		if ($data['country']) {
			$data['country'] = JString::strtoupper($data['country']);
		}

		if (parent::save($data)){
			// At this point we do have an id.
			$pk = $this->getState($this->getName() . '.id');

			// on frontend attachment uploads maybe forbidden
			// so allow changing name or description only
			$allowed = $backend || ($jemsettings->attachmentenabled > 0);

			if ($allowed) {
				// attachments, new ones first
				$attachments 				= array();
				$attachments 				= $fileFilter->get('attach', array(), 'array');
				$attachments['customname']	= $jinput->post->get('attach-name', array(), 'array');
				$attachments['description'] = $jinput->post->get('attach-desc', array(), 'array');
				$attachments['access'] 		= $jinput->post->get('attach-access', array(), 'array');
				JEMAttachment::postUpload($attachments, 'venue' . $pk);
			}

			// and update old ones
			$old				= array();
			$old['id'] 			= $jinput->post->get('attached-id', array(), 'array');
			$old['name'] 		= $jinput->post->get('attached-name', array(), 'array');
			$old['description'] = $jinput->post->get('attached-desc', array(), 'array');
			$old['access'] 		= $jinput->post->get('attached-access', array(), 'array');

			foreach ($old['id'] as $k => $id){
				$attach 				= array();
				$attach['id'] 			= $id;
				$attach['name'] 		= $old['name'][$k];
				$attach['description'] 	= $old['description'][$k];
				if ($allowed) {
					$attach['access'] 	= $old['access'][$k];
				} // else don't touch this field
				JEMAttachment::update($attach);
			}

			return true;
		}

		return false;
	}
}