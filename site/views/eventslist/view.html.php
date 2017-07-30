<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

require JPATH_COMPONENT_SITE.'/classes/view.class.php';

/**
 * Eventslist-View
*/
class JemViewEventslist extends JemView
{
	public function __construct($config = array())
	{
		parent::__construct($config);

		// additional path for common templates + corresponding override path
		$this->addCommonTemplatePath();
	}

	/**
	 * Creates the Simple List View
	 */
	public function display($tpl = null)
	{
		// Initialize variables
		$app         = JFactory::getApplication();
		$jemsettings = JemHelper::config();
		$settings    = JemHelper::globalattribs();
		$menu        = $app->getMenu();
		$menuitem    = $menu->getActive();
		$document    = JFactory::getDocument();
		$params      = $app->getParams();
		$uri         = JFactory::getURI();
		$jinput      = $app->input;
		$task        = $jinput->getCmd('task', '');
		$print       = $jinput->getBool('print', false);
		$pathway     = $app->getPathWay();
		$user        = JemFactory::getUser();
		$itemid      = $jinput->getInt('id', 0) . ':' . $jinput->getInt('Itemid', 0);

		// Load css
		JemHelper::loadCss('jem');
		JemHelper::loadCustomCss();
		JemHelper::loadCustomTag();

		if ($print) {
			JemHelper::loadCss('print');
			$document->setMetaData('robots', 'noindex, nofollow');
		}

		// get variables
		$filter_order = $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.filter_order', 'filter_order', 'a.dates', 'cmd');
		$filter_order_DirDefault = 'ASC';
		// Reverse default order for dates in archive mode
		if ($task == 'archive' && $filter_order == 'a.dates') {
			$filter_order_DirDefault = 'DESC';
		}
		$filter_order_Dir = $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.filter_order_Dir', 'filter_order_Dir', $filter_order_DirDefault, 'word');
		$filter_type      = $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.filter_type', 'filter_type', 0, 'int');
		$search           = $app->getUserStateFromRequest('com_jem.eventslist.'.$itemid.'.filter_search', 'filter_search', '', 'string');

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order']     = $filter_order;

		// Get data from model
		$rows = $this->get('Items');

		// Are events available?
		$noevents = (!$rows) ? 1 : 0;

		// params
		$pagetitle     = $params->def('page_title', $menuitem ? $menuitem->title : JText::_('COM_JEM_EVENTS'));
		$pageheading   = $params->def('page_heading', $params->get('page_title'));
		$pageclass_sfx = $params->get('pageclass_sfx');

		// pathway
		if ($menuitem) {
			$pathway->setItemName(1, $menuitem->title);
		}

		if ($task == 'archive') {
			$pathway->addItem(JText::_('COM_JEM_ARCHIVE'), JRoute::_('index.php?option=com_jem&view=eventslist&task=archive'));
			$print_link = JRoute::_('index.php?option=com_jem&view=eventslist&task=archive&tmpl=component&print=1');
			$pagetitle   .= ' - ' . JText::_('COM_JEM_ARCHIVE');
			$pageheading .= ' - ' . JText::_('COM_JEM_ARCHIVE');
			$params->set('page_heading', $pageheading);
		} else {
			$print_link = JRoute::_('index.php?option=com_jem&view=eventslist&tmpl=component&print=1');
		}

		// Add site name to title if param is set
		if ($app->getCfg('sitename_pagetitles', 0) == 1) {
			$pagetitle = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $pagetitle);
		}
		elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
			$pagetitle = JText::sprintf('JPAGETITLE', $pagetitle, $app->getCfg('sitename'));
		}

		// Set Page title
		$document->setTitle($pagetitle);
		$document->setMetaData('title' , $pagetitle);

		// Check if the user has permission to add things
		$permissions = new stdClass();
		$permissions->canAddEvent = $user->can('add', 'event');
		$permissions->canAddVenue = $user->can('add', 'venue');

		// add alternate feed link
		$link    = 'index.php?option=com_jem&view=eventslist&format=feed';
		$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
		$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
		$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
		$document->addHeadLink(JRoute::_($link.'&type=atom'), 'alternate', 'rel', $attribs);

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
		$lists['filter'] = JHtml::_('select.genericlist', $filters, 'filter_type', array('size'=>'1','class'=>'inputbox'), 'value', 'text', $filter_type);
		$lists['search'] = $search;

		// Create the pagination object
		$pagination = $this->get('Pagination');

		$this->lists         = $lists;
		$this->rows          = $rows;
		$this->noevents      = $noevents;
		$this->print_link    = $print_link;
		$this->params        = $params;
		$this->dellink       = $permissions->canAddEvent; // deprecated
		$this->pagination    = $pagination;
		$this->action        = $uri->toString();
		$this->task          = $task;
		$this->jemsettings   = $jemsettings;
		$this->settings      = $settings;
		$this->permissions   = $permissions;
		$this->pagetitle     = $pagetitle;
		$this->pageclass_sfx = htmlspecialchars($pageclass_sfx);

		$this->_prepareDocument();
		parent::display($tpl);
	}

	/**
	 * Prepares the document
	 */
	protected function _prepareDocument()
	{
		// TODO: Refactor with parent _prepareDocument() function

	//	$app   = JFactory::getApplication();
	//	$menus = $app->getMenu();

		if ($this->params->get('menu-meta_description'))
		{
			$this->document->setDescription($this->params->get('menu-meta_description'));
		}

		if ($this->params->get('menu-meta_keywords'))
		{
			$this->document->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
		}

		if ($this->params->get('robots'))
		{
			$this->document->setMetadata('robots', $this->params->get('robots'));
		}
	}
}
?>
