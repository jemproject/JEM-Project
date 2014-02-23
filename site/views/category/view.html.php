<?php
/**
 * @version 1.9.6
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

require JPATH_COMPONENT_SITE.'/classes/view.class.php';

/**
 * Category-View
 */
class JemViewCategory extends JEMView
{
	/**
	 * Creates the Category View
	 */
	function display($tpl=null)
	{
		if($this->getLayout() == 'calendar') {
			$app = JFactory::getApplication();

			// Load tooltips behavior
			JHtml::_('behavior.tooltip');

			//initialize variables
			$document 		= JFactory::getDocument();
			$jemsettings 	= JemHelper::config();
			$menu 			= $app->getMenu();
			$menuitem		= $menu->getActive();
			$params 		= $app->getParams();
			$uri 			= JFactory::getURI();
			$pathway 		= $app->getPathWay();
			$print			= JRequest::getBool('print');

			// Load css
			JHtml::_('stylesheet', 'com_jem/jem.css', array(), true);
			JHtml::_('stylesheet', 'com_jem/calendar.css', array(), true);
			$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');
			if ($print) {
				JHtml::_('stylesheet', 'com_jem/print.css', array(), true);
				$document->setMetaData('robots', 'noindex, nofollow');
			}

			$evlinkcolor = $params->get('eventlinkcolor');
			$evbackgroundcolor = $params->get('eventbackgroundcolor');
			$currentdaycolor = $params->get('currentdaycolor');
			$eventandmorecolor = $params->get('eventandmorecolor');

			$style = '
			div[id^=\'scat\'] a {color:' . $evlinkcolor . ';}
			div[id^=\'scat\'] {background-color:'.$evbackgroundcolor .';}
			.eventandmore {background-color:'.$eventandmorecolor .';}
			.today .daynum {background-color:'.$currentdaycolor.';}';
			$document->addStyleDeclaration($style);

			// Retrieve date variables
			$year = (int)JRequest::getVar('yearID', strftime("%Y"));
			$month = (int)JRequest::getVar('monthID', strftime("%m"));

			if (JRequest::getVar('id')) {
				$catid = JRequest::getVar('id');
			} else {
				$catid = $params->get('id');
			}

			//get data from model and set the month
			$model = $this->getModel('CategoryCal');
			$model->setDate(mktime(0, 0, 1, $month, 1, $year));

			$category	= $this->get('Category', 'CategoryCal');
			$rows		= $this->get('Data', 'CategoryCal');

			//Set Page title
			$pagetitle   = $params->def('page_title', $menuitem->title);
			$params->def('page_heading', $params->get('page_title'));

			// Add site name to title if param is set
			if ($app->getCfg('sitename_pagetitles', 0) == 1) {
				$pagetitle = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $pagetitle);
			}
			elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
				$pagetitle = JText::sprintf('JPAGETITLE', $pagetitle, $app->getCfg('sitename'));
			}

			$document->setTitle($pagetitle);
			$document->setMetaData('title', $pagetitle);

			//init calendar
			$itemid = JRequest::getInt('Itemid');
			$partItemid = ($itemid > 0) ? '&Itemid='.$itemid : '';
			$partCatid = ($catid > 0) ? '&id=' . $catid : '';
			$cal = new JEMCalendar($year, $month, 0, $app->getCfg('offset'));
			$cal->enableMonthNav('index.php?option=com_jem&view=category&layout=calendar' . $partCatid . $partItemid);
			$cal->setFirstWeekDay($params->get('firstweekday', 1));
			//$cal->enableDayLinks(false);

			$this->rows 		= $rows;
			$this->catid 		= $catid;
			$this->params		= $params;
			$this->jemsettings	= $jemsettings;
			$this->cal			= $cal;

		} else {

			$this->addTemplatePath(JPATH_COMPONENT.'/common/views/tmpl');

			//initialize variables
			$app 			= JFactory::getApplication();
			$document 		= JFactory::getDocument();
			$jemsettings 	= JemHelper::config();
			$settings 		= JemHelper::globalattribs();
			$db  			= JFactory::getDBO();
			$user			= JFactory::getUser();

			JHtml::_('behavior.tooltip');

			//get menu information
			$params 		= $app->getParams();
			$uri 			= JFactory::getURI();
			$pathway 		= $app->getPathWay();
			$menu			= $app->getMenu();
			$menuitem		= $menu->getActive();

			// Load css
			JHtml::_('stylesheet', 'com_jem/jem.css', array(), true);
			$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');

			//get data from model
			$rows 		= $this->get('Data');
			$category 	= $this->get('Category');
			$categories	= $this->get('Childs');

			//are events available?
			if (!$rows) {
				$noevents = 1;
			} else {
				$noevents = 0;
			}

			//does the category exist
			if ($category->id == 0)
			{
				// TODO Translation
				return JError::raiseError(404, JText::sprintf('Category #%d not found', $category->id));
			}

			// Decide which parameters should take priority
			$useMenuItemParams = ($menuitem && $menuitem->query['option'] == 'com_jem'
			                                && $menuitem->query['view']   == 'category'
			                                && $menuitem->query['id']     == $category->id);

			// get variables
			$filter_order		= $app->getUserStateFromRequest('com_jem.category.filter_order', 'filter_order', 	'a.dates', 'cmd');
			$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.category.filter_order_Dir', 'filter_order_Dir',	'', 'word');
			$filter 			= $app->getUserStateFromRequest('com_jem.category.filter', 'filter', '', 'int');
			$search 			= $app->getUserStateFromRequest('com_jem.category.filter_search', 'filter_search', '', 'string');
			$search 			= $db->escape(trim(JString::strtolower($search)));
			$task 				= JRequest::getWord('task');

			// table ordering
			$lists['order_Dir'] = $filter_order_Dir;
			$lists['order'] 	= $filter_order;

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
			$lists['filter'] = JHtml::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $filter);

			// search filter
			$lists['search']= $search;

			// Add feed links
			$link = '&format=feed&id='.$category->id.'&limitstart=';
			$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
			$this->document->addHeadLink(JRoute::_($link . '&type=rss'), 'alternate', 'rel', $attribs);
			$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
			$this->document->addHeadLink(JRoute::_($link . '&type=atom'), 'alternate', 'rel', $attribs);

			//create the pathway
			$cats		= new JEMCategories($category->id);
			$parents	= $cats->getParentlist();

			foreach($parents as $parent) {
				$pathway->addItem($this->escape($parent->catname), JRoute::_(JemHelperRoute::getCategoryRoute($parent->slug)) );
			}

			// Show page heading specified on menu item or category title as heading - idea taken from com_content.
			//
			// Check to see which parameters should take priority
			// If the current view is the active menuitem and an category view for this category, then the menu item params take priority
			if ($useMenuItemParams) {
				$pagetitle   = $params->get('page_title', $menuitem->title ? $menuitem->title : $category->catname);
				$pageheading = $params->get('page_heading', $pagetitle);
				$pathway->setItemName(1, $menuitem->title);
			} else {
				$pagetitle   = $category->catname;
				$pageheading = $pagetitle;
				$pathway->addItem($category->catname, JRoute::_(JemHelperRoute::getCategoryRoute($category->slug)) );
			}

			if ($task == 'archive') {
				$pathway->addItem(JText::_('COM_JEM_ARCHIVE'), JRoute::_(JemHelperRoute::getCategoryRoute($category->slug).'&task=archive'));
				$print_link = JRoute::_(JemHelperRoute::getCategoryRoute($category->id) .'&task=archive&print=1&tmpl=component');
				$pagetitle   .= ' - '.JText::_('COM_JEM_ARCHIVE');
				$pageheading .= ' - '.JText::_('COM_JEM_ARCHIVE');
			} else {
				$print_link = JRoute::_(JemHelperRoute::getCategoryRoute($category->id) .'&print=1&tmpl=component');
			}

			$params->set('page_heading', $pageheading);

			// Add site name to title if param is set
			if ($app->getCfg('sitename_pagetitles', 0) == 1) {
				$pagetitle = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $pagetitle);
			}
			elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
				$pagetitle = JText::sprintf('JPAGETITLE', $pagetitle, $app->getCfg('sitename'));
			}

			//Set Page title & Meta data
			$this->document->setTitle($pagetitle);
			$document->setMetaData('title', $pagetitle);
			$document->setMetadata('keywords', $category->meta_keywords);
			$document->setDescription(strip_tags($category->meta_description));

			//Check if the user has access to the form
			$maintainer = JemUser::ismaintainer('add');
			$genaccess 	= JemUser::validate_user($jemsettings->evdelrec, $jemsettings->delivereventsyes);

			if ($maintainer || $genaccess || $user->authorise('core.create','com_jem')) {
				$dellink = 1;
			} else {
				$dellink = 0;
			}

			// Create the pagination object
			$pagination = $this->get('Pagination');

			//Generate Categorydescription
			if (empty ($category->description)) {
				$description = JText::_('COM_JEM_NO_DESCRIPTION');
			} else {
				//execute plugins
				$category->text	= $category->description;
				$category->title 	= $category->catname;
				JPluginHelper::importPlugin('content');
				$app->triggerEvent('onContentPrepare', array('com_jem.category', &$category, &$params, 0));
				$description = $category->text;
			}

			$cimage = JemImage::flyercreator($category->image,'category');

			//create select lists
			$this->lists			= $lists;
			$this->action			= $uri->toString();
			$this->cimage			= $cimage;
			$this->rows				= $rows;
			$this->noevents			= $noevents;
			$this->category			= $category;
			$this->print_link		= $print_link;
			$this->params			= $params;
			$this->dellink			= $dellink;
			$this->task				= $task;
			$this->description		= $description;
			$this->pagination		= $pagination;
			$this->jemsettings		= $jemsettings;
			$this->settings			= $settings;
			$this->categories		= $categories;
		}

		parent::display($tpl);
	}
}
?>