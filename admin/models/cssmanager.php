<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


/**
 * JEM Cssmanager Model
 *
 */
class JEMModelCssmanager extends JModelLegacy
{
	protected $template = null;

	/**
	 * Internal method to get file properties.
	 *
	 * @param	string The base path.
	 * @param	string The file name.
	 * @return	object
	 *
	 */
	protected function getFile($path, $name)
	{
		$temp = new stdClass;

		$temp->name = $name;
		$temp->exists = file_exists($path.$name);
		$temp->id = urlencode(base64_encode($name));
		return $temp;
	}

	/**
	 * Method to get a list of all the files to edit in a template.
	 *
	 * @return	array	A nested array of relevant files.
	 *
	 */
	public function getFiles()
	{
		// Initialise variables.
		$result	= array();

		jimport('joomla.filesystem.folder');
		$path	= JPath::clean(JPATH_ROOT.'/media/com_jem/');

		// Check if the template path exists.
		if (is_dir($path)) {

			// Handle the CSS files.
			$files = JFolder::files($path.'/css', '\.css$', false, false);

			foreach ($files as $file) {
				$result['css'][] = $this->getFile($path.'/css/', 'css/'.$file);
			}
		} else {
			$this->setError(JText::_('COM_JEM_CSSMANAGER_ERROR_CSS_FOLDER_NOT_FOUND'));
			return false;
		}

		return $result;
	}


	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 */
	protected function populateState()
	{
		$app = JFactory::getApplication('administrator');

		// Load the parameters.
		$params	= JComponentHelper::getParams('com_jem');
		$this->setState('params', $params);
	}


	/**
	 * Detect if option linenumbers is enabled
	 * plugin: codemirror
	 */
	function getStatusLinenumber() {
		$db = $this->getDbo();
		$query = $db->getQuery(true);
		$query->select('params');
		$query->from('#__extensions');
		$query->where(array("type = 'plugin'", "element = 'codemirror'"));
		$db->setQuery($query);
		$manifest = json_decode($db->loadResult(), true);
		return $manifest['linenumbers'];
	}


	/**
	 * Sets parameter values in the component's row of the extension table
	 *
	 * @param $param_array  An array holding the params to store
	 */
	function setStatusLinenumber($status) {
// 		$param_array = array('linenumbers','0');

		// read the existing component value(s)
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('params')
		->from('#__extensions')
		->where(array("type = 'plugin'", "element = 'codemirror'"));

		$db->setQuery($query);
		$params = json_decode($db->loadResult(), true);
		$params['linenumbers'] = $status;

		// add the new variable(s) to the existing one(s)
// 		foreach ($param_array as $name => $value) {
// 			$params[(string) $name] = (string) $value;
// 		}

		// store the combined new and existing values back as a JSON string
		$paramsString = json_encode($params);
		$query = $db->getQuery(true);
		$query->update('#__extensions')
		->set('params = '.$db->quote($paramsString))
		->where(array("type = 'plugin'", "element = 'codemirror'"));

		$db->setQuery($query);
		$db->query();
	}
}
?>