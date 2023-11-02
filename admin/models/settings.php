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
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;

/**
 * JEM Component Settings Model
 *
 */
class JemModelSettings extends AdminModel
{
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
		$config = JemConfig::getInstance();
		$data = $config->toObject();

		return $data;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = Factory::getApplication()->getUserState('com_jem.edit.settings.data', array());

		if (empty($data)) {
			$data = $this->getData();
		}

		return $data;
	}

	/**
	 * Saves the settings
	 */
	public function store($data)
	{
		// If the source value is an object, get its accessible properties.
		if (is_object($data)) {
			$data = get_object_vars($data);
		}

		// additional data:
		$jinput = Factory::getApplication()->input;
		$varmetakey = $jinput->get('meta_keywords','','');
		$data['meta_keywords'] = implode(', ', array_filter($varmetakey));
		$data['lastupdate'] = $jinput->get('lastupdate','',''); // 'lastupdate' indicates last cleanup etc., not when config as stored.

		// sanitize
		if (empty($data['imagewidth'])) {
			$data['imagewidth'] = 100;
		}
		if (empty($data['imagehight'])) {
			$data['imagehight'] = 100;
		}

		//
		// Store into new table
		//
		$config = JemConfig::getInstance();

		// Bind the form fields to the table
		if (!$config->bind($data)) {
			$this->setError(Text::_('?'));
			return false;
		}
		if (!$config->store()) {
			$this->setError(Text::_('?'));
			return false;
		}

		//
		// Old table - deprecated, maybe already removed
		//
		try {
			$settings = Table::getInstance('Settings', 'JemTable');

			$fields = $settings->getFields();
			if (!empty($fields)) {
				// Bind the form fields to the table
				if (!$settings->bind($data,'')) {
					$this->setError($settings->getError());
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
					$this->setError($settings->getError());
					return false;
				}
			}
			// else: ok, old table removed - simply ignore
		}
		catch(Exception $e) {
			// ok, old table removed - simply ignore
		}

		return true;
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * @Note Calling getState in this method will result in recursion.
	 *
	 * @since 1.6
	 */
	protected function populateState()
	{
		$app = Factory::getApplication();

		// Load the parameters.
		$params = JComponentHelper::getParams('com_jem');
		$this->setState('params', $params);
	}

	/**
	 * Return config information
	 */
	public function getConfigInfo()
	{
		$config = new stdClass();

		// Get PHP version and optionally if Magic Quotes are enabled or not
		$phpversion = phpversion();

		if (version_compare($phpversion, '5.4', '<')) {
			$quote = (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) ? "enabled" : "disabled";
		} else { // since PHP 5.4 magic quotes has completely removed
			$quote = '';
		}

		$config->vs_php = $phpversion;
		$config->vs_php_magicquotes	= $quote;

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

		$config->vs_gd = $gd_version;

		// Get info about all JEM parts
        $db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select(array('name', 'type', 'enabled', 'manifest_cache'));
		$query->from('#__extensions');
		$query->where(array('name LIKE "%jem%"'));
		$db->setQuery($query);
		$extensions = $db->loadObjectList('name');

		$known_extensions = array('pkg_jem', 'com_jem', 'mod_jem', 'mod_jem_cal', 'mod_jem_calajax',
		                          'mod_jem_banner', 'mod_jem_jubilee', 'mod_jem_teaser', 'mod_jem_wide',
		                          'plg_content_jem', 'plg_content_jemlistevents',
		                          'plg_finder_jem', 'plg_search_jem',
		                          'plg_quickicon_jem', 'Quick Icon - JEM',
		                          'plg_jem_comments', 'plg_jem_mailer', 'plg_jem_demo',
		                          'AcyMailing Tag : insert events from JEM 2.1+');

		foreach ($extensions as $name => $extension) {
			if (in_array($name, $known_extensions)) {
				$manifest = json_decode($extension->manifest_cache, true);
				$extension->version      = (!empty($manifest) && array_key_exists('version',      $manifest)) ? $manifest['version']      : '?';
				$extension->creationDate = (!empty($manifest) && array_key_exists('creationDate', $manifest)) ? $manifest['creationDate'] : '?';
				$extension->author       = (!empty($manifest) && array_key_exists('author',       $manifest)) ? $manifest['author']       : '?';
				$config->$name = clone $extension;
			}
		}

		return $config;
	}

}
