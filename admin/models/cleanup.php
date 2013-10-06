<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');

/**
 * JEM Component Cleanup Model
 *
 * @package JEM
 *
 */
class JEMModelCleanup extends JModelLegacy
{
	/**
	 * target
	 *
	 * @var string
	 */
	var $_target = null;

	/**
	 * images to delete
	 *
	 * @var array
	 */
	var $_images = null;

	/**
	 * assigned images
	 *
	 * @var array
	 */
	var $_assigned = null;

	/**
	 * unassigned images
	 *
	 * @var array
	 */
	var $_unassigned = null;

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();

		$jinput = JFactory::getApplication()->input;
		$task = $jinput->get('task', '', 'cmd');

		if ($task == 'cleaneventimg') {
			$target = 'events';
			$this->settarget($target);
		} elseif ($task == 'cleanvenueimg') {
			$target = 'venues';
			$this->settarget($target);
		} elseif ($task == 'cleancategoryimg') {
			$target = 'categories';
			$this->settarget($target);
		}
	}

	/**
	 * Method to set the target
	 *
	 * @access	public
	 * @param	string the target directory
	 */
	function settarget($target)
	{
		// Set id and wipe data
		$this->_target = $target;
	}

	/**
	 * Method to delete the images
	 *
	 * @access	public
	 * @return int
	 */
	function delete()
	{
		// Set FTP credentials, if given
		jimport('joomla.client.helper');
		JClientHelper::setCredentialsFromRequest('ftp');

		// Get some data from the request
		$images	= $this->_getImages();
		$folder = $this->_target;

		$count = count($images);
		$fail = 0;

		if ($count) {
			foreach ($images as $image)
			{
				if ($image !== JFilterInput::getInstance()->clean($image, 'path')) {
					JError::raiseWarning(100, JText::_('COM_JEM_UNABLE_TO_DELETE').' '.htmlspecialchars($image, ENT_COMPAT, 'UTF-8'));
					$fail++;
					continue;
				}

				$fullPath = JPath::clean(JPATH_SITE.'/images/jem/'.$folder.'/'.$image);
				$fullPaththumb = JPath::clean(JPATH_SITE.'/images/jem/'.$folder.'/small/'.$image);

				if (is_file($fullPath)) {
					JFile::delete($fullPath);
					if (JFile::exists($fullPaththumb)) {
						JFile::delete($fullPaththumb);
					}
				}
			}
		}

		$deleted = $count - $fail;

		return $deleted;
	}


	/**
	 * Method to delete the cat_relations table
	 *
	 * @access	public
	 * @return int
	 */
	function truncatecats()
	{
		$db = JFactory::getDbo();

		$db->setQuery('TRUNCATE TABLE ' . $db->quoteName('#__jem_cats_event_relations'));
		$db->query();

		return true;
	}

	/**
	 * Truncates JEM tables with exception of settings table
	 */
	public function truncateAllData() {
		$tables = array("attachments",
			"categories",
			"cats_event_relations",
			"events",
			"groupmembers",
			"groups",
			"register",
			"venues");

		$db = JFactory::getDbo();

		foreach ($tables as $table) {
			$db->setQuery("TRUNCATE #__jem_".$table);

			if(!$db->query()) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Method to count the cat_relations table
	 *
	 * @access	public
	 * @return int
	 */
	function getCountcats()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select(array('*'));
		$query->from('#__jem_cats_event_relations');
		$db->setQuery($query);
		$db->query();

		$total = $db->loadObjectList();

		return count($total);
	}



	/**
	 * Method to determine the images to delete
	 *
	 * @access	private
	 * @return array
	 */
	function _getImages()
	{
		$this->_images = array_diff($this->_getavailable(), $this->_getassigned());

		return $this->_images;
	}

	/**
	 * Method to determine the assigned images
	 *
	 * @access	private
	 * @return array
	 */
	function _getassigned()
	{
		if ($this->_target == 'events') {
			$field = 'datimage';
		} elseif ($this->_target == 'venues') {
			$field = 'locimage';
		} elseif ($this->_target == 'categories') {
			$field = 'image';
		}

		$query = 'SELECT '.$field.' FROM #__jem_'.$this->_target;

		$this->_db->setQuery($query);
		$this->_assigned = $this->_db->loadColumn();

		return $this->_assigned;
	}

	/**
	 * Method to determine the unassigned images
	 *
	 * @access	private
	 * @return array
	 */
	function _getavailable()
	{
		// Initialize variables
		$basePath = JPATH_SITE.'/images/jem/'.$this->_target;

		$images = array ();

		// Get the list of files and folders from the given folder
		$fileList = JFolder::files($basePath);

		// Iterate over the files if they exist
		if ($fileList !== false) {
			foreach ($fileList as $file)
			{
				if (is_file($basePath.'/'.$file) && substr($file, 0, 1) != '.') {
					$images[] = $file;
				}
			}
		}

		$this->_unassigned = $images;

		return $this->_unassigned;
	}
}
?>