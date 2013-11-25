<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.modelform');

/**
 * JEM Component Settings Model
 *
 */
class JEMModelSettings extends JModelForm
{
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
		$form = $this->loadForm('com_jem.settings', 'settings', array('control' => 'jform', 'load_data' => $loadData));
		if (empty($form)) {
			return false;
		}

		return $form;
	}


	/**
	 * Loading the table data
	 */
	public function getData()
	{
		
		$db = JFactory::getDbo();

		$query = $db->getQuery(true);
		$query->select(array('*'));
		$query->from('#__jem_settings');
		$query->where(array('id = 1 '));

		$db->setQuery($query);
		$data = $db->loadObject();
		
		
		// Convert the params field to an array.
		$registry = new JRegistry;
		$registry->loadString($data->globalattribs);
		$data->globalattribs = $registry->toArray();
		
		return $data;
	}
	
	
	/**
	 * Method to get the data that should be injected in the form.
	 *
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_jem.edit.settings.data', array());

		if (empty($data)) {
			$data = $this->getData();
		}

		return $data;
	}


	/**
	 * Saves the settings
	 *
	 */
	function store($data)
	{		
		$settings 	= JTable::getInstance('Settings', 'JEMTable');
		$jinput = JFactory::getApplication()->input;
		
		// Bind the form fields to the table
		if (!$settings->bind($data,'')) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}
		
		$varmetakey = $jinput->get('meta_keywords','','');
		$settings->meta_keywords = $varmetakey;

		$meta_key="";
		foreach ($settings->meta_keywords as $meta_keyword) {
			if ($meta_key != "") {
				$meta_key .= ", ";
			}
			$meta_key .= $meta_keyword;
		}

		// binding the input fields (outside the jform)
		$varlastupdate = $jinput->get('lastupdate','','');
		$settings->lastupdate = $varlastupdate;

		$settings->meta_keywords = $meta_key;
		$settings->id = 1;

		if (!$settings->store()) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

		return true;
	}
	
	
	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @since	1.6
	 */
	protected function populateState()
	{
		$app = JFactory::getApplication();
	
		// Load the parameters.
		$params = JComponentHelper::getParams('com_jem');
		$this->setState('params', $params);
	}
	
	
}
