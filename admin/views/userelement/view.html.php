<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView;

/**
 * View class for the JEM userelement screen
 *
 * @package JEM
 *
 */
class JEMViewUserElement extends HtmlView {

	public function display($tpl = null)
	{
		$app = Factory::getApplication();

		// initialise variables
		$app = Factory::getApplication();
		$document = $app->getDocument();
		$jemsettings = JEMAdmin::config();
		$db = Factory::getContainer()->get('DatabaseDriver');

		// get var
		$filter_order		= $app->getUserStateFromRequest('com_jem.userelement.filter_order', 'filter_order', 'u.name', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.userelement.filter_order_Dir', 'filter_order_Dir', '', 'word');
		$search 			= $app->getUserStateFromRequest('com_jem.userelement.filter_search', 'filter_search', '', 'string');
		$search 			= $db->escape(trim(\Joomla\String\StringHelper::strtolower($search)));

		// prepare the document
		$document->setTitle(Text::_('COM_JEM_SELECTATTENDEE'));
		
		// Load css
		// HTMLHelper::_('stylesheet', 'com_jem/backend.css', array(), true);
		$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
	
		$wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');
		// Get data from the model
		$users			= $this->get('Data');
		$pagination 	= $this->get('Pagination');

		// build selectlists
		$lists = array();
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;
		// search filter
		$lists['search']= $search;

		// assign data to template
		$this->lists		= $lists;
		$this->rows			= $users;
		$this->jemsettings	= $jemsettings;
		$this->pagination	= $pagination;

		parent::display($tpl);
	}
}
?>
