<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

/**
 * Script file of JEM component
*/
class mod_jem_calInstallerScript
{

	private $name = 'mod_jem_cal';

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
			// Changes between 2.2.2 -> 2.2.3
			if (version_compare($this->oldRelease, '2.2.3-dev1', 'le') && version_compare($this->newRelease, '2.2.3-dev1', 'ge')) {
				// add use_ajax, take over od_jem_calajax which becomes obsolete
				$this->updateParams223();
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
			$reg = new Registry;
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

	/**
	 * Add use_ajax,
	 * take over instances from mod_jem_calajax which becomes obsolete
	 *
	 * @return void
	 */
	private function updateParams223()
	{
		// get all "mod_jem..." entries
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select('id, module, note, params');
		$query->from('#__modules');
		$query->where('module = "' . $this->name . '" OR module = "mod_jem_calajax"');
		$db->setQuery($query);
		$items = $db->loadObjectList();

		$use_ajax = version_compare(JVERSION, '3.2.7', 'ge') ? '1' : '0';
		$obs_params = array('UseJoomlaLanguage', 'locale_override');
		$fix_params = array('cal15q_tooltips_title', 'cal15q_tooltipspl_title');

		foreach ($items as $item) {
			// Decode the item params
			$reg = new Registry;
			$reg->loadString($item->params);

			$mod_params = false;
			$mod_mod = $item->module == 'mod_jem_calajax';

			// add use_ajax if missing
			if (!$reg->exists('use_ajax')) {
				$reg->set('use_ajax', $use_ajax);
				$mod_params = true;
			}

			// adapt text defines
			foreach ($fix_params as $f) {
				$str = $reg->get($f, '');
				$str2 = mb_ereg_replace('MOD_JEM_CALAJAX_', 'MOD_JEM_CAL_', $str);
				if ($str !== $str2) {
					$reg->set($f, $str2);
					$mod_params = true;
				}
			}

			// remove obsolete params
			foreach ($obs_params as $o) {
				if ($reg->exists($o)) {
					$reg->set($o, null);
					$mod_params = true;
				}
			}

			// write back
			if ($mod_params || $mod_mod) {
				// write changed data back into DB
				$query = $db->getQuery(true);
				$query->update('#__modules');
				if ($mod_params) {
					$query->set('params = '.$db->quote((string)$reg));
				}
				if ($mod_mod) { // change module and append a note
					$query->set('module = '.$db->quote($this->name));
					$query->set('note = '.$db->quote(join(' -- ', array($item->note, 'PLEASE CHECK this transfered from obsolete mod_jem_calajax, then uninstall mod_jem_calajax!'))));
				}
				$query->where(array('id = '.$db->quote($item->id)));
				$db->setQuery($query);
				$db->execute();
			}
		}
	}

}
