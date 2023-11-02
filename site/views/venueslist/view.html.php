<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
/**
 * View: Venueslist
 */

	
class JemViewVenueslist extends JemView
{
	public function __construct($config = array())
{
		parent::__construct($config);

		// additional path for common templates + corresponding override path
		$this->addCommonTemplatePath();
	}	
	
	/**
	 * Creates the Venueslist View
	 */
	public function display($tpl = null)
	{
		$items      = $this->get('Items');
		$pagination = $this->get('Pagination');
		
		// initialize variables
		$app          = Factory::getApplication();
        $document     = $app->getDocument();
		$jemsettings  = JemHelper::config();
		$settings     = JemHelper::globalattribs();
		$menu         = $app->getMenu();
		$menuitem     = $menu->getActive();
		$params       = $app->getParams();
		$uri          = Uri::getInstance();
		$user         = JemFactory::getUser();
		$userId       = $user->get('id');
		$pathway      = $app->getPathWay();
		$jinput       = $app->input;
		$print        = $jinput->getBool('print', false);
		$task         = $jinput->getCmd('task', '');

		// redirect if not logged in
		//if (!$userId) {
			//$app->enqueueMessage(Text::_('COM_JEM_NEED_LOGGED_IN'), 'error');
			// return false;
		//}

		// Decide which parameters should take priority
		$useMenuItemParams = ($menuitem && $menuitem->query['option'] == 'com_jem'
		                                && $menuitem->query['view'] == 'venueslist');

		// Load css
		JemHelper::loadCss('jem');
		JemHelper::loadCustomCss();
		JemHelper::loadCustomTag();

		if ($print) {
			JemHelper::loadCss('print');
			$document->setMetaData('robots', 'noindex, nofollow');
		}

		// Get data from model
		$rows = $this->get('Items');

		// are no venues available?
		$novenues = (!$rows) ? 1 : 0;

		// get variables
		$filter_order     = $app->getUserStateFromRequest('com_jem.venueslist.filter_order', 'filter_order', 	'a.city', 'cmd');
		$filter_order_Dir = $app->getUserStateFromRequest('com_jem.venueslist.filter_order_Dir', 'filter_order_Dir',	'', 'word');
// 		$filter_state     = $app->getUserStateFromRequest('com_jem.venueslist.filter_state', 'filter_state', 	'*', 'word');
		$filter           = $app->getUserStateFromRequest('com_jem.venueslist.filter_type', 'filter_type', '', 'int');
		$search           = $app->getUserStateFromRequest('com_jem.venueslist.filter_search', 'filter_search', '', 'string');

		// search filter
		$filters = array();

		// Workaround issue #557: Show venue name always.
		$jemsettings->showlocate = 1;

		//$filters[] = HTMLHelper::_('select.option', '0', Text::_('COM_JEM_CHOOSE'));
		
		if ($jemsettings->showlocate == 1) {



			$filters[] = HTMLHelper::_('select.option', '3', Text::_('COM_JEM_CITY'));
		}


			$filters[] = HTMLHelper::_('select.option', '2', Text::_('COM_JEM_VENUE'));			


			$filters[] = HTMLHelper::_('select.option', '5', Text::_('COM_JEM_STATE'));
		$lists['filter'] = HTMLHelper::_('select.genericlist', $filters, 'filter_type', array('size'=>'1','class'=>'input-medium'), 'value', 'text', $filter);

		// search filter
		$lists['search'] = $search;

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		// pathway
		if ($menuitem) {
			$pathway->setItemName(1, $menuitem->title);
		}

		// Set Page title
		$pagetitle = Text::_('COM_JEM_VENUESLIST_PAGETITLE');
		$pageheading = $pagetitle;
		$pageclass_sfx = '';

		// Check to see which parameters should take priority
		if ($useMenuItemParams) {
			// Menu item params take priority
			$params->def('page_title', $menuitem->title);
			$pagetitle = $params->get('page_title', Text::_('COM_JEM_VENUESLIST_PAGETITLE'));
			$pageheading = $params->get('page_heading', $pagetitle);
			$pageclass_sfx = $params->get('pageclass_sfx');
			$print_link = Route::_('index.php?option=com_jem&view=venueslist&print=1&tmpl=component');
		}

		$params->set('page_heading', $pageheading);

		// Add site name to title if param is set
		if ($app->get('sitename_pagetitles', 0) == 1) {
			$pagetitle = Text::sprintf('JPAGETITLE', $app->get('sitename'), $pagetitle);
		}
		elseif ($app->get('sitename_pagetitles', 0) == 2) {
			$pagetitle = Text::sprintf('JPAGETITLE', $pagetitle, $app->get('sitename'));
		}

		$document->setTitle($pagetitle);
		$document->setMetaData('title', $pagetitle);

		//Check if the user has permission to add things
		$permissions = new stdClass();
		//$permissions->canAddEvent = $user->can('add', 'event');
		$permissions->canAddVenue = $user->can('add', 'venue');
		$permissions->canEditPublishVenue = $user->can(array('edit', 'publish'), 'venue');

		// Create the pagination object
		// $pagination = $this->get('Pagination');
		

		$this->action             = $uri->toString();
		$this->rows				  = $rows;
		$this->items      		  = $items;
		$this->task               = $task;
		$this->print              = $print;
		$this->params             = $params;
		$this->pagination 		  = $pagination;
		$this->jemsettings        = $jemsettings;
		$this->settings           = $settings;


		$this->pagetitle          = $pagetitle;
		$this->lists              = $lists;
		$this->novenues           = $novenues;


		$this->permissions		= $permissions;
		$this->show_status		= $permissions->canEditPublishVenue;	$this->print_link		= $print_link;
		$this->pageclass_sfx      = $pageclass_sfx ? htmlspecialchars($pageclass_sfx) : $pageclass_sfx;

		parent::display($tpl);
	}
}