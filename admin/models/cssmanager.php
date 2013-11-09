<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

// no direct access
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
	
}
?>