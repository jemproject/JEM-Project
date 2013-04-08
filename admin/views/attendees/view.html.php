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
 * View class for the EventList attendees screen
 *
 * @package Joomla
 * @subpackage EventList
 * @since 0.9
 */
class EventListViewAttendees extends JViewLegacy {

	function display($tpl = null)
	{
		$app =  JFactory::getApplication();

		if($this->getLayout() == 'print') {
			$this->_displayprint($tpl);
			return;
		}

		//initialise variables
		$db			=  JFactory::getDBO();
		$elsettings = ELAdmin::config();
		$document	=  JFactory::getDocument();
		$user		=  JFactory::getUser();

		//get vars
		$filter_order		= $app->getUserStateFromRequest( 'com_jem.attendees.filter_order', 'filter_order', 'u.username', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.attendees.filter_order_Dir',	'filter_order_Dir',	'', 'word' );
		$filter_waiting	= $app->getUserStateFromRequest( 'com_jem.attendees.waiting',	'filter_waiting',	0, 'int' );
		$filter 			= $app->getUserStateFromRequest( 'com_jem.attendees.filter', 'filter', '', 'int' );
		$search 			= $app->getUserStateFromRequest( 'com_jem.attendees.search', 'search', '', 'string' );
		$search 			= $db->getEscaped( trim(JString::strtolower( $search ) ) );

		//add css and submenu to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/eventlistbackend.css');

		//Create Submenu
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_EVENTLIST' ), 'index.php?option=com_jem');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_EVENTS' ), 'index.php?option=com_jem&view=events');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_VENUES' ), 'index.php?option=com_jem&view=venues');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_CATEGORIES' ), 'index.php?option=com_jem&view=categories');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_ARCHIVESCREEN' ), 'index.php?option=com_jem&view=archive');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_GROUPS' ), 'index.php?option=com_jem&view=groups');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_HELP' ), 'index.php?option=com_jem&view=help');
		if ($user->get('gid') > 24) {
			JSubMenuHelper::addEntry( JText::_( 'COM_JEM_SETTINGS' ), 'index.php?option=com_jem&controller=settings&task=edit');
		}

		//add toolbar
		JToolBarHelper::title( JText::_( 'COM_JEM_REGISTERED_USERS' ), 'users' );
		JToolBarHelper::addNew();
		JToolBarHelper::editList();
		JToolBarHelper::spacer();
		JToolBarHelper::deleteList();
		JToolBarHelper::spacer();
		JToolBarHelper::custom('back', 'back', 'back', JText::_('COM_JEM_BACK'), false);
		JToolBarHelper::spacer();
		JToolBarHelper::help( 'el.registereduser', true );

		// Get data from the model
		$rows      	=  $this->get( 'Data');
		$pageNav 	=  $this->get( 'Pagination' );
		$event 		=  $this->get( 'Event' );

 		if (ELHelper::isValidDate($event->dates)) {
			$event->dates = strftime($elsettings->formatdate, strtotime( $event->dates ));
		} 
		else {
			$event->dates		= JText::_('COM_JEM_OPEN_DATE');
		}

		//build filter selectlist
		$filters = array();
		$filters[] = JHTML::_('select.option', '1', JText::_( 'COM_JEM_NAME' ) );
		$filters[] = JHTML::_('select.option', '2', JText::_( 'COM_JEM_USERNAME' ) );
		$lists['filter'] = JHTML::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $filter );

		// search filter
		$lists['search'] = $search;

		// waiting list status
		$options = array( JHTML::_('select.option', 0, JText::_('all')), 
		                  JHTML::_('select.option', 1, JText::_('attending')), 
		                  JHTML::_('select.option', 2, JText::_('waiting')) ) ;
		$lists['waiting'] = JHTML::_('select.genericlist', $options, 'filter_waiting', 'onChange="this.form.submit();"', 'value', 'text', $filter_waiting);
		
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order']		= $filter_order;

		//assign to template
		$this->assignRef('lists'      	, $lists);
		$this->assignRef('rows'      	, $rows);
		$this->assignRef('pageNav' 		, $pageNav);
		$this->assignRef('event'		, $event);

		parent::display($tpl);
	}

	/**
	 * Prepares the print screen
	 *
	 * @param $tpl
	 *
	 * @since 0.9
	 */
	function _displayprint($tpl = null)
	{
		$elsettings = ELAdmin::config();
		$document	=  JFactory::getDocument();
		$document->addStyleSheet(JURI::root().'media/com_jem/css/eventlistbackend.css');

		$rows      	=  $this->get( 'Data');
		$event 		=  $this->get( 'Event' );

	
		if (ELHelper::isValidDate($row->dates)) {
			$event->dates = strftime($elsettings->formatdate, strtotime( $event->dates ));
//			$date		= strftime( $this->elsettings->formatdate, strtotime( $row->dates ));
		} 
		else {
			$event->dates	= JText::_('COM_JEM_OPEN_DATE');
		}
		
		//assign data to template
		$this->assignRef('rows'      	, $rows);
		$this->assignRef('event'		, $event);

		parent::display($tpl);
	}
}
?>