<?php
/**
 * @version 1.1 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
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

// no direct access
defined( '_JEXEC' ) or die;

jimport( 'joomla.application.component.view');

/**
 * HTML View class for the JEM View
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewSearch extends JViewLegacy
{
	/**
	 * Creates the Simple List View
	 *
	 * @since 0.9
	 */
	function display( $tpl = null )
	{
		$app =  JFactory::getApplication();

		//initialize variables
		$document 	=  JFactory::getDocument();
		$jemsettings =  JEMHelper::config();
		$menu		=  $app->getMenu();
		$item    	= $menu->getActive();
		$params 	=  $app->getParams();
		$uri 		=  JFactory::getURI();
		$pathway 	=  $app->getPathWay();

		//add css file
		$document->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');
    	
		// get variables
		$limitstart        = JRequest::getVar('limitstart', 0, '', 'int');
		$limit             = $app->getUserStateFromRequest('com_jem.search.limit', 'limit', $params->def('display_num', 0), 'int');
		$filter_continent  = $app->getUserStateFromRequest('com_jem.search.filter_continent', 'filter_continent', '', 'string');
		$filter_country    = $app->getUserStateFromRequest('com_jem.search.filter_country', 'filter_country', '', 'string');
		$filter_city       = $app->getUserStateFromRequest('com_jem.search.filter_city', 'filter_city', '', 'string');
		$filter_date_from  = $app->getUserStateFromRequest('com_jem.search.filter_date_from', 'filter_date_from', '', 'string');
		$filter_date_to    = $app->getUserStateFromRequest('com_jem.search.filter_date_to', 'filter_date_to', '', 'string');
		$filter_category 	 = $app->getUserStateFromRequest('com_jem.search.filter_category', 'filter_category', 0, 'int');
		$task              = JRequest::getWord('task');
		$pop               = JRequest::getBool('pop');

		//get data from model
		$rows 	=  $this->get('Data');
		$total 	=  $this->get('Total');

		//are events available?
		if (!$rows) {
			$noevents = 1;
		} else {
			$noevents = 0;
		}

		//params
		$params->def( 'page_title', $item->title);

		if ( $pop ) {//If printpopup set true
			$params->set( 'popup', 1 );
		}

		//pathway
		$pathway->setItemName( 1, $item->title );
		
		if ( $task == 'archive' ) {
			$pathway->addItem(JText::_( 'COM_JEM_ARCHIVE' ), JRoute::_('index.php?view=eventslist&task=archive') );
			$print_link = JRoute::_('index.php?view=eventslist&task=archive&tmpl=component&print=1');
			$pagetitle = $params->get('page_title').' - '.JText::_( 'COM_JEM_ARCHIVE' );
		} else {
			$print_link = JRoute::_('index.php?view=eventslist&tmpl=component&print=1');
			$pagetitle = $params->get('page_title');
		}
		
		//Set Page title
		$document->setTitle( $pagetitle );
		$document->setMetadata( 'title' , $pagetitle );

		//Check if the user has access to the form
		$maintainer = ELUser::ismaintainer();
		$genaccess 	= ELUser::validate_user( $jemsettings->evdelrec, $jemsettings->delivereventsyes );

		if ($maintainer || $genaccess ) 
		{ 
		$dellink = 1;
		} else {
		$dellink = 0;	
		}

		//add alternate feed link
		$link    = 'index.php?option=com_jem&view=eventslist&format=feed';
		$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
		$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
		$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
		$document->addHeadLink(JRoute::_($link.'&type=atom'), 'alternate', 'rel', $attribs);
		
		//create select lists
		$lists	= $this->_buildSortLists();
		
		if ($lists['filter']) {
			//$uri->setVar('filter', JRequest::getString('filter'));
			//$filter		= $app->getUserStateFromRequest('com_jem.jem.filter', 'filter', '', 'string');
			$uri->setVar('filter', $lists['filter']);
			$uri->setVar('filter_type', JRequest::getString('filter_type'));
		} else {
			$uri->delVar('filter');
			$uri->delVar('filter_type');
		}
		
		//Cause of group limits we can't use class here to build the categories tree
		$categories   = $this->get('CategoryTree');
		$catoptions   = array();
		$catoptions[] = JHTML::_('select.option', '0', JText::_('COM_JEM_SELECT_CATEGORY'));
		$catoptions   = array_merge($catoptions, JEMCategories::getcatselectoptions($categories));
		$selectedcats = ($filter_category) ? array($filter_category) : array();
    
		//build selectlists
		$lists['categories'] =  JHTML::_('select.genericlist', $catoptions, 'filter_category', 'size="1" class="inputbox"', 'value', 'text', $selectedcats);

		// Create the pagination object
		$pagination = $this->get('Pagination');
		
		// date filter
		$lists['date_from'] = JHTML::_('calendar', $filter_date_from, 'filter_date_from', 'filter_date_from', '%Y-%m-%d', 'class="inputbox"');
		$lists['date_to']   = JHTML::_('calendar', $filter_date_to, 'filter_date_to', 'filter_date_to', '%Y-%m-%d', 'class="inputbox"');

		// country filter
		$continents = array();
		$continents[] = JHTML::_('select.option', '', JText::_('COM_JEM_SELECT_CONTINENT'));
		$continents[] = JHTML::_('select.option', 'AF', JText::_('Africa'));
		$continents[] = JHTML::_('select.option', 'AS', JText::_('Asia'));
		$continents[] = JHTML::_('select.option', 'EU', JText::_('Europe'));
		$continents[] = JHTML::_('select.option', 'NA', JText::_('North America'));
		$continents[] = JHTML::_('select.option', 'SA', JText::_('South America'));
		$continents[] = JHTML::_('select.option', 'OC', JText::_('Oceania'));
		$continents[] = JHTML::_('select.option', 'AN', JText::_('Antartica'));
		$lists['continents'] = JHTML::_('select.genericlist', $continents, 'filter_continent', 'class="inputbox"', 'value', 'text', $filter_continent);
		unset($continents);
    
		// country filter
		$countries = array();
		$countries[] = JHTML::_('select.option', '', JText::_('COM_JEM_SELECT_COUNTRY'));
		$countries = array_merge($countries, $this->get('CountryOptions'));
		$lists['countries'] = JHTML::_('select.genericlist', $countries, 'filter_country', 'class="inputbox"', 'value', 'text', $filter_country);
		unset($countries);

		// city filter
		if ($filter_country) 
		{
			$cities = array();
			$cities[] = JHTML::_('select.option', '', JText::_('COM_JEM_SELECT_CITY'));
			$cities = array_merge($cities, $this->get('CityOptions'));
			$lists['cities'] = JHTML::_('select.genericlist', $cities, 'filter_city', 'class="inputbox"', 'value', 'text', $filter_city);
			unset($cities);
		}

		$this->lists			= $lists;
		$this->total			= $total;
		$this->action			= $uri->toString();

		$this->rows				= $rows;
		$this->task				= $task;
		$this->noevents			= $noevents;
		$this->print_link		= $print_link;
		$this->params			= $params;
		$this->dellink			= $dellink;
		$this->pagination		= $pagination;
		$this->jemsettings		= $jemsettings;
		$this->pagetitle		= $pagetitle;
		$this->filter_continent	= $filter_continent;
		$this->filter_country	= $filter_country;
		$this->document			= $document;

		parent::display($tpl);
	}

	/**
	 * Manipulate Data
	 *
	 * @access public
	 * @return object $rows
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
			$row->odd   = $k;
			
			$this->rows[$key] = $row;
			$k = 1 - $k;
		}

		return $this->rows;
	}

	/**
	 * Method to build the sortlists
	 *
	 * @access private
	 * @return array
	 * @since 0.9
	 */
	function _buildSortLists()
	{
		$jemsettings =  JEMHelper::config();
		
		$filter_order		= JRequest::getCmd('filter_order', 'a.dates');
		$filter_order_Dir	= JRequest::getWord('filter_order_Dir', 'ASC');

		$filter				= $this->escape(JRequest::getString('filter'));
		$filter_type		= JRequest::getString('filter_type');

		$sortselects = array();
		$sortselects[]	= JHTML::_('select.option', 'title', $jemsettings->titlename );
		$sortselects[] 	= JHTML::_('select.option', 'venue', $jemsettings->locationname );
		$sortselect 	= JHTML::_('select.genericlist', $sortselects, 'filter_type', 'size="1" class="inputbox"', 'value', 'text', $filter_type );

		$lists['order_Dir'] 	= $filter_order_Dir;
		$lists['order'] 		= $filter_order;
		$lists['filter'] 		= $filter;
		$lists['filter_types'] 	= $sortselect;

		return $lists;
	}
}
?>