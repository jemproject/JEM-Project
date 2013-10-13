<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');


/**
 * HTML View class for the Category View
 *
 * @package JEM
 *
*/
class JEMViewCategory extends JViewLegacy
{
	/**
	 * Creates the Category View
	 *
	 *
	 */
	function display($tpl=null)
	{
		if($this->getLayout() == 'calendar') {
			$app = JFactory::getApplication();

			// Load tooltips behavior
			JHTML::_('behavior.tooltip');

			//initialize variables
			$document 	= JFactory::getDocument();
			$menu 		= $app->getMenu();
			$jemsettings = JEMHelper::config();
			$item 		= $menu->getActive();
			$params 	= $app->getParams();
			$uri 		= JFactory::getURI();
			$pathway 	= $app->getPathWay();

			//add css file
			$document->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
			$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');
			$document->addStyleSheet($this->baseurl.'/media/com_jem/css/calendar.css');


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
			$model = $this->getModel();
			$model->setDate(mktime(0, 0, 1, $month, 1, $year));

			$category	= $this->get('Category','CategoryCal');
			$rows		= $this->get('Data','CategoryCal');

			//Set Meta data
			$document->setTitle($item->title);

			//Set Page title
			$pagetitle = $params->def('page_title', $item->title);
			$document->setTitle($pagetitle);
			$document->setMetaData('title', $pagetitle);

			//init calendar
			$cal = new JEMCalendar($year, $month, 0, $app->getCfg('offset'));
			$cal->enableMonthNav('index.php?view=category&layout=calendar&id='. $category->slug);
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
		$app = JFactory::getApplication();
		$document 		= JFactory::getDocument();
		$menu			= $app->getMenu();
		$jemsettings 	= JEMHelper::config();
		$db  			= JFactory::getDBO();

		JHTML::_('behavior.tooltip');

		//get menu information
		$menu			= $app->getMenu();
		$item 			= $menu->getActive();
		$params 		= $app->getParams();
		$uri 			= JFactory::getURI();
		$pathway 		= $app->getPathWay();

		//add css file
		$document->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');


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
			$filters[] = JHTML::_('select.option', '1', JText::_('COM_JEM_TITLE'));
		}
		if ($jemsettings->showlocate == 1) {
			$filters[] = JHTML::_('select.option', '2', JText::_('COM_JEM_VENUE'));
		}
		if ($jemsettings->showcity == 1) {
			$filters[] = JHTML::_('select.option', '3', JText::_('COM_JEM_CITY'));
		}
		if ($jemsettings->showcat == 1) {
			$filters[] = JHTML::_('select.option', '4', JText::_('COM_JEM_CATEGORY'));
		}
		if ($jemsettings->showstate == 1) {
			$filters[] = JHTML::_('select.option', '5', JText::_('COM_JEM_STATE'));
		}
		$lists['filter'] = JHTML::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $filter);

		// search filter
		$lists['search']= $search;

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

		//Set Meta data
		$document->setTitle($item->title.' - '.$category->catname);
		$document->setMetadata('keywords', $category->meta_keywords);
		$document->setDescription(strip_tags($category->meta_description));

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
			$pathway->addItem($this->escape($parent->catname), JRoute::_('index.php?view=category&id='.$parent->categoryslug));
		}

		if ($task == 'archive') {
			$pathway->addItem(JText::_('COM_JEM_ARCHIVE').' - '.$category->catname, JRoute::_('index.php?option=com_jem&view=category&task=archive&id='.$category->slug));
			$print_link = JRoute::_('index.php?option=com_jem&view=category&id='. $category->id .'&task=archive&print=1&tmpl=component');
			$pagetitle = $category->catname.' - '.JText::_('COM_JEM_ARCHIVE');
		} else {
			$pathway->addItem($category->catname, JRoute::_('index.php?option=com_jem&view=category&id='.$category->slug));
			$print_link = JRoute::_('index.php?option=com_jem&view=category&id='. $category->id .'&print=1&tmpl=component');
			$pagetitle = $category->catname;
		}

		//Check if the user has access to the form
		$maintainer = JEMUser::ismaintainer();
		$genaccess 	= JEMUser::validate_user($jemsettings->evdelrec, $jemsettings->delivereventsyes);

		if ($maintainer || $genaccess) {
			$dellink = 1;
		} else {
			$dellink = 0;
		}

		// Create the pagination object
		$pagination = $this->get('Pagination');

		//Generate Categorydescription
		if (empty ($category->catdescription)) {
			$catdescription = JText::_('COM_JEM_NO_DESCRIPTION');
		} else {
			//execute plugins
			$category->text	= $category->catdescription;
			$category->title 	= $category->catname;
			JPluginHelper::importPlugin('content');
			$app->triggerEvent('onContentPrepare', array('com_jem.category', &$category, &$params, 0));
			$catdescription = $category->text;
		}

		$cimage = JEMImage::flyercreator($category->image,'category');

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
		$this->catdescription	= $catdescription;
		$this->pagination		= $pagination;
		$this->jemsettings		= $jemsettings;
		$this->item				= $item;
		$this->categories		= $categories;

		}

		parent::display($tpl);
	}

	/**
	 * Manipulate Data
	 *
	 *
	 */
	function &getRows()
	{
		$count = count($this->rows);

		if (!$count) {
			return;
		}

		$k = 0;
		foreach($this->rows as $key => $row) {
			$row->odd = $k;

			$this->rows[$key] = $row;
			$k = 1 - $k;
		}

		return $this->rows;
	}

}
?>