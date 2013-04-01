<?php
/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

defined( '_JEXEC' ) or die;

jimport( 'joomla.application.component.view');

/**
 * View class for the EventList venueselect screen
 *
 * @package Joomla
 * @subpackage EventList
 * @since 0.9
 */
class EventListViewVenueelement extends JViewLegacy {

	function display($tpl = null)
	{
		$app =  JFactory::getApplication();

		//initialise variables
		$db			=  JFactory::getDBO();
		$document	=  JFactory::getDocument();
		
		JHTML::_('behavior.tooltip');
		JHTML::_('behavior.modal');

		//get vars
		$filter_order		= $app->getUserStateFromRequest( 'com_eventlist.venueelement.filter_order', 'filter_order', 'l.ordering', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_eventlist.venueelement.filter_order_Dir', 'filter_order_Dir', '', 'word' );
		$filter 			= $app->getUserStateFromRequest( 'com_eventlist.venueelement.filter', 'filter', '', 'int' );
		$filter_state 		= $app->getUserStateFromRequest( 'com_eventlist.venueelement.filter_state', 'filter_state', '*', 'word' );
		$search 			= $app->getUserStateFromRequest( 'com_eventlist.venueelement.search', 'search', '', 'string' );
		$search 			= $db->getEscaped( trim(JString::strtolower( $search ) ) );
		$template 			= $app->getTemplate();

		//prepare document
		$document->setTitle(JText::_( 'COM_EVENTLIST_SELECTVENUE' ));
		$document->addStyleSheet("templates/$template/css/general.css");

		// Get data from the model
		$rows      	=  $this->get( 'Data');
//		$total      = & $this->get( 'Total');
		$pageNav 	=  $this->get( 'Pagination' );

		//publish unpublished filter
		$lists['state']	= JHTML::_('grid.state', $filter_state );

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		//Build search filter
		$filters = array();
		$filters[] = JHTML::_('select.option', '1', JText::_( 'COM_EVENTLIST_VENUE' ) );
		$filters[] = JHTML::_('select.option', '2', JText::_( 'COM_EVENTLIST_CITY' ) );
		$lists['filter'] = JHTML::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $filter );

		// search filter
		$lists['search']= $search;

		//assign data to template
		$this->assignRef('lists'      	, $lists);
		$this->assignRef('rows'      	, $rows);
		$this->assignRef('pageNav' 		, $pageNav);

		parent::display($tpl);
	}
}
?>