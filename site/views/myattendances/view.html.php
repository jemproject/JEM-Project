<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;


/**
 * Myattendances-View
 */
class JemViewMyattendances extends JViewLegacy
{
	/**
	 * Creates the Myattendances View
	 */
	public function display($tpl = null)
	{
		// initialize variables
		$app         = JFactory::getApplication();
		$document    = JFactory::getDocument();
		$jemsettings = JemHelper::config();
		$settings    = JemHelper::globalattribs();
		$menu        = $app->getMenu();
		$menuitem    = $menu->getActive();
		$params      = $app->getParams();
		$uri         = JFactory::getURI();
		$user        = JemFactory::getUser();
		$pathway     = $app->getPathWay();
		$print       = $app->input->getBool('print', false);
		$task        = $app->input->getCmd('task', '');

		// redirect if not logged in
		if (!$user->get('id')) {
			$app->enqueueMessage(JText::_('COM_JEM_NEED_LOGGED_IN'), 'error');
			return false;
		}

		// Decide which parameters should take priority
		$useMenuItemParams = ($menuitem && $menuitem->query['option'] == 'com_jem'
		                                && $menuitem->query['view'] == 'myattendances');

		// Load css
		JemHelper::loadCss('jem');
		JemHelper::loadCustomCss();
		JemHelper::loadCustomTag();

		if ($print) {
			JemHelper::loadCss('print');
			$document->setMetaData('robots', 'noindex, nofollow');
		}

		$attending = $this->get('Attending');
		$attending_pagination = $this->get('AttendingPagination');

		// are attendences available?
		$noattending = (!$attending) ? 1 : 0;

		// get variables
		$filter_order     = $app->getUserStateFromRequest('com_jem.myattendances.filter_order',     'filter_order',     'a.dates', 'cmd');
		$filter_order_Dir = $app->getUserStateFromRequest('com_jem.myattendances.filter_order_Dir', 'filter_order_Dir', '',       'word');
// 		$filter_state     = $app->getUserStateFromRequest('com_jem.myattendances.filter_state',     'filter_state',     '*',      'word');
		$filter           = $app->getUserStateFromRequest('com_jem.myattendances.filter',           'filter',            0,        'int');
		$search           = $app->getUserStateFromRequest('com_jem.myattendances.filter_search',    'filter_search',    '',     'string');

		// search filter
		$filters = array();

		if ($jemsettings->showtitle == 1) {
			$filters[] = JHtml::_('select.option', '1', JText::_('COM_JEM_TITLE'));
		}
		if ($jemsettings->showlocate == 1) {
			$filters[] = JHtml::_('select.option', '2', JText::_('COM_JEM_VENUE'));
		}
		if ($jemsettings->showcity == 1) {
			$filters[] = JHtml::_('select.option', '3', JText::_('COM_JEM_CITY'));
		}
		if ($jemsettings->showcat == 1) {
			$filters[] = JHtml::_('select.option', '4', JText::_('COM_JEM_CATEGORY'));
		}
		if ($jemsettings->showstate == 1) {
			$filters[] = JHtml::_('select.option', '5', JText::_('COM_JEM_STATE'));
		}
		$lists['filter'] = JHtml::_('select.genericlist', $filters, 'filter', array('size'=>'1','class'=>'inputbox'), 'value', 'text', $filter);

		// search filter
		$lists['search']= $search;

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		// pathway
		if ($menuitem) {
			$pathway->setItemName(1, $menuitem->title);
		}

		// Set Page title
		$pagetitle = JText::_('COM_JEM_MY_ATTENDANCES');
		$pageheading = $pagetitle;

		// Check to see which parameters should take priority
		if ($useMenuItemParams) {
			// Menu item params take priority
			$params->def('page_title', $menuitem->title);
			$pagetitle = $params->get('page_title', JText::_('COM_JEM_MY_ATTENDANCES'));
			$pageheading = $params->get('page_heading', $pagetitle);
			$pageclass_sfx = $params->get('pageclass_sfx');
		}

		if ($task == 'archive') {
			$pathway->addItem(JText::_('COM_JEM_ARCHIVE'), JRoute::_(JemHelperRoute::getMyAttendancesRoute().'&task=archive'));
			$print_link = JRoute::_(JemHelperRoute::getMyAttendancesRoute().'&task=archive&print=1&tmpl=component');
			$pagetitle   .= ' - '.JText::_('COM_JEM_ARCHIVE');
			$pageheading .= ' - '.JText::_('COM_JEM_ARCHIVE');
			$params->set('page_heading', $pageheading);
		} else {
			$print_link = JRoute::_(JemHelperRoute::getMyAttendancesRoute().'&print=1&tmpl=component');
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

		// Don't add things from this view, no good starting point
		$permissions = new stdClass();

		$this->action               = $uri->toString();
		$this->attending            = $attending;
		$this->task                 = $task;
		$this->params               = $params;
		$this->attending_pagination = $attending_pagination;
		$this->jemsettings          = $jemsettings;
		$this->settings             = $settings;
		$this->permissions          = $permissions;
		$this->pagetitle            = $pagetitle;
		$this->print_link           = $print_link;
		$this->print                = $print;
		$this->lists                = $lists;
		$this->noattending          = $noattending;
		$this->pageclass_sfx        = htmlspecialchars($pageclass_sfx);

		parent::display($tpl);
	}
}
?>