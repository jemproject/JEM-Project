<?php
/**
 * @version 2.3.6
 * @package JEM
 * @copyright (C) 2013-2021 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;


/**
 * View: Venueslist
 */
class JemViewVenueslist extends JViewLegacy
{
	protected $pagination = null;
	protected $items = null;
	
	/**
	 * Creates the Venueslist View
	 */
	public function display($tpl = null)
	{
		$items      = $this->get('Items');
		$pagination = $this->get('Pagination');
		
		// initialize variables
		$app          = JFactory::getApplication();
		$document     = JFactory::getDocument();
		$jemsettings  = JemHelper::config();
		$settings     = JemHelper::globalattribs();
		$menu         = $app->getMenu();
		$menuitem     = $menu->getActive();
		$params       = $app->getParams();
		$uri          = JFactory::getURI();
		$user         = JemFactory::getUser();
		$userId       = $user->get('id');
		$pathway      = $app->getPathWay();
		$jinput       = $app->input;
		$print        = $jinput->getBool('print', false);
		$task         = $jinput->getCmd('task', '');

		// redirect if not logged in
		//if (!$userId) {
			//$app->enqueueMessage(JText::_('COM_JEM_NEED_LOGGED_IN'), 'error');
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

		//$filters[] = JHtml::_('select.option', '0', JText::_('COM_JEM_CHOOSE'));
		
		if ($jemsettings->showlocate == 1) {



			$filters[] = JHtml::_('select.option', '3', JText::_('COM_JEM_CITY'));
		}


			$filters[] = JHtml::_('select.option', '2', JText::_('COM_JEM_VENUE'));			


			$filters[] = JHtml::_('select.option', '5', JText::_('COM_JEM_STATE'));
		$lists['filter'] = JHtml::_('select.genericlist', $filters, 'filter_type', array('size'=>'1','class'=>'input-medium'), 'value', 'text', $filter);

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
		$pagetitle = JText::_('COM_JEM_VENUESLIST_PAGETITLE');
		$pageheading = $pagetitle;
		$pageclass_sfx = '';

		// Check to see which parameters should take priority
		if ($useMenuItemParams) {
			// Menu item params take priority
			$params->def('page_title', $menuitem->title);
			$pagetitle = $params->get('page_title', JText::_('COM_JEM_VENUESLIST_PAGETITLE'));
			$pageheading = $params->get('page_heading', $pagetitle);
			$pageclass_sfx = $params->get('pageclass_sfx');
			$print_link = JRoute::_('index.php?option=com_jem&view=venueslist&print=1&tmpl=component');
		}

		$params->set('page_heading', $pageheading);

		// Add site name to title if param is set
		if ($app->getCfg('sitename_pagetitles', 0) == 1) {
			$pagetitle = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $pagetitle);
		}
		elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
			$pagetitle = JText::sprintf('JPAGETITLE', $pagetitle, $app->getCfg('sitename'));
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
		$this->pageclass_sfx      = htmlspecialchars($pageclass_sfx);

		parent::display($tpl);
	}
}
