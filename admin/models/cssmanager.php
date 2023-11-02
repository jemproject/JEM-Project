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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;

/**
 * Model-CSSManager
 */
class JemModelCssmanager extends JModelLegacy
{
	/**
	 * Internal method to get file properties.
	 *
	 * @param  string The base path.
	 * @param  string The file name.
	 * @return object
	 */
	protected function getFile($path, $name)
	{
		$temp = new stdClass;

		$temp->name = $name;
		$temp->exists = file_exists($path.$name);
		$temp->id = base64_encode($name);

		if ($temp->exists) {
			$ext =  File::getExt($path.$name);
				if ($ext != 'css') {
					# the file is valid but the extension not so let's return false
					$temp->ext = false;
				} else {
					$temp->ext = true;
				}
		}

		return $temp;
	}

	/**
	 * Internal method to get file properties.
	 *
	 * @param  string The base path.
	 * @param  string The file name.
	 * @return object
	 */
	protected function getCustomFile($path, $name)
	{
		$temp = new stdClass;
		$temp->name = $name;
		$temp->exists = file_exists($path.$name);

		$filename = 'custom#:'.$name;
		$temp->id = base64_encode($filename);

		if ($temp->exists) {
			$ext =  File::getExt($path.$name);
			if ($ext != 'css') {
				# the file is valid but the extension not so let's return false
				$temp->ext = false;
			} else {
				$temp->ext = true;
			}
		}

		return $temp;
	}

	/**
	 * Method to get a list of all the files to edit in a template.
	 *
	 * @return array A nested array of relevant files.
	 */
	public function getFiles()
	{
		// Initialise variables.
		$result = array();

		$path = JPath::clean(JPATH_ROOT.'/media/com_jem/');

		// Check if the template path exists.
		if (is_dir($path)) {
			// Handle the CSS files.
			$files = Folder::files($path.'/css', '\.css$', false, false);

			foreach ($files as $file) {
				$result['css'][] = $this->getFile($path.'/css/', $file);
			}
		} else {
			$this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_CSS_FOLDER_NOT_FOUND'));
			return false;
		}

		# define array with custom css files
		$settings = JemHelper::retrieveCss();

		$custom = array();
		$custom[] = $settings->get('css_backend_customfile');
		$custom[] = $settings->get('css_calendar_customfile');
		$custom[] = $settings->get('css_colorpicker_customfile');
		$custom[] = $settings->get('css_geostyle_customfile');
		$custom[] = $settings->get('css_googlemap_customfile');
		$custom[] = $settings->get('css_jem_customfile');
		$custom[] = $settings->get('css_print_customfile');

		foreach ($custom as $cfile)
		{
			if ($cfile) {
				$rf = $this->getCustomFile($path.'css/custom/',$cfile);
				if ($rf->exists && $rf->ext) {
					$result['custom'][] = $rf;
				}
			}

		}

		return $result;
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * @Note  Calling getState in this method will result in recursion.
	 */
	protected function populateState()
	{
		$app = Factory::getApplication('administrator');

		// Load the parameters.
		$params = JComponentHelper::getParams('com_jem');
		$this->setState('params', $params);
	}

	/**
	 * Detect if option linenumbers is enabled
	 * plugin: codemirror
	 */
	public function getStatusLinenumber()
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select('params');
		$query->from('#__extensions');
		$query->where(array("type = 'plugin'", "element = 'codemirror'"));
		$db->setQuery($query);
		$manifest = json_decode($db->loadResult(), true);
		return array_key_exists('linenumbers', $manifest) ? $manifest['linenumbers'] : false;
	}

	/**
	 * Sets parameter values in the component's row of the extension table
	 *
	 * @param $param_array An array holding the params to store
	 */
	public function setStatusLinenumber($status)
	{
		// read the existing component value(s)
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select('params')
		      ->from('#__extensions')
		      ->where(array("type = 'plugin'", "element = 'codemirror'"));

		$db->setQuery($query);
		$params = json_decode($db->loadResult(), true);
		$params['linenumbers'] = $status;

		// store the combined new and existing values back as a JSON string
		$paramsString = json_encode($params);
		$query = $db->getQuery(true);
		$query->update('#__extensions')
		      ->set('params = '.$db->quote($paramsString))
		      ->where(array("type = 'plugin'", "element = 'codemirror'"));

		$db->setQuery($query);
		$db->execute();
	}
}
?>
