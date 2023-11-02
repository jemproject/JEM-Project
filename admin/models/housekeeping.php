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

jimport('joomla.application.component.model');
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');

/**
 * Housekeeping-Model
 */
class JemModelHousekeeping extends JModelLegacy
{
	const EVENTS = 1;
	const VENUES = 2;
	const CATEGORIES = 3;

	/**
	 * Map logical name to folder and db names
	 * @var stdClass
	 */
	private $map = null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		$map = array();
		$map[JemModelHousekeeping::EVENTS] = array("folder" => "events", "table" => "events", "field" => "datimage");
		$map[JemModelHousekeeping::VENUES] = array("folder" => "venues", "table" => "venues", "field" => "locimage");
		$map[JemModelHousekeeping::CATEGORIES] = array("folder" => "categories", "table" => "categories", "field" => "image");
		$this->map = $map;
	}

	/**
	 * Method to delete the images
	 *
	 * @access public
	 * @return int
	 */
	public function delete($type)
	{
		// Set FTP credentials, if given
		jimport('joomla.client.helper');
		JClientHelper::setCredentialsFromRequest('ftp');

		// Get some data from the request
		$images	= $this->getImages($type);
		$folder = $this->map[$type]['folder'];

		$count = count($images);
		$fail = 0;

		foreach ($images as $image)
		{
			if ($image !== JFilterInput::getInstance()->clean($image, 'path')) {
				Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_UNABLE_TO_DELETE').' '.htmlspecialchars($image, ENT_COMPAT, 'UTF-8'), 'warning');
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

		$deleted = $count - $fail;

		return $deleted;
	}

	/**
	 * Deletes zombie cats_event_relations with no existing event or category
	 * @return boolean
	 */
	public function cleanupCatsEventRelations()
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$db->setQuery('DELETE cat FROM #__jem_cats_event_relations as cat'
				.' LEFT OUTER JOIN #__jem_events as e ON cat.itemid = e.id'
				.' WHERE e.id IS NULL');
		$db->execute();

		$db->setQuery('DELETE cat FROM #__jem_cats_event_relations as cat'
				.' LEFT OUTER JOIN #__jem_categories as c ON cat.catid = c.id'
				.' WHERE c.id IS NULL');
		$db->execute();

		return true;
	}

	/**
	 * Truncates JEM tables with exception of settings table
	 */
	public function truncateAllData()
	{
		$result = true;
		$tables = array('attachments', 'categories', 'cats_event_relations', 'events', 'groupmembers', 'groups', 'register', 'venues');
		$db = Factory::getContainer()->get('DatabaseDriver');

		foreach ($tables as $table) {
			$db->setQuery('TRUNCATE #__jem_'.$table);

			if ($db->execute() === false) {
				// report but continue
				JemHelper::addLogEntry('Error truncating #__jem_'.$table, __METHOD__, JLog::ERROR);
				$result = false;
			}
		}

		$categoryTable = $this->getTable('category', 'JemTable');
		$categoryTable->addRoot();

		return $result;
	}

	/**
	 * Method to count the cat_relations table
	 *
	 * @access public
	 * @return int
	 */
	public function getCountcats()
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select(array('*'));
		$query->from('#__jem_cats_event_relations');
		$db->setQuery($query);
		$db->execute();

		$total = $db->loadObjectList();

		return is_array($total) ? count($total) : 0;
	}

	/**
	 * Method to determine the images to delete
	 *
	 * @access private
	 * @return array
	 */
	private function getImages($type)
	{
		$images = array_diff($this->getAvailable($type), $this->getAssigned($type));

		return $images;
	}

	/**
	 * Method to determine the assigned images
	 *
	 * @access private
	 * @return array
	 */
	private function getAssigned($type)
	{
		$query = 'SELECT '.$this->map[$type]['field'].' FROM #__jem_'.$this->map[$type]['table'];

		$this->_db->setQuery($query);
		$assigned = $this->_db->loadColumn();

		return $assigned;
	}

	/**
	 * Method to determine the unassigned images
	 *
	 * @access private
	 * @return array
	 */
	private function getAvailable($type)
	{
		// Initialize variables
		$basePath = JPATH_SITE.'/images/jem/'.$this->map[$type]['folder'];

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

		return $images;
	}
}
?>
