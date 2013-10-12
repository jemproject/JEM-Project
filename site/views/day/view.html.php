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
 * HTML View class for the Day View
 *
 * @package JEM
 *
 */
class JEMViewDay extends JViewLegacy
{
	/**
	 * Creates the Day View
	 *
	 *
	 */
	function display($tpl = null)
	{

		//initialize variables
		$app 			= JFactory::getApplication();
		$document 		= JFactory::getDocument();
		$jemsettings 	= JEMHelper::config();
		$menu			= $app->getMenu();
		$item 			= $menu->getActive();
		$params 		= $app->getParams();
		$db  			= JFactory::getDBO();
		$uri 			= JFactory::getURI();
		$task 			= JRequest::getWord('task');
		$pathway 		= $app->getPathWay();

		// Retrieving locid
		$jinput = JFactory::getApplication()->input;
		$requestVenueId = $jinput->get('locid', null, 'int');
		$requestCategoryId = $jinput->get('catid', null, 'int');
		$requestDate = $jinput->get('id', null, 'int');

		//add css file
		$document->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');

		// get variables
		$filter_order		= $app->getUserStateFromRequest('com_jem.day.filter_order', 'filter_order', 	'a.dates', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.day.filter_order_Dir', 'filter_order_Dir',	'', 'word');
		$filter 			= $app->getUserStateFromRequest('com_jem.day.filter', 'filter', '', 'int');
		$search 			= $app->getUserStateFromRequest('com_jem.day.filter_search', 'filter_search', '', 'string');
		$search 			= $db->escape(trim(JString::strtolower($search)));

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		//get data from model
		$rows 		= $this->get('Data');
		$day		= $this->get('Day');

		$daydate 	= JEMOutput::formatdate($day);

		//are events available?
		if (!$rows) {
			$noevents = 1;
		} else {
			$noevents = 0;
		}

		//params
		$params->def('page_title', $item->title);

		if ($requestVenueId){
			$print_link = JRoute::_('index.php?view=day&tmpl=component&print=1&locid='.$requestVenueId.'&id='.$requestDate);
		}
		if ($requestCategoryId){
			$print_link = JRoute::_('index.php?view=day&tmpl=component&print=1&catid='.$requestCategoryId.'&id='.$requestDate);
		}
		if (!$requestCategoryId && !$requestVenueId){
			$print_link = JRoute::_('index.php?view=day&tmpl=component&print=1&id='.$requestDate);
		}

		//pathway
		$pathway->setItemName(1, $item->title);

		//Set Page title
		if (!$item->title) {
			$document->setTitle($params->get('page_title'));
			$document->setMetadata('keywords' , $params->get('page_title'));
		}

		//Check if the user has access to the form
		$maintainer = JEMUser::ismaintainer();
		$genaccess 	= JEMUser::validate_user($jemsettings->evdelrec, $jemsettings->delivereventsyes);

		if ($maintainer || $genaccess) {
			$dellink = 1;
		} else {
			$dellink = 0;
		}

		//add alternate feed link
		$link    = 'index.php?option=com_jem&view=day&format=feed&id=' . date('Ymd', strtotime($this->get('Day')));
		$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
		$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
		$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
		$document->addHeadLink(JRoute::_($link.'&type=atom'), 'alternate', 'rel', $attribs);

		//search filter
		$filters = array();

		if ($jemsettings->showtitle == 1) {
			$filters[] = JHTML::_('select.option', '1', JText::_('COM_JEM_TITLE'));
		}
		if ($jemsettings->showlocate == 1 && !($requestVenueId)) {
			$filters[] = JHTML::_('select.option', '2', JText::_('COM_JEM_VENUE'));
		}
		if ($jemsettings->showcity == 1 && !($requestVenueId)) {
			$filters[] = JHTML::_('select.option', '3', JText::_('COM_JEM_CITY'));
		}
		if ($jemsettings->showcat == 1 && !($requestCategoryId)) {
			$filters[] = JHTML::_('select.option', '4', JText::_('COM_JEM_CATEGORY'));
		}
		if ($jemsettings->showstate == 1 && !($requestVenueId)) {
			$filters[] = JHTML::_('select.option', '5', JText::_('COM_JEM_STATE'));
		}
		$lists['filter'] = JHTML::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $filter);

		// search filter
		$lists['search']= $search;

		// Create the pagination object
		$pagination = $this->get('Pagination');

		$this->lists			= $lists;
		$this->rows				= $rows;
		$this->noevents			= $noevents;
		$this->print_link		= $print_link;
		$this->params			= $params;
		$this->dellink			= $dellink;
		$this->pagination		= $pagination;
		$this->action			= $uri->toString();
		$this->task				= $task;
		$this->jemsettings		= $jemsettings;
		$this->lists			= $lists;
		$this->daydate			= $daydate;

		parent::display($tpl);
	}

	/**
	 * Manipulate Data
	 *
	 * @access public
	 * @return object $rows
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