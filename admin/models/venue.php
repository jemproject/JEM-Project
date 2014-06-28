<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

jimport('joomla.application.component.modeladmin');

/**
 * Venue Model
 */
class JEMModelVenue extends JModelAdmin
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
			if ($record->published != -2) {
				return ;
			}

			$user = JFactory::getUser();

			if (!empty($record->catid)) {
				return $user->authorise('core.delete', 'com_jem.category.'.(int) $record->catid);
			} else {
				return $user->authorise('core.delete', 'com_jem');
			}
		}
	}

	/**
	 * Method to test whether a record can be deleted.
	 *
	 * @param	object	A record object.
	 * @return	boolean	True if allowed to change the state of the record. Defaults to the permission set in the component.
	 */
	protected function canEditState($record)
	{
		$user = JFactory::getUser();

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
	public function getTable($type = 'Venue', $prefix = 'JEMTable', $config = array())
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
	protected function prepareTable(&$table)
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
		$date 			= JFactory::getDate();
		$jinput 		= JFactory::getApplication()->input;
		$user 			= JFactory::getUser();
		$jemsettings 	= JEMHelper::config();
		$app 			= JFactory::getApplication();
		$fileFilter 	= new JInput($_FILES);
		$table 			= $this->getTable();

		// Check if we're in the front or back
		if ($app->isAdmin())
			$backend = true;
		else
			$backend = false;

		$ip = $jinput->get('author_ip', '', 'string');
		$data['author_ip'] 		= $ip;

		//uppercase needed by mapservices
		if ($data['country']) {
			$data['country'] = JString::strtoupper($data['country']);
		}

		if (parent::save($data)){
			// At this point we do have an id.
			$pk = $this->getState($this->getName() . '.id');

			// attachments, new ones first
			$attachments 				= array();
			$attachments 				= $fileFilter->get('attach', array(), 'array');
			$attachments['customname']	= $jinput->post->get('attach-name', array(), 'array');
			$attachments['description'] = $jinput->post->get('attach-desc', array(), 'array');
			$attachments['access'] 		= $jinput->post->get('attach-access', array(), 'array');
			JEMAttachment::postUpload($attachments, 'venue' . $pk);

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
				$attach['access'] 		= $old['access'][$k];
				JEMAttachment::update($attach);
			}

			return true;
		}

		return false;
	}
}