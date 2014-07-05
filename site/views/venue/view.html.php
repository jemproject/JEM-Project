<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die ();

require JPATH_COMPONENT_SITE.'/classes/view.class.php';

/**
 * Venue-View
 */
class JemViewVenue extends JEMView {

	function __construct($config = array()) {
		parent::__construct($config);

		// additional path for common templates + corresponding override path
		$this->addCommonTemplatePath();
	}

	/**
	 * Creates the Venue View
	 */
	function display($tpl = null) {
		if ($this->getLayout() == 'calendar') {
			$app = JFactory::getApplication();

			// Load tooltips behavior
			JHtml::_('behavior.tooltip');

			// initialize variables
			$document 		= JFactory::getDocument();
			$menu 			= $app->getMenu();
			$menuitem		= $menu->getActive();
			$jemsettings	= JEMHelper::config();
			$params 		= $app->getParams();
			$uri 			= JFactory::getURI();
			$pathway 		= $app->getPathWay();
			$jinput 		= $app->input;
			$print			= JRequest::getBool('print');

			// Load css
			JemHelper::loadCss('jem');
			JemHelper::loadCss('calendar');
			JemHelper::loadCustomCss();
			JemHelper::loadCustomTag();

			if ($print) {
				JemHelper::loadCss('print');
				$document->setMetaData('robots', 'noindex, nofollow');
			}

			$evlinkcolor = $params->get('eventlinkcolor');
			$evbackgroundcolor = $params->get('eventbackgroundcolor');
			$currentdaycolor = $params->get('currentdaycolor');
			$eventandmorecolor = $params->get('eventandmorecolor');

			$style = '
			div[id^=\'catz\'] a {color:' . $evlinkcolor . ';}
			div[id^=\'catz\'] {background-color:' . $evbackgroundcolor . ';}
			.eventcontent {background-color:'.$evbackgroundcolor .';}
			.eventandmore {background-color:' . $eventandmorecolor . ';}
			.today .daynum {background-color:' . $currentdaycolor . ';}';
			$document->addStyleDeclaration ($style);

			// add javascript (using full path - see issue #590)
			JHtml::_('script', 'media/com_jem/js/calendar.js');

			// Retrieve year/month variables
			$year = $jinput->get('yearID', strftime("%Y"),'int');
			$month = $jinput->get('monthID', strftime("%m"),'int');

			// get data from model and set the month
			$model = $this->getModel('VenueCal');
			$model->setDate(mktime(0, 0, 1, $month, 1, $year));
			$rows = $this->get('Items','VenueCal');

			// Set Page title
			$pagetitle = $params->def('page_title', $menuitem->title);
			$params->def('page_heading', $params->get('page_title'));
			$pageclass_sfx = $params->get('pageclass_sfx');

			// Add site name to title if param is set
			if ($app->getCfg('sitename_pagetitles', 0) == 1) {
				$pagetitle = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $pagetitle);
			}
			elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
				$pagetitle = JText::sprintf('JPAGETITLE', $pagetitle, $app->getCfg('sitename'));
			}

			$document->setTitle($pagetitle);
			$document->setMetaData('title', $pagetitle);

			// init calendar
			$itemid = JRequest::getInt('Itemid');
			$venueID = $params->get('id');

			$partItemid = ($itemid > 0) ? '&Itemid='.$itemid : '';
			$partVenid = ($venueID > 0) ? '&id=' . $venueID : '';
			$cal = new JEMCalendar($year, $month, 0, $app->getCfg('offset'));
			$cal->enableMonthNav('index.php?view=venue&layout=calendar'.$partVenid.$partItemid);
			$cal->setFirstWeekDay($params->get('firstweekday',1));
			$cal->enableDayLinks(false);

			// map variables
			$this->rows 			= $rows;
			$this->params 			= $params;
			$this->jemsettings 		= $jemsettings;
			$this->cal 				= $cal;
			$this->pageclass_sfx	= htmlspecialchars($pageclass_sfx);

		} else {

			// initialize variables
			$app 			= JFactory::getApplication();
			$document 		= JFactory::getDocument();
			$menu 			= $app->getMenu();
			$menuitem		= $menu->getActive();
			$jemsettings 	= JemHelper::config();
			$settings 		= JemHelper::globalattribs();
			$db 			= JFactory::getDBO();
			$params 		= $app->getParams('com_jem');
			$pathway 		= $app->getPathWay ();
			$uri 			= JFactory::getURI();
			$task 			= JRequest::getWord('task');
			$user			= JFactory::getUser();
			$itemid 		= JRequest::getInt('id', 0) . ':' . JRequest::getInt('Itemid', 0);

			// Load css
			JemHelper::loadCss('jem');
			JemHelper::loadCustomCss();
			JemHelper::loadCustomTag();

			// get data from model
			$rows	= $this->get('Items');
			$venue	= $this->get('Venue');

			// are events available?
			if (!$rows) {
				$noevents = 1;
			} else {
				$noevents = 0;
			}

			// Decide which parameters should take priority
			$useMenuItemParams = ($menuitem && $menuitem->query['option'] == 'com_jem'
			                                && $menuitem->query['view']   == 'venue'
			                                && (!isset($menuitem->query['layout']) || $menuitem->query['layout'] == 'default')
			                                && $menuitem->query['id']     == $venue->id);

			// get search & user-state variables
			$filter_order 		= $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.filter_order', 'filter_order', 'a.dates', 'cmd');
			$filter_order_DirDefault = 'ASC';
			// Reverse default order for dates in archive mode
			if($task == 'archive' && $filter_order == 'a.dates') {
				$filter_order_DirDefault = 'DESC';
			}
			$filter_order_Dir = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.filter_order_Dir', 'filter_order_Dir', $filter_order_DirDefault, 'word');
			$filter_type      = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.filter_type', 'filter_type', '', 'int');
			$search           = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.filter_search', 'filter_search', '', 'string');
			$search           = $db->escape(trim(JString::strtolower($search)));

			// table ordering
			$lists['order_Dir']	= $filter_order_Dir;
			$lists['order']		= $filter_order;

			// Get image
			$limage = JemImage::flyercreator($venue->locimage,'venue');

			// Add feed links
			$link = '&format=feed&id='.$venue->id.'&limitstart=';
			$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
			$this->document->addHeadLink(JRoute::_($link . '&type=rss'), 'alternate', 'rel', $attribs);
			$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
			$this->document->addHeadLink(JRoute::_($link . '&type=atom'), 'alternate', 'rel', $attribs);

			// pathway, page title, page heading
			if ($useMenuItemParams) {
				$pagetitle   = $params->get('page_title', $menuitem->title ? $menuitem->title : $venue->venue);
				$pageheading = $params->get('page_heading', $pagetitle);
				$pathway->setItemName(1, $menuitem->title);
			} else {
				$pagetitle   = $venue->venue;
				$pageheading = $pagetitle;
				$params->set('show_page_heading', 1); // ensure page heading is shown
				$pathway->addItem($pagetitle, JRoute::_(JemHelperRoute::getVenueRoute($venue->slug)));
			}
			$pageclass_sfx = $params->get('pageclass_sfx');

			// create the pathway
			if ($task == 'archive') {
				$pathway->addItem (JText::_('COM_JEM_ARCHIVE'), JRoute::_(JemHelperRoute::getVenueRoute($venue->slug).'&task=archive'));
				$print_link = JRoute::_(JEMHelperRoute::getVenueRoute($venue->slug).'&task=archive&print=1&tmpl=component');
				$pagetitle   .= ' - ' . JText::_('COM_JEM_ARCHIVE');
				$pageheading .= ' - ' . JText::_('COM_JEM_ARCHIVE');
			} else {
				//$pathway->addItem($venue->venue, JRoute::_(JEMHelperRoute::getVenueRoute($venue->slug)));
				$print_link = JRoute::_(JemHelperRoute::getVenueRoute($venue->slug).'&print=1&tmpl=component');
			}

			$params->set('page_heading', $pageheading);

			// Add site name to title if param is set
			if ($app->getCfg('sitename_pagetitles', 0) == 1) {
				$pagetitle = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $pagetitle);
			}
			elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
				$pagetitle = JText::sprintf('JPAGETITLE', $pagetitle, $app->getCfg('sitename'));
			}

