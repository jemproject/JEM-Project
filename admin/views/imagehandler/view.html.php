<?php
/**
 * @version 1.9 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

defined( '_JEXEC' ) or die;



/**
 * View class for the JEM imageselect screen
 * Based on the Joomla! media component
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewImagehandler extends JViewLegacy {

	/**
	 * Image selection List
	 *
	 * @since 0.9
	 */
	function display($tpl = null) {
		$app 		= JFactory::getApplication();
		$option 	= JRequest::getString('option');
		$document 	= JFactory::getDocument();

		if($this->getLayout() == 'uploadimage') {
			$this->_displayuploadimage($tpl);
			return;
		}

		//get vars
		$task 		= JRequest::getVar('task');
		$search 	= $app->getUserStateFromRequest($option.'.search', 'search', '', 'string');
		$search 	= trim(JString::strtolower($search));

		//set variables
		if ($task == 'selecteventimg') {
			$folder = 'events';
			$task 	= 'eventimg';
			$redi	= 'selecteventimg';
		} else if ($task == 'selectvenueimg') {
			$folder = 'venues';
			$task 	= 'eventimg';
			$redi	= 'selecteventimg';
		} else if ($task == 'selectcategoriesimg') {
			$folder = 'categories';
			$task 	= 'categoriesimg';
			$redi	= 'selectcategoriesimg';
		}
		JRequest::setVar('folder', $folder);

		// Do not allow cache
		JResponse::allowCache(false);

		//add css
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		//get images
		$images = $this->get('images');
		$pagination = $this->get('Pagination');

		if (count($images) > 0 || $search) {
			$this->images 		= $images;
			$this->folder 		= $folder;
			$this->task 		= $redi;
			$this->search 		= $search;
			$this->state		= $this->get('state');
			$this->pagination 	= $pagination;
			parent::display($tpl);
		} else {
			//no images in the folder, redirect to uploadscreen and raise notice
			JError::raiseNotice('SOME_ERROR_CODE', JText::_('COM_JEM_NO_IMAGES_AVAILABLE'));
			$this->setLayout('uploadimage');
			JRequest::setVar('task', $task);
			$this->_displayuploadimage($tpl);
			return;
		}
	}

	function setImage($index = 0) {
		if (isset($this->images[$index])) {
			$this->_tmp_img = $this->images[$index];
		} else {
			$this->_tmp_img = new JObject;
		}
	}

	/**
	 * Prepares the upload image screen
	 *
	 * @param $tpl
	 *
	 * @since 0.9
	 */
	function _displayuploadimage($tpl = null) {
		//initialise variables
		$document		= JFactory::getDocument();
		$uri 			= JFactory::getURI()->toString();
		$jemsettings	= JEMAdmin::config();

		//get vars
		$task 			= JRequest::getVar('task');

		//add css
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');
		
		jimport('joomla.client.helper');
		$ftp = JClientHelper::setCredentialsFromRequest('ftp');

		//assign data to template
		$this->task 		= $task;
		$this->jemsettings 	= $jemsettings;
		$this->request_url 	= $uri;
		$this->ftp 			= $ftp;

		parent::display($tpl);
	}
}
?>
