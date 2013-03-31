<?php
/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.model');
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');

/**
 * EventList Component Cleanup Model
 *
 * @package Joomla
 * @subpackage EventList
 * @since		0.9
 */
class EventListModelCleanup extends JModel
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
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();

		if (JRequest::getCmd('task') == 'cleaneventimg') {
			$target = 'events';
		} else {
			$target = 'venues';
		}
		$this->settarget($target);
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
		$this->_target	 = $target;
	}

	/**
	 * Method to delete the images
	 *
	 * @access	public
	 * @since 0.9
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

		if ($count) {

			$fail = 0;

			foreach ($images as $image)
			{
				if ($image !== JFilterInput::clean($image, 'path')) {
					JError::raiseWarning(100, JText::_('UNABLE TO DELETE').' '.htmlspecialchars($image, ENT_COMPAT, 'UTF-8'));
					$fail++;
					continue;
				}

				$fullPath = JPath::clean(JPATH_SITE.DS.'images'.DS.'eventlist'.DS.$folder.DS.$image);
				$fullPaththumb = JPath::clean(JPATH_SITE.DS.'images'.DS.'eventlist'.DS.$folder.DS.'small'.DS.$image);

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
	 * Method to determine the images to delete
	 *
	 * @access	private
	 * @since 0.9
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
	 * @since 0.9
	 * @return array
	 */
	function _getassigned()
	{
		if ($this->_target == 'events') {
			$field = 'datimage';
		} else {
			$field = 'locimage';
		}

		$query = 'SELECT '.$field.' FROM #__eventlist_'.$this->_target;

		$this->_db->setQuery($query);

		$this->_assigned = $this->_db->loadResultArray();

		return $this->_assigned;
	}

	/**
	 * Method to determine the unassigned images
	 *
	 * @access	private
	 * @since 0.9
	 * @return array
	 */
	function _getavailable()
	{
		// Initialize variables
		$basePath = JPATH_SITE.DS.'images'.DS.'eventlist'.DS.$this->_target;

		$images 	= array ();

		// Get the list of files and folders from the given folder
		$fileList 	= JFolder::files($basePath);

		// Iterate over the files if they exist
		if ($fileList !== false) {
			foreach ($fileList as $file)
			{
				if (is_file($basePath.DS.$file) && substr($file, 0, 1) != '.' && strtolower($file) !== 'index.html') {

					$images[] = $file;

				}
			}
		}

		$this->_unassigned = $images;

		return $this->_unassigned;
	}
}
?>