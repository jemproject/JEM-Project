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

			// Remove obsolete files and folder
			$this->deleteObsoleteFiles();

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

				// change category ids in menu items
				$this->updateJemMenuItems195();

				// change category ids in modules
				$this->updateJemModules195();
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

	/**
	 * Helper method that outputs a short JEM header with logo and text
	 */
	private function getHeader() {
		?>
		<img src="../media/com_jem/images/jemlogo.png" alt="" style="float:left; padding-right:20px;" />
		<h1><?php echo JText::_('COM_JEM'); ?></h1>
		<p class="small"><?php echo JText::_('COM_JEM_INSTALLATION_HEADER'); ?></p>
		<?php
	}

	/**
	 * Checks if component is already registered in Joomlas schema table and adds an entry if
	 * neccessary
	 * @param string $versionId The JEM version to add to the schema table
	 */
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

	/**
	 * Remove all obsolete files and folders of previous versions.
	 *
	 * Todo: Enhance the lists on each new version.
	 *
	 * @return void
	 */
	private function deleteObsoleteFiles()
	{
		$files = array(
			// obsolete since JEM 1.9.2
			'/administrator/components/com_jem/controllers/archive.php',
			'/administrator/components/com_jem/models/archive.php',
			'/administrator/components/com_jem/views/archive/view.html.php',
			'/administrator/components/com_jem/views/archive/tmpl/default.php',
			'/components/com_jem/views/calendar/metadata.xml',
			'/components/com_jem/views/categories/metadata.xml',
			'/components/com_jem/views/categoriesdetailed/metadata.xml',
			'/components/com_jem/views/category/metadata.xml',
			'/components/com_jem/views/day/metadata.xml',
			'/components/com_jem/views/editevent/metadata.xml',
			'/components/com_jem/views/editvenue/metadata.xml',
			'/components/com_jem/views/event/metadata.xml',
			'/components/com_jem/views/eventslist/metadata.xml',
			'/components/com_jem/views/myattending/metadata.xml',
			'/components/com_jem/views/myevents/metadata.xml',
			'/components/com_jem/views/myvenues/metadata.xml',
			'/components/com_jem/views/search/metadata.xml',
			'/components/com_jem/views/venue/metadata.xml',
			'/components/com_jem/views/venues/metadata.xml',
			// obsolete since JEM 1.9.3
			'/components/com_jem/views/category/tmpl/default_attachments.php',
			'/components/com_jem/views/category/tmpl/default_table.php',
			'/components/com_jem/views/editevent/tmpl/default_attachments.php',
			'/components/com_jem/views/editvenue/tmpl/default_attachments.php',
			'/components/com_jem/views/eventslist/tmpl/default_table.php',
			'/components/com_jem/views/venue/tmpl/default_attachments.php',
			'/components/com_jem/views/weekcal/tmpl/default.xml.disabled',
			// obsolete since JEM 1.9.4
			// obsolete since JEM 1.9.5
			'/administrator/components/com_jem/help/en-GB/archive.html',
			'/administrator/components/com_jem/help/en-GB/toolbars/apunedch.png',
			'/administrator/components/com_jem/help/en-GB/toolbars/asch.png',
			'/administrator/components/com_jem/help/en-GB/toolbars/asuch.png',
			'/administrator/components/com_jem/help/en-GB/toolbars/dbh.png',
			'/administrator/components/com_jem/help/en-GB/toolbars/nedh.png',
			'/administrator/components/com_jem/help/en-GB/toolbars/punedh.png',
			'/administrator/components/com_jem/help/en-GB/toolbars/udh.png',
			'/administrator/components/com_jem/help/images/icon-16-attention.png',
			'/administrator/components/com_jem/help/images/icon-16-hint.png',
			'/administrator/components/com_jem/models/jem.php',
			'/administrator/components/com_jem/models/fields/imageselectevent.php',
			'/administrator/components/com_jem/views/category/tmpl/default.php',
			'/administrator/components/com_jem/views/category/tmpl/default_attachments.php',
			'/administrator/components/com_jem/views/event/tmpl/addvenue.php',
			'/administrator/components/com_jem/views/group/tmpl/default.php',
			'/administrator/components/com_jem/views/jem/view.html.php',
			'/administrator/components/com_jem/views/jem/tmpl/default.php',
			'/administrator/components/com_jem/views/settings/tmpl/default_basic.php',
			'/administrator/components/com_jem/views/settings/tmpl/default_eventpage.php',
			'/administrator/components/com_jem/views/settings/tmpl/default_navigation.php',
			'/components/com_jem/models/myattending.php',
			'/components/com_jem/views/editevent/tmpl/default.xml',
			'/components/com_jem/views/myattending/view.html.php',
			'/components/com_jem/views/myattending/tmpl/default.php',
			'/components/com_jem/views/myattending/tmpl/default.xml',
			'/components/com_jem/views/myattending/tmpl/default_attending.php',
			'/media/css/calendarweek.css',
			'/media/css/gmapsoverlay.css',
			'/media/css/picker.css',
			'/media/images/evlogo.png',
			'/media/js/gmapsoverlay.js',
			'/media/js/picker.js',
			'/media/js/recurrencebackend.js',
			'/media/js/seobackend.js',
		);

		// TODO There is an issue while deleting folders using the ftp mode
		$folders = array(
			// obsolete since JEM 1.9.2
			'/administrator/components/com_jem/views/archive/tmpl',
			'/administrator/components/com_jem/views/archive',
			// obsolete since JEM 1.9.3
			// obsolete since JEM 1.9.4
			// obsolete since JEM 1.9.5
			'/administrator/components/com_jem/views/jem/tmpl',
			'/administrator/components/com_jem/views/jem',
			'/components/com_jem/views/myattending/tmpl',
			'/components/com_jem/views/myattending',
		);

		foreach ($files as $file) {
			if (JFile::exists(JPATH_ROOT . $file) && !JFile::delete(JPATH_ROOT . $file)) {
				echo JText::sprintf('FILES_JOOMLA_ERROR_FILE_FOLDER', $file).'<br />';
			}
		}

		foreach ($folders as $folder) {
			if (JFolder::exists(JPATH_ROOT . $folder) && !JFolder::delete(JPATH_ROOT . $folder)) {
				echo JText::sprintf('FILES_JOOMLA_ERROR_FILE_FOLDER', $folder).'<br />';
			}
		}
	}

	/**
	 * Increment category ids in params of menu items related to com_jem.
	 * (required when updating from 1.9.4 or below to 1.9.5 or newer)
	 *
	 * @return void
	 */
	private function updateJemMenuItems195()
	{
		// get all "com_jem..." frontend entries
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('id, link, params');
		$query->from('#__menu');
		$query->where(array("client_id = 0", "link LIKE 'index.php?option=com_jem&view=%'"));
		$query->order('id');
		$db->setQuery($query);
		$items = $db->loadObjectList();

		foreach ($items as $item) {
			// Decode the item params
			$reg = new JRegistry;
			$reg->loadString($item->params);

			// get view
			preg_match('/view=([^&]+)/', $item->link, $matches);
			$view = $matches[1];

			$modified = false;

			switch ($view) {
				/* case A: single category, 0 is valid */
				case 'calendar':
				case 'search':
					// top_category - empty or 0: ok, >0: increment
					$id = (int)$reg->get('top_category', 0);
					if ($id > 0) {
						$reg->set('top_category', $id + 1);
						$modified = true;
					}
					break;
				case 'category':
					// id - empty or 0: ok, >0: increment
					// id exists on calendar layout only where it must be greater zero (a real category)
					// on default layout it's part of 'link' which is changed in 1.9.5.sql
					$id = (int)$reg->get('id', 0);
					if ($id > 0) {
						$reg->set('id', $id + 1);
						$modified = true;
					}
					break;

				/* case B: single category, 0 becomes invalid and must be set to 1 (root) */
				case 'categories':
					// catid - empty or 0: 1, >0: increment
					$id = (int)$reg->get('catid', 0);
					$reg->set('catid', ($id > 0) ? $id + 1 : 1);
					$modified = true;
					break;
				case 'categoriesdetailed':
					// id - empty or 0: 1, >0: increment
					$id = (int)$reg->get('id', 0);
					$reg->set('id', ($id > 0) ? $id + 1 : 1);
					$modified = true;
					break;

				/* case C: list of categories (invalid IDs are removed) or empty */
				case 'eventslist':
					// categoryswitchcats - empty: ok, list of ids >0: increment each
					$catids = $reg->get('categoryswitchcats');
					$newids = array();
					if (!empty($catids) && is_string($catids)) {
						$catids = explode(',', $catids);
						foreach ($catids as $id) {
							$id = (int)trim($id);
							if ($id > 0) {
								$newids[] = $id + 1;
							}
						}
					}
					if (!empty($newids)) {
						$reg->set('categoryswitchcats', implode(',', $newids));
						$modified = true;
					}
					break;

				/* case D: no reference to categories - nothings to do */
				case 'day':
				case 'editevent':
				case 'editvenue':
				case 'event':
				case 'myattendances':
				case 'myevents':
				case 'myvenues':
				case 'venue':
				case 'venues':
				case 'weekcal':
					// nothings to do
					break;

				default:
					// Default case should not be triggered
					//echo 'Oops - ' . $view . ' on ' . $item->id . '<br />';
					break;
			}

			// write back
			if ($modified) {
				// write changed params back into DB
				$query = $db->getQuery(true);
				$query->update('#__menu');
				$query->set('params = '.$db->quote((string)$reg));
				$query->where(array('id = '.$db->quote($item->id)));
				$db->setQuery($query);
				$db->query();
			}
		}
	}

	/**
	 * Increment category ids in params of JEM modules.
	 * (required when updating from 1.9.4 or below to 1.9.5 or newer)
	 *
	 * @return void
	 */
	private function updateJemModules195()
	{
		// get all "mod_jem..." entries
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('id, module, params');
		$query->from('#__modules');
		$query->where(array("client_id = 0", "module LIKE 'mod_jem%'"));
		$query->order('id');
		$db->setQuery($query);
		$items = $db->loadObjectList();

		foreach ($items as $item) {
			// Decode the item params
			$reg = new JRegistry;
			$reg->loadString($item->params);

			$modified = false;

			switch ($item->module) {
				/* case C: list of categories (invalid IDs are removed) or empty */
				case 'mod_jem':
				case 'mod_jem_cal':
				case 'mod_jem_teaser':
				case 'mod_jem_wide':
					// catid - empty: ok, list of ids >0: increment each
					$catids = $reg->get('catid');
					$newids = array();
					if (!empty($catids) && is_string($catids)) {
						$catids = explode(',', $catids);
						foreach ($catids as $id) {
							$id = (int)trim($id);
							if ($id > 0) {
								$newids[] = $id + 1;
							}
						}
					}
					if (!empty($newids)) {
						$reg->set('catid', implode(',', $newids));
						$modified = true;
					}
					break;

				default:
					// Default case should not be triggered
					//echo 'Oops - ' . $item->module . ' on ' . $item->id . '<br />';
					break;
			}

			// write back
			if ($modified) {
				// write changed params back into DB
				$query = $db->getQuery(true);
				$query->update('#__modules');
				$query->set('params = '.$db->quote((string)$reg));
				$query->where(array('id = '.$db->quote($item->id)));
				$db->setQuery($query);
				$db->query();
			}
		}
	}
}
