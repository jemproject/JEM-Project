<?php
/**
 * @version 1.9 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 *
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');


/**
 * HTML View class for the Categoryevents View
 *
 * @package JEM
 * @since 0.9
*/
class JEMViewCategoryevents extends JViewLegacy
{
	/**
	 * Creates the Categoryevents View
	 *
	 * @since 0.9
	 */
	function display($tpl=null)
	{
		
		//initialize variables
		$app = JFactory::getApplication();
		$document 		= JFactory::getDocument();
		$menu			= $app->getMenu();
		$jemsettings 	= JEMHelper::config();
		$db  			=  JFactory::getDBO();
		//$item			= $menu->getActive();

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
		$filter_order		= $app->getUserStateFromRequest( 'com_jem.categoryevents.filter_order', 'filter_order', 	'a.dates', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.categoryevents.filter_order_Dir', 'filter_order_Dir',	'', 'word' );
		$filter_state 		= $app->getUserStateFromRequest( 'com_jem.categoryevents.filter_state', 'filter_state', 	'*', 'word' );
		$filter 			= $app->getUserStateFromRequest( 'com_jem.categoryevents.filter', 'filter', '', 'int' );
		$search 			= $app->getUserStateFromRequest( 'com_jem.categoryevents.search', 'search', '', 'string' );
		$search 			= $db->escape( trim(JString::strtolower( $search ) ) );
		$task 				= JRequest::getWord('task');
		$pop				= JRequest::getBool('pop');


		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] 	= $filter_order;


		//search filter
		$filters = array();

		if ($jemsettings->showtitle == 1) {
			$filters[] = JHTML::_('select.option', '1', JText::_( 'COM_JEM_TITLE' ) );
		}
		if ($jemsettings->showlocate == 1) {
			$filters[] = JHTML::_('select.option', '2', JText::_( 'COM_JEM_VENUE' ) );
		}
		if ($jemsettings->showcity == 1) {
			$filters[] = JHTML::_('select.option', '3', JText::_( 'COM_JEM_CITY' ) );
		}
		if ($jemsettings->showcat == 1) {
			$filters[] = JHTML::_('select.option', '4', JText::_( 'COM_JEM_CATEGORY' ) );
		}
		if ($jemsettings->showstate == 1) {
			$filters[] = JHTML::_('select.option', '5', JText::_( 'COM_JEM_STATE' ) );
		}
		$lists['filter'] = JHTML::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $filter );

		
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

		//Print function
		$params->def('print', !$app->getCfg('hidePrint'));
		$params->def('icons', $app->getCfg('icons'));

		if ($pop) {
			$params->set('popup', 1);
		}

		//add alternate feed link
		$link	= 'index.php?option=com_jem&view=categoryevents&format=feed&id='.$category->id;
		$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
		$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
		$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
		$document->addHeadLink(JRoute::_($link.'&type=atom'), 'alternate', 'rel', $attribs);

		//create the pathway
		$cats		= new JEMCategories($category->id);
		$parents	= $cats->getParentlist();

		foreach($parents as $parent) {
			$pathway->addItem($this->escape($parent->catname), JRoute::_('index.php?view=categoryevents&id='.$parent->categoryslug));
		}

		if ($task == 'archive') {
			$pathway->addItem(JText::_('COM_JEM_ARCHIVE').' - '.$category->catname, JRoute::_('index.php?option=com_jem&view=categoryevents&task=archive&id='.$category->slug));
			$link = JRoute::_('index.php?option=com_jem&view=categoryevents&task=archive&id='.$category->slug);
			$print_link = JRoute::_('index.php?option=com_jem&view=categoryevents&id='. $category->id .'&task=archive&print=1&tmpl=component');
			$pagetitle = $category->catname.' - '.JText::_('COM_JEM_ARCHIVE');
		} else {
			$pathway->addItem($category->catname, JRoute::_('index.php?option=com_jem&view=categoryevents&id='.$category->slug));
			$link = JRoute::_('index.php?option=com_jem&view=categoryevents&id='.$category->slug);
			$print_link = JRoute::_('index.php?option=com_jem&view=categoryevents&id='. $category->id .'&print=1&tmpl=component');
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
			$results = $app->triggerEvent('onContentPrepare', array('com_jem.categoryevents', &$category, &$params, 0));
			$catdescription = $category->text;
		}

