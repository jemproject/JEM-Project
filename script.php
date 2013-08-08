<?php
defined('_JEXEC') or die;

$db = JFactory::getDBO();
jimport('joomla.filesystem.folder');


/**
 * Script file of JEM component
*/
class com_jemInstallerScript
{
	/**
	 * Method to install the component
	 *
	 * @return void
	 */
	function install($parent)
	{
		//		$parent->getParent()->setRedirectURL('index.php?option=com_jem');
		$error = array(
				'summary' => 0,
				'folders' => 0,
				'settings' => 0
		);
		?>
<table class="adminlist">
	<tr>
		<td valign="top"><img src="../media/com_jem/images/jemlogo.png"
			height="100" width="250" alt="jem Logo" align="left">
		</td>
		<td valign="top" width="100%">
			<h1>JEM</h1>
			<p class="small">
				by <a href="http://www.joomlaeventmanager.net" target="_blank">joomlaeventmanager.net</a><br />
				Released under the terms and conditions of the <a
					href="http://www.gnu.org/licenses/gpl-2.0.html" target="_blank">GNU
					General Public License</a>.
			</p>
		</td>
	</tr>
	<tr>
		<td colspan="2">
			<h2>Installation Status:</h2>
			<h3>Check Folders:</h3> <?php
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
			if ($direxists = JFolder::exists(JPATH_SITE.$createDirs[0])) {
			echo "<p><span style='color:green;'>Success:</span> Directory <i>$createDirs[0]</i> already exists. Skipping creation.</p>";
		} else {
			echo "<p><span style='color:orange;'>Info:</span> Directory <i>$createDirs[0]</i> does NOT exist.</p>";
			echo "<p>Trying to create folder structure:</p>";

			echo "<ul>";
			// Folder creation
			foreach($createDirs as $directory) {
				if ($makedir = JFolder::create(JPATH_SITE.$directory)) {
					echo "<li><span style='color:green;'>Success:</span> Directory <i>$directory</i> created.</li>";
				} else {
					echo "<li><span style='color:red;'>Error:</font> Directory <i>$directory</i> NOT created.</li>";
					$error['folders']++;
				}
			}
			echo "</ul>";
		}

		if($error['folders']) {
		?>
			<p>
				Please check the existance of the listed directories.<br /> If they
				do not exist, create them and ensure JEM has write access to these
				directories.<br /> If you don't so, you prevent JEM from functioning
				correctly. (You can't upload images).
			</p> <?php
		}

		echo "<h3>Settings</h3>";

		$db = JFactory::getDBO();
		$query = "SELECT id FROM #__jem_settings";
		$db->setQuery($query);
		$db->loadResult();

		if(!$db->loadResult()) {
			$query = "INSERT INTO #__jem_settings VALUES (1, 2, 1, 1, 1, 1, 1, 1, '1', '1', '100%', '20%', '40%', '20%', '', "
	 				."'D, j. F Y', 'j.m.y', '%H.%M', 'h', 1, 1, 1, 1, 1, 1, 1, 1, -2, 0, 'example@example.com', 0, '1000', -2, -2, -2, 1, '', "
					."1, 1, 1, 1, '100', '100', '100', 1, 1, 0, 0, 1, 2, 2, -2, 1, 0, -2, 1, 0, 1, '[title], [a_name], [categories], [times]', "
					."'The event titled [title] starts on [dates]!', 1, 0, '0', 0, 1, 0, '1364604520', '', '', 'COM', 'US', '100', '10%', '10', "
					."'0', 0, 1, 1, 1, 1, 1, 1, 1, 0, 0, '10%', 1, 30, 1, 1, 'media/com_jem/attachments', '1000', "
					."'txt,csv,htm,html,xml,css,doc,xls,zip,rtf,ppt,pdf,swf,flv,avi,wmv,mov,jpg,jpeg,gif,png,tar.gz', 0, '365', 100, 1, '','1.9.1','')";
			$db->setQuery($query);

			if (!$db->query()) {
				echo "<p><span style='color:red;'>Error:</span> Saving default settings failed.</p>";
				$error['settings']++;
			} else {
				echo "<p><span style='color:green;'>Success:</span> Saved default settings.</p>";
			}
		} else {
			echo "<p><span style='color:green;'>Success:</span> Found existing (default) settings.</p>";
		}

		echo "<h3>Summary</h3>";

		foreach ($error as $k => $v) {
			if($k != 'summary') {
				$error['summary'] += $v;
			}
		}

		if($error['summary']) {
		?>
			<p style='color: red;'>
				<b>JEM was NOT installed successfully!</b>
			</p> <?php
		} else {
		?>
			<p style='color: green;'>
				<b>JEM was installed successfully!</b> Have Fun.
			</p> <?php
		}
		?>
		</td>
	</tr>
</table>
<?php
	}

	/**
	 * method to uninstall the component
	 *
	 * @return void
	 */
	function uninstall($parent)
	{
		?>
<h2>Uninstall Status:</h2>
<?php
echo '<p>' . JText::_('COM_JEM_UNINSTALL_TEXT') . '</p>';
	}

	/**
	 * method to update the component
	 *
	 * @return void
	 */
	function update($parent)
	{
		?>
<h2>Update Status:</h2>
<?php
echo '<p>' . JText::sprintf('COM_JEM_UPDATE_TEXT', $parent->get('manifest')->version) . '</p>';
	}

	/**
	 * method to run before an install/update/uninstall method
	 *
	 * @return void
	 */
	function preflight($type, $parent)
	{
		$jversion = new JVersion();
		$oldRelease = $this->getParam('version');
		
		// Minimum Joomla version as per Manifest file
		$requiredJoomlaVersion = $parent->get('manifest')->attributes()->version;

		// abort if the current Joomla release is older than required version
		if(version_compare($jversion->getShortVersion(), $requiredJoomlaVersion, 'lt')) {
			Jerror::raiseWarning(100, JText::sprintf('COM_JEM_PREFLIGHT_WRONG_JOOMLA_VERSION', $requiredJoomlaVersion));
			return false;
		}

		// abort if the release being installed is not newer than the currently installed version
		if ($type == 'update') {
			// Installed component version
			$oldRelease = $this->getParam('version');
						
			// Installing component version as per Manifest file
			$newRelease = $parent->get('manifest')->version;

			if (version_compare($newRelease, $oldRelease, 'le')) {
				Jerror::raiseWarning(100, JText::sprintf('COM_JEM_PREFLIGHT_INCORRECT_VERSION_SEQUENCE', $oldRelease, $newRelease));
				return false;
			}
		} 
			
			if($oldRelease == 1.9)
			{
		
			$db = JFactory::getDbo();
			$query = "SELECT extension_id FROM #__extensions WHERE type='component' and element='com_jem'";
			$db->setQuery($query);

			$extensionid = $db->loadResult();
			$versionid = '1.9';
			
		
			// Create a new query object.
			$query = $db->getQuery(true);
 
			// Insert columns.
			$columns = array('extension_id', 'version_id');
 
			// Insert values.
			$values = array($extensionid, $versionid);
 
			// Prepare the insert query.
			$query
    		->insert($db->quoteName('#__schemas'))
    		->columns($db->quoteName($columns))
    		->values(implode(',', $values));
 
			// Reset the query using our newly populated query object.
			$db->setQuery($query);
			$db->query();
			
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
	}

	/**
	 * Get a parameter from the manifest file (actually, from the manifest cache).
	 *
	 * @param $name  The name of the parameter
	 *
	 * @return The parameter
	 */
	function getParam($name) {
		$db = JFactory::getDbo();
		$db->setQuery('SELECT manifest_cache FROM #__extensions WHERE type = "component" and element = "com_jem"');
		$manifest = json_decode($db->loadResult(), true);
		return $manifest[$name];
	}

	/**
	 * Sets parameter values in the component's row of the extension table
	 *
	 * @param $param_array  An array holding the params to store
	 */
	function setParams($param_array) {
		if (count($param_array) > 0) {
			// read the existing component value(s)
			$db = JFactory::getDbo();
			$db->setQuery('SELECT params FROM #__extensions WHERE type = "component" and element = "com_jem"');
			$params = json_decode($db->loadResult(), true);

			// add the new variable(s) to the existing one(s)
			foreach ($param_array as $name => $value) {
				$params[(string) $name] = (string) $value;
			}

			// store the combined new and existing values back as a JSON string
			$paramsString = json_encode($params);
			$db->setQuery('UPDATE #__extensions SET params = ' .
					$db->quote($paramsString) .
					' WHERE name = "com_jem"');
			$db->query();
		}
	}
}
