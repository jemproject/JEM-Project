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
 * Event model.
 */
class JEMModelEvent extends JModelAdmin
{
	/**
	 * Method to test whether a record can be deleted.
	 *
	 * @param	object	A record object.
	 * @return	boolean	True if allowed to delete the record. Defaults to the permission set in the component.
	 *
	 */
	protected function canDelete($record)
	{
		if (!empty($record->id)) {
			if ($record->published != -2){
				return ;
			}

			$user = JFactory::getUser();

			if (!empty($record->catid)){
				$db = JFactory::getDbo();

				$query = $db->getQuery(true);
				$query->delete($db->quoteName('#__jem_cats_event_relations'));
				$query->where('itemid = '.$record->id);

				$db->setQuery($query);
				$db->query();

				return $user->authorise('core.delete', 'com_jem.category.'.(int) $record->catid);
			} else {
				$db = JFactory::getDbo();

				$query = $db->getQuery(true);
				$query->delete($db->quoteName('#__jem_cats_event_relations'));
				$query->where('itemid = '.$record->id);

				$db->setQuery($query);
				$db->query();

				return $user->authorise('core.delete', 'com_jem');
			}
		}
	}

	/**
	 * Method to test whether a record can be deleted.
	 *
	 * @param	object	A record object.
	 * @return	boolean	True if allowed to change the state of the record. Defaults to the permission set in the component.
	 *
	 */
	protected function canEditState($record)
	{
		$user = JFactory::getUser();

		if (!empty($record->catid)){
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
	 *
	 */
	public function getTable($type = 'Event', $prefix = 'JEMTable', $config = array())
	{
		return JTable::getInstance($type, $prefix, $config);
	}

	/**
	 * Method to get the record form.
	 *
	 * @param	array	$data		Data for the form.
	 * @param	boolean	$loadData	True if the form is to load its own data (default case), false if not.
	 * @return	mixed	A JForm object on success, false on failure
	 *
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
	 * @param	integer	The id of the primary key.
	 *
	 * @return	mixed	Object on success, false on failure.
	 */
	public function getItem($pk = null)
	{
		$jemsettings = JEMAdmin::config();

		if ($item = parent::getItem($pk)){
			// Convert the params field to an array.
			$registry = new JRegistry;
			$registry->loadString($item->attribs);
			$item->attribs = $registry->toArray();

			// Convert the metadata field to an array.
			$registry = new JRegistry;
			$registry->loadString($item->metadata);
			$item->metadata = $registry->toArray();

			$item->articletext = trim($item->fulltext) != '' ? $item->introtext . "<hr id=\"system-readmore\" />" . $item->fulltext : $item->introtext;

			$db = JFactory::getDbo();

			$query = $db->getQuery(true);
			$query->select(array('count(id)'));
			$query->from('#__jem_register');
			$query->where(array('event= '.$db->quote($item->id), 'waiting= 0'));

			$db->setQuery($query);
			$res = $db->loadResult();
			$item->booked = $res;

			$files = JEMAttachment::getAttachments('event'.$item->id);
			$item->attachments = $files;
		}

		if ($item->id){
			// Store current recurrence values
			$item->recurr_bak = new stdClass;
			foreach (get_object_vars($item) as $k => $v) {
				if (strncmp('recurrence_', $k, 11) === 0) {
					$item->recurr_bak->$k = $v;
				}
			}

			$item->recurrence_type 			= '';
			$item->recurrence_number 		= '';
			$item->recurrence_byday 		= '';
			$item->recurrence_counter 		= '';
			$item->recurrence_first_id 		= '';
			$item->recurrence_limit 		= '';
			$item->recurrence_limit_date	= '';
		}

		$item->author_ip = $jemsettings->storeip ? JemHelper::retrieveIP() : false;

		if (empty($item->id)){
			$item->country = $jemsettings->defaultCountry;
		}

		return $item;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_jem.edit.event.data', array());

		if (empty($data)){
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
		$jinput 		= JFactory::getApplication()->input;
		
		$db = $this->getDbo();
		$table->title = htmlspecialchars_decode($table->title, ENT_QUOTES);

		// Increment version number.
		$table->version ++;
		
		//get time-values from time selectlist and combine them accordingly
		$starthours		= $jinput->get('starthours','','cmd');
		$startminutes	= $jinput->get('startminutes','','cmd');
		$endhours		= $jinput->get('endhours','','cmd');
		$endminutes		= $jinput->get('endminutes','','cmd');
		
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

		// Variables
		$cats 				= $jinput->get('cid', array(), 'post', 'array');
		$recurrencenumber 	= $jinput->get('recurrence_number', '', 'int');
		$recurrencebyday 	= $jinput->get('recurrence_byday', '', 'string');
		$metakeywords 		= $jinput->get('meta_keywords', '', '');
		$metadescription 	= $jinput->get('meta_description', '', '');
		$author_ip 			= $jinput->get('author_ip', '', '');
		
		// event maybe first of recurrence set -> dissolve complete set
		if (JemHelper::dissolve_recurrence($data['id'])) {
			$this->cleanCache();
		}

		if ($data['dates'] == null || $data['recurrence_type'] == '0')
		{
			$data['recurrence_number']		= '';
			$data['recurrence_byday']		= '';
			$data['recurrence_counter'] 	= '';
			$data['recurrence_type']		= '';
			$data['recurrence_limit']		= '';
			$data['recurrence_limit_date']	= '';
			$data['recurrence_first_id']	= '';
		}else{
			if ($data['id']) {
				// edited event maybe part of a recurrence set
				// -> drop event from set
				$data['recurrence_first_id']	= '';
				$data['recurrence_counter'] 	= '';
			}

			$data['recurrence_number']		= $recurrencenumber;
			$data['recurrence_byday']		= $recurrencebyday;
		}

		$data['meta_keywords'] 		= $metakeywords;
		$data['meta_description']	= $metadescription;
		$data['author_ip']			= $author_ip;

		if (parent::save($data)){
			// At this point we do have an id.
			$pk = $this->getState($this->getName() . '.id');

			if (isset($data['featured'])){
				$this->featured($pk, $data['featured']);
			}

			// attachments, new ones first
			$attachments 				= array();
			$attachments 				= $fileFilter->get('attach', array(), 'array');
			$attachments['customname']	= $jinput->post->get('attach-name', array(), 'array');
			$attachments['description'] = $jinput->post->get('attach-desc', array(), 'array');
			$attachments['access'] 		= $jinput->post->get('attach-access', array(), 'array');
			JEMAttachment::postUpload($attachments, 'event' . $pk);

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

			// Store cats
			$cats	= $jinput->get('cid', array(), 'post', 'array');
			$db 	= $this->getDbo();
			$query 	= $db->getQuery(true);

			$query->delete($db->quoteName('#__jem_cats_event_relations'));
			$query->where('itemid = ' . $pk);
			$db->setQuery($query);
			$db->query();

			foreach ($cats as $cat){
				$db 	= $this->getDbo();
				$query	= $db->getQuery(true);

				// Insert columns.
				$columns = array('catid','itemid');

				// Insert values.
				$values = array($cat,$pk);

				// Prepare the insert query.
				$query->insert($db->quoteName('#__jem_cats_event_relations'))
				->columns($db->quoteName($columns))
				->values(implode(',', $values));

				// Reset the query using our newly populated query object.
				$db->setQuery($query);
				$db->query();
			}

			// check for recurrence
			// when filled it will perform the cleanup function

			$table->load($pk);
			if ($table->recurrence_number > 0 && !$table->dates == null){
				JEMHelper::cleanup(1);
			}

			return true;
		}

		return false;
	}

	/**
	 * Method to toggle the featured setting of articles.
	 *
	 * @param	array	The ids of the items to toggle.
	 * @param	int		The value to toggle to.
	 *
	 * @return	boolean	True on success.
	 */
	public function featured($pks, $value = 0)
	{
		// Sanitize the ids.
		$pks = (array) $pks;
		JArrayHelper::toInteger($pks);

		if (empty($pks)) {
			$this->setError(JText::_('COM_JEM_EVENTS_NO_ITEM_SELECTED'));
			return false;
		}

		try {
			$db = $this->getDbo();

			$db->setQuery(
					'UPDATE #__jem_events' .
					' SET featured = '.(int) $value.
					' WHERE id IN ('.implode(',', $pks).')'
			);
			if (!$db->query()) {
				throw new Exception($db->getErrorMsg());
			}

		} catch (Exception $e) {
			$this->setError($e->getMessage());
			return false;
		}

		$this->cleanCache();

		return true;
	}
}