<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

// No direct access.
defined('_JEXEC') or die;

jimport('joomla.application.component.modelform');

/**
 * Settings model.
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
	 *
	 *
	 */
	public function getData()
	{
		$query = 'SELECT * FROM #__jem_settings WHERE id = 1';

		$this->_db->setQuery($query);
		$data = $this->_db->loadObject();

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
	function store($post,$post2)
	{

		//var_dump($post2);exit;
		//var_dump($post);exit;

		$settings 	= JTable::getInstance('jem_settings', '');
		$jinput = JFactory::getApplication()->input;

		// Bind the form fields to the table
		if (!$settings->bind($post)) {
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


		$varoldevent = $jinput->get('oldevent','','int');
		$varminus = $jinput->get('minus','','int');
		$varcomunsolution = $jinput->get('comunsolution','','int');
		$varcomunoption = $jinput->get('comunoption','','int');
		$varshowfroregistra = $jinput->get('showfroregistra','','');
		$varshowfrounregistra = $jinput->get('showfrounregistra','','');
		$varlastupdate = $jinput->get('lastupdate','','');

		$settings->oldevent = $varoldevent;
		$settings->minus = $varminus;
		$settings->comunsolution = $varcomunsolution;
		$settings->comunoption = $varcomunoption;
		$settings->showfroregistra = $varshowfroregistra;
		$settings->showfrounregistra = $varshowfrounregistra;
		$settings->lastupdate = $varlastupdate;


		$settings->meta_keywords = $meta_key;

		$settings->id = 1;




		if (!$settings->store()) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}


		return true;
	}













}
