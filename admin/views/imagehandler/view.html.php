<?php
/**
 * @version 2.2.3
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
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
class JemViewImagehandler extends JViewLegacy
{

	/**
	 * Image selection List
	 */
	public function display($tpl = null)
	{
		$app    = JFactory::getApplication();
		$option = $app->input->getString('option', 'com_jem');

		JHtml::_('behavior.framework');

		if ($this->getLayout() == 'uploadimage') {
			$this->_displayuploadimage($tpl);
			return;
		}

		//get vars
		$task   = $app->input->get('task', '');
		$search = $app->getUserStateFromRequest($option.'.filter_search', 'filter_search', '', 'string');
		$search = trim(JString::strtolower($search));

		//set variables
		if ($task == 'selecteventimg') {
			$folder = 'events';
			$task   = 'eventimg';
			$redi   = 'selecteventimg';
		} elseif ($task == 'selectvenueimg') {
			$folder = 'venues';
			$task   = 'venueimg';
			$redi   = 'selectvenueimg';
		} elseif ($task == 'selectcategoriesimg') {
			$folder = 'categories';
			$task   = 'categoriesimg';
			$redi   = 'selectcategoriesimg';
		}

		$app->input->set('folder', $folder);

		// Do not allow cache
		if (version_compare(JVERSION, '3.0', 'ge')) {
			$app->allowCache(false);
		} else {
			JResponse::allowCache(false);
		}

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		//get images
		$images = $this->get('images');
		$pagination = $this->get('Pagination');

		if ($search || (is_array($images) && (count($images) > 0))) {
			$this->images     = $images;
			$this->folder     = $folder;
			$this->task       = $redi;
			$this->search     = $search;
			$this->state      = $this->get('state');
			$this->pagination = $pagination;
			parent::display($tpl);
		} else {
			//no images in the folder, redirect to uploadscreen and raise notice
			JError::raiseNotice('SOME_ERROR_CODE', JText::_('COM_JEM_NO_IMAGES_AVAILABLE'));
			$this->setLayout('uploadimage');
			$app->input->set('task', $task);
			$this->_displayuploadimage($tpl);
			return;
		}
	}

	public function setImage($index = 0)
	{
		if (isset($this->images[$index])) {
			$this->_tmp_img = $this->images[$index];
		} else {
			$this->_tmp_img = new JObject;
		}
	}

	/**
	 * Prepares the upload image screen
	 *
	 * @param  $tpl
	 *
	 */
	protected function _displayuploadimage($tpl = null)
	{
		//initialise variables
		$uri         = JFactory::getURI()->toString();
		$jemsettings = JemAdmin::config();

		//get vars
		$task = JFactory::getApplication()->input->get('task', '');

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		jimport('joomla.client.helper');
		$ftp = JClientHelper::setCredentialsFromRequest('ftp');

		//assign data to template
		$this->task        = $task;
		$this->jemsettings = $jemsettings;
		$this->request_url = $uri;
		$this->ftp         = $ftp;

		parent::display($tpl);
	}
}
?>
