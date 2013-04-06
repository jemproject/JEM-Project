<?php
/**
 * $Id$
 * @package Joomla
 * @subpackage Eventlist
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 *
 * Eventlist is maintained by the community located at
 * http://www.joomlaeventmanager.net
 *
 * Eventlist is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * Eventlist is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
defined( '_JEXEC' ) or die;

jimport('joomla.application.component.controller');
jimport('joomla.filesystem.file');

/**
 * EventList Component Imagehandler Controller
 *
 * @package Joomla
 * @subpackage EventList
 * @since 0.9
 */
class EventListControllerImagehandler extends EventListController
{
	/**
	 * Constructor
	 *
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();

		// Register Extra task
		$this->registerTask( 'eventimgup', 	'uploadimage' );
		$this->registerTask( 'venueimgup', 	'uploadimage' );
		$this->registerTask( 'categoriesimgup', 	'uploadimage' );
	}

	/**
	 * logic for uploading an image
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function uploadimage()
	{
		global $app;
		
		// Check for request forgeries
		JSession::checkToken() or die;

		$elsettings = ELAdmin::config();

		$file 		= JRequest::getVar( 'userfile', '', 'files', 'array' );
		$task 		= JRequest::getVar( 'task' );
		
		// Set FTP credentials, if given
		jimport('joomla.client.helper');
		JClientHelper::setCredentialsFromRequest('ftp');
		//$ftp = JClientHelper::getCredentials('ftp');

		//set the target directory
		if ($task == 'venueimgup') {
			$base_Dir = JPATH_SITE.DS.'images'.DS.'eventlist'.DS.'venues'.DS;
		} 
		
		if ($task == 'eventimgup') {
			$base_Dir = JPATH_SITE.DS.'images'.DS.'eventlist'.DS.'events'.DS;
		} 
		
		if ($task == 'categoriesimgup') {
			$base_Dir = JPATH_SITE.DS.'images'.DS.'eventlist'.DS.'categories'.DS;
		} 

		//do we have an upload?
		if (empty($file['name'])) {
			echo "<script> alert('".JText::_( 'COM_JEM_IMAGE_EMPTY' )."'); window.history.go(-1); </script>\n";
			$app->close();
		}

		//check the image
		$check = ELImage::check($file, $elsettings);

		if ($check === false) {
			$app->redirect($_SERVER['HTTP_REFERER']);
		}

		//sanitize the image filename
		$filename = ELImage::sanitize($base_Dir, $file['name']);
		$filepath = $base_Dir . $filename;

		//upload the image
		if (!JFile::upload($file['tmp_name'], $filepath)) {
			echo "<script> alert('".JText::_( 'COM_JEM_UPLOAD_FAILED' )."'); window.history.go(-1); </script>\n";
			$app->close();

		} else {
			echo "<script> alert('".JText::_( 'COM_JEM_UPLOAD_COMPLETE' )."'); window.history.go(-1); window.parent.elSelectImage('$filename', '$filename'); </script>\n";
			$app->close();
		}

	}

	/**
	 * logic to mass delete images
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function delete()
	{
		global $app;

		// Set FTP credentials, if given
		jimport('joomla.client.helper');
		JClientHelper::setCredentialsFromRequest('ftp');

		// Get some data from the request
		$images	= JRequest::getVar( 'rm', array(), '', 'array' );
		$folder = JRequest::getVar( 'folder');

		if (count($images)) {
			foreach ($images as $image)
			{
				if ($image !== JFilterInput::clean($image, 'path')) {
					JError::raiseWarning(100, JText::_('COM_JEM_UNABLE_TO_DELETE').' '.htmlspecialchars($image, ENT_COMPAT, 'UTF-8'));
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

		if ($folder == 'events') {
			$task = 'selecteventimg';
		} 
		
		if ($folder == 'venues') {
			$task = 'selectvenueimg';
		} 
		
		if ($folder == 'categories') {
			$task = 'selectcategoriesimg';
		} 

		$app->redirect('index.php?option=com_jem&view=imagehandler&task='.$task.'&tmpl=component');
	}

}
?>