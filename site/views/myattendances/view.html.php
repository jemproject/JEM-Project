<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
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
	function display($tpl = null)
	{
		$app = JFactory::getApplication();

		//initialize variables
		$document 		= JFactory::getDocument();
		$jemsettings 	= JemHelper::config();
		$settings 		= JemHelper::globalattribs();
		$menu 			= $app->getMenu();
		$menuitem		= $menu->getActive();
		$params 		= $app->getParams();
		$uri 			= JFactory::getURI();
		$user			= JFactory::getUser();
		$pathway 		= $app->getPathWay();
	//	$db  			= JFactory::getDBO();

		//redirect if not logged in
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
		
		$attending 	= $this->get('Attending');
		$attending_pagination 	= $this->get('AttendingPagination');

		//are attendences available?
		if (!$attending) {
			$noattending = 1;
		} else {
			$noattending = 0;
		}

		// get variables
		$filter_order		= $app->getUserStateFromRequest('com_jem.myattendances.filter_order', 'filter_order', 	'a.dates', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.myattendances.filter_order_Dir', 'filter_order_Dir',	'', 'word');
// 		$filter_state 		= $app->getUserStateFromRequest('com_jem.myattendances.filter_state', 'filter_state', 	'*', 'word');
		$filter 			= $app->getUserStateFromRequest('com_jem.myattendances.filter', 'filter', '', 'int');
		$search 			= $app->getUserStateFromRequest('com_jem.myattendances.filter_search', 'filter_search', '', 'string');

		$task 				= JRequest::getWord('task');

		//search filter
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

		//pathway
		if ($menuitem) {
			$pathway->setItemName(1, $menuitem->title);
		}

		//Set Page title
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

		$this->action					= $uri->toString();
		$this->attending				= $attending;
		$this->task						= $task;
		$this->params					= $params;
		$this->attending_pagination 	= $attending_pagination;
		$this->jemsettings				= $jemsettings;
		$this->settings					= $settings;
		$this->pagetitle				= $pagetitle;
		$this->lists 					= $lists;
		$this->noattending				= $noattending;
		$this->pageclass_sfx			= htmlspecialchars($pageclass_sfx);

		parent::display($tpl);
	}
}
?>