<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Client\ClientHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Object\CMSObject;

/**
 * View class for the JEM imageselect screen
 * Based on the Joomla! media component
 *
 * @package JEM
 *
 */
class JemViewImagehandler extends HtmlView
{

	/**
	 * Image selection List
	 */
	public function display($tpl = null)
	{
		$app    = Factory::getApplication();
		$option = $app->input->getString('option', 'com_jem');

		// HTMLHelper::_('behavior.framework');

		if ($this->getLayout() == 'uploadimage') {
			$this->_displayuploadimage($tpl);
			return;
		}

		//get vars
		$task   = $app->input->get('task', '');
		$search = $app->getUserStateFromRequest($option.'.filter_search', 'filter_search', '', 'string');
		$search = trim(\Joomla\String\StringHelper::strtolower($search));

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
		$app->allowCache(false);

		// Load css
		// HTMLHelper::_('stylesheet', 'com_jem/backend.css', array(), true);
		$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
	
		$wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');
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
			Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_NO_IMAGES_AVAILABLE'), 'notice');
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
			$this->_tmp_img = new CMSObject;
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
		$uri         =Uri::getInstance();
		$uri         = $uri->toString();
		$jemsettings = JemAdmin::config();

		//get vars
		$task = Factory::getApplication()->input->get('task', '');

		// Load css
		// HTMLHelper::_('stylesheet', 'com_jem/backend.css', array(), true);
		$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
	
		$wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');

		$ftp = ClientHelper::setCredentialsFromRequest('ftp');

		//assign data to template
		$this->task        = $task;
		$this->jemsettings = $jemsettings;
		$this->request_url = $uri;
		$this->ftp         = $ftp;

		parent::display($tpl);
	}
}
?>
