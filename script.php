<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

$db = JFactory::getDBO();
jimport('joomla.filesystem.folder');


/**
 * Script file of JEM component
*/
class com_jemInstallerScript
{
	private $oldRelease = "";
	private $newRelease = "";

	/**
	 * Method to install the component
	 *
	 * @return void
	 */
	function install($parent)
	{
		$error = array(
				'summary' => 0,
				'folders' => 0
		);

		$this->getHeader();
		?>

		<h2><?php echo JText::_('COM_JEM_INSTALL_STATUS'); ?>:</h2>
		<h3><?php echo JText::_('COM_JEM_INSTALL_CHECK_FOLDERS'); ?>:</h3> <?php

		$imageDir = "/images/jem";
		$createDirs = array(
				$imageDir,
				$imageDir.'/categories',
				$imageDir.'/categories/small',
				$imageDir.'/events',
				$imageDir.'/events/small',
				$imageDir.'/venues',
				$imageDir.'/venues/small'
		);

		// Check for existance of /images/jem directory
		if (JFolder::exists(JPATH_SITE.$createDirs[0])) {
			echo "<p><span style='color:green;'>".JText::_('COM_JEM_INSTALL_SUCCESS').":</span> ".
				JText::sprintf('COM_JEM_INSTALL_DIRECTORY_EXISTS_SKIP', $createDirs[0])."</p>";
		} else {
			echo "<p><span style='color:orange;'>".JText::_('COM_JEM_INSTALL_INFO').":</span> ".
				JText::sprintf('COM_JEM_INSTALL_DIRECTORY_NOT_EXISTS', $createDirs[0])."</p>";
			echo "<p>".JText::_('COM_JEM_INSTALL_DIRECTORY_TRY_CREATE').":</p>";

			echo "<ul>";
			// Folder creation
			foreach($createDirs as $directory) {
				if (JFolder::create(JPATH_SITE.$directory)) {
					echo "<li><span style='color:green;'>".JText::_('COM_JEM_INSTALL_SUCCESS').":</span> ".
						JText::sprintf('COM_JEM_INSTALL_DIRECTORY_CREATED', $directory)."</li>";
				} else {
					echo "<li><span style='color:red;'>".JText::_('COM_JEM_INSTALL_ERROR').":</span> ".
						JText::sprintf('COM_JEM_INSTALL_DIRECTORY_NOT_CREATED', $directory)."</li>";
					$error['folders']++;
				}
			}
			echo "</ul>";
		}

		if($error['folders']) {
			echo "<p>".JText::_('COM_JEM_INSTALL_DIRECTORY_CHECK_EXISTANCE')."</p>";
		}

		echo "<h3>".JText::_('COM_JEM_INSTALL_SETTINGS')."</h3>";

		$db = JFactory::getDBO();
		$query = $db->getQuery(true);
		$query->select('id')->from('#__jem_settings');
		$db->setQuery($query);
		$db->loadResult();

		if($db->loadResult()) {
			echo "<p><span style='color:green;'>".JText::_('COM_JEM_INSTALL_SUCCESS').":</span> ".
				JText::_('COM_JEM_INSTALL_FOUND_SETTINGS')."</p>";
		}

		echo "<h3>".JText::_('COM_JEM_INSTALL_SUMMARY')."</h3>";

		foreach ($error as $k => $v) {
			if($k != 'summary') {
				$error['summary'] += $v;
			}
		}

		if($error['summary']) {
		?>
			<p style='color: red;'>
				<b><?php echo JText::_('COM_JEM_INSTALL_INSTALLATION_NOT_SUCCESSFUL'); ?></b>
			</p>
		<?php
		} else {
		?>
			<p style='color: green;'>
				<b><?php echo JText::_('COM_JEM_INSTALL_INSTALLATION_SUCCESSFUL'); ?></b>
			</p> <?php
		}
		
		
		$param_array = array(
				"event_comunoption"=>"0",
				"event_comunsolution"=>"0",
				"event_show_author"=>"1",
				"event_lg"=>"",			
				"event_link_author"=>"1",
				"event_show_contact"=>"1",
				"event_link_contact"=>"1",
				"event_show_description"=>"1",
				"event_show_detailsadress"=>"1",
				"event_show_detailstitle"=>"1",
				"event_show_detlinkvenue"=>"1",
				"event_show_hits"=>"0",
				"event_show_locdescription"=>"1",
				"event_show_mapserv"=>"0",
				"event_show_print_icon"=>"1",
				"event_show_email_icon"=>"1",
				"event_show_ical_icon"=>"1",
				"event_tld"=>"",
				"editevent_show_meta_option"=>"0",
				"editevent_show_attachment_tab"=>"0",
				"editevent_show_other_tab"=>"0",
				"global_display"=>"1",
				"global_regname"=>"1",
				"global_show_archive_icon"=>"1",
				"global_show_filter"=>"1",
				"global_show_email_icon"=>"1",
				"global_show_ical_icon"=>"1",
				"global_show_icons"=>"1",
				"global_show_locdescription"=>"1",
				"global_show_print_icon"=>"1",
				"global_show_timedetails"=>"1",
				"global_show_detailsadress"=>"1",
				"global_show_detlinkvenue"=>"1",
				"global_show_mapserv"=>"0",
				"global_tld"=>"",
				"global_lg"=>""
		);
		
		$this->setGlobalAttribs($param_array);
		
	}

