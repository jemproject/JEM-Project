<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Script file of JEM component
*/
class mod_jemInstallerScript
{

	private $name = 'mod_jem';

	private $oldRelease = "";
	private $newRelease = "";

	/**
	 * method to run before an install/update/uninstall method
	 * (it seams method is not called on uninstall)
	 *
	 * @return void
	 */
	function preflight($type, $parent)
	{
		// abort if the release being installed is not newer than the currently installed version
		if (strtolower($type) == 'update') {
			// Installed component version
			$this->oldRelease = $this->getParam('version');

			// Installing component version as per Manifest file
			$this->newRelease = (string) $parent->getManifest()->version;

			if (version_compare($this->newRelease, $this->oldRelease, 'lt')) {
				return false;
			}
		}
	}

	/**
	 * Method to run after an install/update/uninstall method
	 * (it seams method is not called on uninstall)
	 *
	 * @return void
	 */
	function postflight($type, $parent)
	{
		if (strtolower($type) == 'update') {
			// Changes between 2.1.5 -> 2.1.6
			if (version_compare($this->oldRelease, '2.1.6-rc3', 'le') && version_compare($this->newRelease, '2.1.6-rc3', 'ge')) {
				// change category/venue/event ID lists from string to array
				$this->updateParams216();
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
	private function getParam($name)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select('manifest_cache')->from('#__extensions')->where(array("type = 'module'", "element = '".$this->name."'"));
		$db->setQuery($query);
		$manifest = json_decode($db->loadResult(), true);
		return $manifest[$name];
	}

	/**
	 * Increment category ids in params of JEM modules.
	 * (required when updating from 1.9.4 or below to 1.9.5 or newer)
	 *
	 * @return void
	 */
	private function updateParams216()
	{
		// get all "mod_jem..." entries
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select('id, params');
		$query->from('#__modules');
		$query->where('module = "' . $this->name . '"');
		$db->setQuery($query);
		$items = $db->loadObjectList();

		foreach ($items as $item) {
			// Decode the item params
			$reg = new JRegistry;
			$reg->loadString($item->params);

			$modified = false;

			// catid - if string then convert to array
			$ids = $reg->get('catid');
			if (!empty($ids) && is_string($ids)) {
				$reg->set('catid', explode(',', $ids));
				$modified = true;
			}
			// venid - if string then convert to array
			$ids = $reg->get('venid');
			if (!empty($ids) && is_string($ids)) {
				$reg->set('venid', explode(',', $ids));
				$modified = true;
			}

			// write back
			if ($modified) {
				// write changed params back into DB
				$query = $db->getQuery(true);
				$query->update('#__modules');
				$query->set('params = '.$db->quote((string)$reg));
				$query->where(array('id = '.$db->quote($item->id)));
				$db->setQuery($query);
				$db->execute();
			}
		}
	}

}
