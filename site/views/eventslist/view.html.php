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

// no direct access
defined( '_JEXEC' ) or die;

jimport( 'joomla.application.component.view');

/**
 * HTML View class for the JEM View
 *
 * @package JEM
 * @since 0.9
*/
class JEMViewEventslist extends JViewLegacy
{
	/**
	 * Creates the Simple List View
	 *
	 * @since 0.9
	 */
	function display( $tpl = null )
	{
		$app = JFactory::getApplication();

		//initialize variables
		$document 	= JFactory::getDocument();
		$jemsettings = JEMHelper::config();
		$menu		= $app->getMenu();
		$item		= $menu->getActive();
		$params 	= $app->getParams();
		$uri 		= JFactory::getURI();
		$pathway 	= $app->getPathWay();
		$db  		=  JFactory::getDBO();

		//add css file
		$document->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');

		// get variables (original)
		//$limitstart	= JRequest::getVar('limitstart', 0, '', 'int');
		//$limit		= $app->getUserStateFromRequest('com_jem.eventslist.limit', 'limit', $params->def('display_num', 0), 'int');


		$filter_order		= $app->getUserStateFromRequest( 'com_jem.eventslist.filter_order', 'filter_order', 	'a.dates', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.eventslist.filter_order_Dir', 'filter_order_Dir',	'', 'word' );
		$filter_state 		= $app->getUserStateFromRequest( 'com_jem.eventslist.filter_state', 'filter_state', 	'*', 'word' );
		$filter 			= $app->getUserStateFromRequest( 'com_jem.eventslist.filter', 'filter', '', 'int' );
		$search 			= $app->getUserStateFromRequest( 'com_jem.eventslist.search', 'search', '', 'string' );
		$search 			= $db->escape( trim(JString::strtolower( $search ) ) );

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;


		$task 		= JRequest::getWord('task');
		$pop		= JRequest::getBool('pop');

		//get data from model
		$rows 	= $this->get('Data');
		$total 	= $this->get('Total');

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
		$document->setTitle($pagetitle);
		$document->setMetaData( 'title' , $pagetitle );


		//Check if the user has access to the form
		$maintainer = JEMUser::ismaintainer();
		$genaccess 	= JEMUser::validate_user( $jemsettings->evdelrec, $jemsettings->delivereventsyes );

		if ($maintainer || $genaccess )
		{
			$dellink = 1;
		} else {
			$dellink = 0;
		}

		//add alternate feed link
		$link	= 'index.php?option=com_jem&view=eventslist&format=feed';
		$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
		$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
		$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
		$document->addHeadLink(JRoute::_($link.'&type=atom'), 'alternate', 'rel', $attribs);

		//create select lists (original)
		//$lists	= $this->_buildSortLists();


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



		/*
		 if ($lists['filter']) {
		//$uri->setVar('filter', JRequest::getString('filter'));
		//$filter		= $app->getUserStateFromRequest('com_jem.jem.filter', 'filter', '', 'string');
		$uri->setVar('filter', $lists['filter']);
		$uri->setVar('filter_type', JRequest::getString('filter_type'));
		} else {
		$uri->delVar('filter');
		$uri->delVar('filter_type');
		}
		*/
		// Create the pagination object
		$pagination = $this->get('Pagination');

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
		$jemsettings = JEMHelper::config();

		$filter_order		= JRequest::getCmd('filter_order', 'a.dates');
		$filter_order_Dir	= JRequest::getWord('filter_order_Dir', 'ASC');

		$filter				= $this->escape(JRequest::getString('filter'));
		$filter_type		= JRequest::getString('filter_type');

		$sortselects = array();

		if ($jemsettings->showtitle == 1) {
			$sortselects[]	= JHTML::_('select.option', 'title', JText::_('COM_JEM_TABLE_TITLE'));
		}
		if ($jemsettings->showlocate == 1) {
			$sortselects[] 	= JHTML::_('select.option', 'venue', JText::_('COM_JEM_TABLE_LOCATION'));
		}
		if ($jemsettings->showcity == 1) {
			$sortselects[] 	= JHTML::_('select.option', 'city', JText::_('COM_JEM_TABLE_CITY'));
		}
		if ($jemsettings->showcat == 1) {
			$sortselects[] 	= JHTML::_('select.option', 'type', JText::_('COM_JEM_TABLE_CATEGORY'));
		}
		if ($jemsettings->showstate == 1) {
			$sortselects[] 	= JHTML::_('select.option', 'state', JText::_('COM_JEM_TABLE_STATE'));
		}

		$sortselect 	= JHTML::_('select.genericlist', $sortselects, 'filter_type', 'size="1" class="inputbox"', 'value', 'text', $filter_type );

		$lists['order_Dir'] 	= $filter_order_Dir;
		$lists['order'] 		= $filter_order;
		$lists['filter'] 		= $filter;
		$lists['filter_types'] 	= $sortselect;

		return $lists;
	}
}
?>