	/**
	 * method to uninstall the component
	 *
	 * @return void
	 */
	function uninstall($parent)
	{
		$this->getHeader(); ?>
		<h2><?php echo JText::_('COM_JEM_UNINSTALL_STATUS'); ?>:</h2>
		<p><?php echo JText::_('COM_JEM_UNINSTALL_TEXT'); ?></p>
		<?php
	}

	/**
	 * method to update the component
	 *
	 * @return void
	 */
	function update($parent)
	{
		$this->getHeader(); ?>
		<h2><?php echo JText::_('COM_JEM_UPDATE_STATUS'); ?>:</h2>
		<p><?php echo JText::sprintf('COM_JEM_UPDATE_TEXT', $parent->get('manifest')->version); ?></p>;
		<?php
	}

	/**
	 * method to run before an install/update/uninstall method
	 *
	 * @return void
	 */
	function preflight($type, $parent)
	{
		// Minimum required PHP version
		$minPhpVersion = "5.3.1";

		// Abort if PHP release is older than required version
		if(version_compare(PHP_VERSION, $minPhpVersion, '<')) {
			Jerror::raiseWarning(100, JText::sprintf('COM_JEM_PREFLIGHT_WRONG_PHP_VERSION', $minPhpVersion, PHP_VERSION));
			return false;
		}

		// Minimum Joomla version as per Manifest file
		$minJoomlaVersion = $parent->get('manifest')->attributes()->version;

		// abort if the current Joomla release is older than required version
		$jversion = new JVersion();
		if(version_compare($jversion->getShortVersion(), $minJoomlaVersion, '<')) {
			Jerror::raiseWarning(100, JText::sprintf('COM_JEM_PREFLIGHT_WRONG_JOOMLA_VERSION', $minJoomlaVersion));
			return false;
		}

		// abort if the release being installed is not newer than the currently installed version
		if ($type == 'update') {
			// Installed component version
			$this->oldRelease = $this->getParam('version');

			// Installing component version as per Manifest file
			$this->newRelease = $parent->get('manifest')->version;

			if (version_compare($this->newRelease, $this->oldRelease, 'lt')) {
				Jerror::raiseWarning(100, JText::sprintf('COM_JEM_PREFLIGHT_INCORRECT_VERSION_SEQUENCE', $this->oldRelease, $this->newRelease));
				return false;
			}

			// Initialize schema table if necessary
			$this->initializeSchema($this->oldRelease);
		}

		// $type is the type of change (install, update or discover_install)
		echo '<p>' . JText::_('COM_JEM_PREFLIGHT_' . $type . '_TEXT') . '</p>';
	}

