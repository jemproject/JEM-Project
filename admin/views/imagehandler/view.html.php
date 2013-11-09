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
 * View class for the JEM imageselect screen
 * Based on the Joomla! media component
 *
 * @package JEM
 *
 */
class JEMViewImagehandler extends JViewLegacy {

	/**
	 * Image selection List
	 *
	 */
	function display($tpl = null) {
		$app 		= JFactory::getApplication();
		$option 	= JRequest::getString('option');

		if($this->getLayout() == 'uploadimage') {
			$this->_displayuploadimage($tpl);
			return;
		}

		//get vars
		$task 		= JRequest::getVar('task');
		$search 	= $app->getUserStateFromRequest($option.'.filter_search', 'filter_search', '', 'string');
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

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

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
	 */
	function _displayuploadimage($tpl = null) {
		//initialise variables
		$uri 			= JFactory::getURI()->toString();
		$jemsettings	= JEMAdmin::config();

		//get vars
		$task 			= JRequest::getVar('task');

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

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
