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
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Filesystem\Path;

/**
 * JEM Component Imagehandler Model
 *
 * @package JEM
 */
class JemModelImagehandler extends BaseDatabaseModel
{
	/**
	 * Array to cache list of images
	 *
	 * @var array
	 */
	protected $_list = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	protected $_pagination = null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		$app        = Factory::getApplication();
		$option     = $app->input->getString('option', 'com_jem');
		$task       = $app->input->getVar('task', '');
		$limit      = $app->getUserStateFromRequest($option.'imageselect'.$task.'limit', 'limit', $app->get('list_limit'), 'int');
		$limitstart = $app->getUserStateFromRequest($option.'imageselect'.$task.'limitstart', 'limitstart', 0, 'int');
		$limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;
		$search     = $app->getUserStateFromRequest($option.'.filter_search', 'filter_search', '', 'string');
		$search     = trim(\Joomla\String\StringHelper::strtolower($search));

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
		$this->setState('search', $search);
	}

	function getState($property = null, $default = null)
	{
		static $set = false;

		if (!$set) {
			$folder = Factory::getApplication()->input->get('folder', '');
			$this->setState('folder', $folder);

			$set = true;
		}

		return parent::getState($property);
	}

	/**
	 * Build imagelist
	 *
	 * @return array $list The imagefiles from a directory to display
	 */
	public function getImages()
	{
		$list = $this->getList();

		$listimg = array();

		$s = $this->getState('limitstart');
		$l = $this->getState('limit');
		$t = $this->getState('total');

		if ($t < ($s + $l)) {
			$l = $t - $s;
		}

		for ($i = $s; $i < $s + $l; $i++)
		{
			$list[$i]->size = $this->_parseSize(filesize($list[$i]->path));

			$info = getimagesize($list[$i]->path);
			if ($info === false) {
				continue; // skip file on error
			}

			$list[$i]->width  = $info[0];
			$list[$i]->height = $info[1];
			//$list[$i]->type = $info[2];
			//$list[$i]->mime = $info['mime'];

			if (($info[0] > 60) || ($info[1] > 60)) {
				$dimensions = $this->_imageResize($info[0], $info[1], 60);
				$list[$i]->width_60  = $dimensions[0];
				$list[$i]->height_60 = $dimensions[1];
			} else {
				$list[$i]->width_60  = $list[$i]->width;
				$list[$i]->height_60 = $list[$i]->height;
			}

			$listimg[] = $list[$i];
		}

		return $listimg;
	}

	/**
	 * Build imagelist
	 *
	 * @return array $list The imagefiles from a directory
	 */
	public function getList()
	{
		// Only process the list once per request
		if (!is_array($this->_list))
		{
			// Get folder from request
			$folder = $this->getState('folder');
			$search = $this->getState('search');

			// Initialize variables
			$basePath = JPATH_SITE.'/images/jem/'.$folder;

			// Get the list of files and folders from the given folder
			$fileList = Folder::files($basePath);

			// Iterate over the files if they exist
			if ($fileList !== false) {
				$this->_list = array();
				foreach ($fileList as $file) {
					if (is_file($basePath.'/'.$file) && substr($file, 0, 1) != '.') {
						if (empty($search) || stristr($file, $search)) {
							$tmp = new CMSObject();
							$tmp->name = $file;
							$tmp->path = Path::clean($basePath.'/'.$file);

							$this->_list[] = $tmp;
						}
					}
				}
			}

			$this->setState('total', is_array($this->_list) ? count($this->_list) : 0);
		}

		return $this->_list;
	}

	/**
	 * Method to get a pagination object for the images
	 *
	 * @access public
	 * @return integer
	 */
	public function getPagination()
	{
		if (empty($this->_pagination)) {
			$this->_pagination = new Pagination($this->getState('total'), $this->getState('limitstart'), $this->getState('limit'));
		}

		return $this->_pagination;
	}

	/**
	 * Build display size
	 *
	 * @return array width and height
	 */
	protected function _imageResize($width, $height, $target)
	{
		if (($width > 0) && ($height > 0)) {
			//takes the larger size of the width and height and applies the
			//formula accordingly...this is so this script will work
			//dynamically with any size image
			if ($width > $height) {
				$percentage = ($target / $width);
			} else {
				$percentage = ($target / $height);
			}

			//gets the new value and applies the percentage, then rounds the value
			$width  = round($width  * $percentage);
			$height = round($height * $percentage);
		}

		return array($width, $height);
	}

	/**
	 * Return human readable size info
	 *
	 * @return string size of image
	 */
	protected function _parseSize($size)
	{
		if ($size < 1024) {
			return $size . ' Bytes';
		} elseif ($size < (1024 * 1024)) {
			return sprintf('%01.2f', $size / 1024.0) . ' kB';
		} else {
			return sprintf('%01.2f', $size / (1024.0 * 1024)) . ' MB';
		}
	}
}
?>