		$cimage = JEMImage::flyercreator($category->image,'category');

		//create select lists
		$this->lists			= $lists;
		$this->action			= $uri->toString();
		$this->cimage				= $cimage;
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

		
		
		
		
		
		
		if($this->getLayout() == 'calendar')
		{
			$app =  JFactory::getApplication();

        // Load tooltips behavior
        JHTML::_('behavior.tooltip');

        //initialize variables
        $document 	=  JFactory::getDocument();
        $menu 		=  $app->getMenu();
        $jemsettings =  JEMHelper::config();
        $item 		= $menu->getActive();
        $params 	=  $app->getParams();
        $uri 		=  JFactory::getURI();
        $pathway 	=  $app->getPathWay();

        //add css file
        $document->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
        $document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');
        $document->addStyleSheet($this->baseurl.'/media/com_jem/css/calendar.css');
        
        // add javascript
       // $document->addScript($this->baseurl.'/media/com_jem/js/calendar.js');

        $category 	= $this->get('Category');
        
        $year 	= (int)JRequest::getVar('yearID', strftime("%Y"));
        $month 	= (int)JRequest::getVar('monthID', strftime("%m"));

        //get data from model and set the month
        $model =  $this->getModel();
        $model->setDate(mktime(0, 0, 1, $month, 1, $year));

        $rows =  $this->get('Data');

        //Set Meta data
        $document->setTitle($item->title);

        //Set Page title
        $pagetitle = $params->def('page_title', $item->title);
        $document->setTitle($pagetitle);
        $document->setMetaData('title', $pagetitle);

        //init calendar
		$cal = new JEMCalendar($year, $month, 0, $app->getCfg('offset'));
		$cal->enableMonthNav('index.php?view=categoryevents&layout=calendar&id='. $category->slug);
		$cal->setFirstWeekDay($params->get('firstweekday', 1));
		//$cal->enableDayLinks(false);
				
		$this->rows 		= $rows;
		$this->params		= $params;
		$this->jemsettings	= $jemsettings;
		$this->cal			= $cal;
		}

		parent::display($tpl);
	}

	/**
	 * Manipulate Data
	 *
	 * @since 0.9
	 */
	function &getRows()
	{
		$count = count($this->rows);

		if (!$count) {
			return;
		}

		$k = 0;
		foreach($this->rows as $key => $row)
		{
			$row->odd = $k;

			$this->rows[$key] = $row;
			$k = 1 - $k;
		}

		return $this->rows;
	}

	
	/**
	 * Creates a tooltip
	 *
	 * @access  public
	 * @param string  $tooltip The tip string
	 * @param string  $title The title of the tooltip
	 * @param string  $text The text for the tip
	 * @param string  $href An URL that will be used to create the link
	 * @param string  $class the class to use for tip.
	 * @return  string
	 * @since 1.5
	 */
	function caltooltip($tooltip, $title = '', $text = '', $href = '', $class = '')
	{
		$tooltip = (htmlspecialchars($tooltip));
		$title = (htmlspecialchars($title));
	
		if ($title) {
			$title = $title.'::';
		}
	
		if ($href) {
			$href = JRoute::_($href);
			$style = '';
			$tip = '<span class="'.$class.'" title="'.$title.$tooltip.'"><a href="'.$href.'">'.$text.'</a></span>';
		} else {
			$tip = '<span class="'.$class.'" title="'.$title.$tooltip.'">'.$text.'</span>';
		}
	
		return $tip;
	}
	

}
?>