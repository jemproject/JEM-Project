<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
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

		// Convert Css settings to an array
		$registryCss = new JRegistry;
		$registryCss->loadString($data->css);
		$data->css = $registryCss->toArray();

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


	/**
	 * Return config information
	 */
	public function getConfigInfo()
	{
		if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
			$quote = "enabled";
		} else {
			$quote = "disabled";
		}

		// Get GD version.
		$gd_version = '?';
		if (function_exists('gd_info')) {
			$gd_info = gd_info();
			if (array_key_exists('GD Version', $gd_info)) {
				$gd_version = $gd_info['GD Version'];
			}
		} else {
			ob_start();
			if (phpinfo(INFO_MODULES)) {
				$info = strip_tags(ob_get_contents());
			}
			ob_end_clean();
			preg_match('/gd support\w*(.*)/i', $info, $gd_sup);
			preg_match('/gd version\w*(.*)/i', $info, $gd_ver);
			if (count($gd_ver) > 0) {
				$gd_version = trim($gd_ver[1]);
			}
			if (count($gd_sup) > 0) {
				$gd_version .= ' (' . trim($gd_sup[1]) . ')';
			}
		}

		$config 					= new stdClass();
		$config->vs_component		= JemHelper::getParam(1,'version',1,'com_jem');
		$config->vs_plg_mailer		= JemHelper::getParam(1,'version',2,'plg_jem_mailer');
		$config->vs_mod_jem_cal		= JemHelper::getParam(1,'version',3,'mod_jem_cal');
		$config->vs_mod_jem			= JemHelper::getParam(1,'version',3,'mod_jem');
		$config->vs_mod_jem_wide	= JemHelper::getParam(1,'version',3,'mod_jem_wide');
		$config->vs_mod_jem_teaser	= JemHelper::getParam(1,'version',3,'mod_jem_teaser');
		$config->vs_php				= phpversion();
		$config->vs_php_magicquotes	= $quote;
		$config->vs_gd				= $gd_version;

		return $config;
	}

}