			// set Page title & Meta data
			$document->setTitle($pagetitle);
			$document->setMetaData('title', $pagetitle);
			$document->setMetadata('keywords', $venue->meta_keywords);
			$document->setDescription(strip_tags($venue->meta_description));

			// Check if the user has access to the add-eventform
			$maintainer = JemUser::ismaintainer('add');
			$genaccess = JemUser::validate_user($jemsettings->evdelrec, $jemsettings->delivereventsyes);

			if ($maintainer || $genaccess || $user->authorise('core.create','com_jem')) {
				$addeventlink = 1;
			} else {
				$addeventlink = 0;
			}

			// Check if the user has access to the add-venueform
			$maintainer2 = JemUser::venuegroups('add');
			$genaccess2 = JemUser::validate_user($jemsettings->locdelrec, $jemsettings->deliverlocsyes);
			if ($maintainer2 || $genaccess2) {
				$addvenuelink = 1;
			} else {
				$addvenuelink = 0;
			}

			// Check if the user has access to the edit-venueform
			$maintainer3 = JemUser::venuegroups('edit');
			$genaccess3 = JemUser::editaccess($jemsettings->venueowner, $venue->created, $jemsettings->venueeditrec, $jemsettings->venueedit);
			if ($maintainer3 || $genaccess3) {
				$allowedtoeditvenue = 1;
			} else {
				$allowedtoeditvenue = 0;
			}