	/**
	 * Method to run after an install/update/uninstall method
	 *
	 * @return void
	 */
	function postflight($type, $parent)
	{
		// $type is the type of change (install, update or discover_install)
		echo '<p>' . JText::_('COM_JEM_POSTFLIGHT_' . $type . '_TEXT') . '</p>';

		if ($type == 'update') {
			// Category changes between 1.9.4 -> 1.9.5
			if (version_compare($this->oldRelease, '1.9.5', 'lt') && version_compare($this->newRelease, '1.9.4', 'gt')) {
				JTable::addIncludePath(JPATH_ROOT.'/administrator/components/com_jem/tables');
				$categoryTable = JTable::getInstance('Category', 'JEMTable');
				$categoryTable->rebuild();
			}
		}
	}

	/**
	 * Get a parameter from the manifest file (actually, from the manifest cache).
	 *
	 * @param $name  The name of the parameter
	 *
	 * @return The parameter
	 */
	private function getParam($name) {
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('manifest_cache')->from('#__extensions')->where(array("type = 'component'", "element = 'com_jem'"));
		$db->setQuery($query);
		$manifest = json_decode($db->loadResult(), true);
		return $manifest[$name];
	}

	/**
	 * Sets parameter values in the component's row of the extension table
	 *
	 * @param $param_array  An array holding the params to store
	 */
	private function setParams($param_array) {
		if (count($param_array) > 0) {
			// read the existing component value(s)
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('params')->from('#__extensions')->where(array("type = 'component'", "element = 'com_jem'"));
			$db->setQuery($query);
			$params = json_decode($db->loadResult(), true);

			// add the new variable(s) to the existing one(s)
			foreach ($param_array as $name => $value) {
				$params[(string) $name] = (string) $value;
			}

			// store the combined new and existing values back as a JSON string
			$paramsString = json_encode($params);
			$query = $db->getQuery(true);
			$query->update('#__extensions')
				->set('params = '.$db->quote($paramsString))
				->where(array("type = 'component'", "element = 'com_jem'"));
			$db->setQuery($query);
			$db->query();
		}
	}
	
	/**
	 * Sets globalattrib values in the settings table
	 *
	 * @param $param_array  An array holding the params to store
	 */
	private function setGlobalAttribs($param_array) {
		if (count($param_array) > 0) {
			// read the existing component value(s)
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('globalattribs')->from('#__jem_settings');
			$db->setQuery($query);
			$params = json_decode($db->loadResult(), true);
	
			// add the new variable(s) to the existing one(s)
			foreach ($param_array as $name => $value) {
				$params[(string) $name] = (string) $value;
			}
	
			// store the combined new and existing values back as a JSON string
			$paramsString = json_encode($params);
			$query = $db->getQuery(true);
			$query->update('#__jem_settings')
			->set('globalattribs = '.$db->quote($paramsString));
			$db->setQuery($query);
			$db->query();
		}
	}

	private function getHeader() {
		?>
		<img src="../media/com_jem/images/jemlogo.png" alt="" style="float:left; padding-right:20px;" />
		<h1><?php echo JText::_('COM_JEM'); ?></h1>
	 	<p class="small"><?php echo JText::_('COM_JEM_INSTALLATION_HEADER'); ?></p>
		<?php
	}

	private function initializeSchema($versionId) {
		$db = JFactory::getDbo();

		// Get extension ID of JEM
		$query = $db->getQuery(true);
		$query->select('extension_id')->from('#__extensions')->where(array("type='component'", "element='com_jem'"));
		$db->setQuery($query);
		$extensionId = $db->loadResult();

		if(!$extensionId) {
			// This is a fresh installation, return
			return;
		}

		// Check if an entry already exists in schemas table
		$query = $db->getQuery(true);
		$query->select('version_id')->from('#__schemas')->where('extension_id = '.$extensionId);
		$db->setQuery($query);

		if($db->loadResult()) {
			// Entry exists, return
			return;
		}

		// Insert extension ID and old release version number into schemas table
		$query = $db->getQuery(true);
		$query->insert('#__schemas')
			->columns($db->quoteName(array('extension_id', 'version_id')))
			->values(implode(',', array($extensionId, $db->quote($versionId))));

		$db->setQuery($query);
		$db->query();
	}
}