			// Generate Venuedescription
			if (!$venue->locdescription == '' || !$venue->locdescription == '<br />') {
				// execute plugins
				$venue->text = $venue->locdescription;
				$venue->title = $venue->venue;
				JPluginHelper::importPlugin ('content');
				$app->triggerEvent ('onContentPrepare', array (
						'com_jem.venue',
						&$venue,
						&$params,
						0
				));
				$venuedescription = $venue->text;
			}

			// build the url
			// TODO: What's about "https://"?
			if (!empty($venue->url) && strtolower (substr($venue->url, 0, 7)) != "http://") {
				$venue->url = 'http://' . $venue->url;
			}

			// prepare the url for output
			if (strlen($venue->url) > 35) {
				$venue->urlclean = $this->escape(substr($venue->url, 0, 35 )) . '...';
			} else {
				$venue->urlclean = $this->escape($venue->url);
			}

			// create flag
			if ($venue->country) {
				$venue->countryimg = JemHelperCountries::getCountryFlag($venue->country);
			}

			// Create the pagination object
			$pagination = $this->get('Pagination');

			// filters
			$filters = array ();

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

			// mapping variables
			$this->lists 				= $lists;
			$this->action 				= $uri->toString ();
			$this->rows 				= $rows;
			$this->noevents 			= $noevents;
			$this->venue 				= $venue;
			$this->print_link 			= $print_link;
			$this->params 				= $params;
			$this->addvenuelink 		= $addvenuelink;
			$this->addeventlink 		= $addeventlink;
			$this->limage 				= $limage;
			$this->venuedescription		= $venuedescription;
			$this->pagination 			= $pagination;
			$this->jemsettings 			= $jemsettings;
			$this->settings				= $settings;
			$this->item					= $menuitem;
			$this->pagetitle			= $pagetitle;
			$this->task					= $task;
			$this->allowedtoeditvenue 	= $allowedtoeditvenue;
			$this->pageclass_sfx		= htmlspecialchars($pageclass_sfx);
		}

		parent::display($tpl);
	}
}
?>