<?php
/**
 * @version 1.9 $Id$
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

defined( '_JEXEC' ) or die;

jimport( 'joomla.application.component.view');

/**
 * View class for the JEM attendees screen
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewAttendees extends JViewLegacy {

	function display($tpl = null)
	{
		$app =  JFactory::getApplication();

		if($this->getLayout() == 'print') {
			$this->_displayprint($tpl);
			return;
		}

		//initialise variables
		$db			=  JFactory::getDBO();
		$jemsettings = JEMAdmin::config();
		$document	=  JFactory::getDocument();
		$user		=  JFactory::getUser();

		//get vars
		$filter_order		= $app->getUserStateFromRequest( 'com_jem.attendees.filter_order', 'filter_order', 'u.username', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.attendees.filter_order_Dir',	'filter_order_Dir',	'', 'word' );
		$filter_waiting	= $app->getUserStateFromRequest( 'com_jem.attendees.waiting',	'filter_waiting',	0, 'int' );
		$filter 			= $app->getUserStateFromRequest( 'com_jem.attendees.filter', 'filter', '', 'int' );
		$search 			= $app->getUserStateFromRequest( 'com_jem.attendees.search', 'search', '', 'string' );
		$search 			= $db->escape( trim(JString::strtolower( $search ) ) );

		//add css and submenu to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		//Create Submenu
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_JEM' ), 'index.php?option=com_jem');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_EVENTS' ), 'index.php?option=com_jem&view=events');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_VENUES' ), 'index.php?option=com_jem&view=venues');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_CATEGORIES' ), 'index.php?option=com_jem&view=categories');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_ARCHIVESCREEN' ), 'index.php?option=com_jem&view=archive');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_GROUPS' ), 'index.php?option=com_jem&view=groups');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_HELP' ), 'index.php?option=com_jem&view=help');
		if (JFactory::getUser()->authorise('core.manage')) {
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
		$pagination =  $this->get( 'Pagination' );
		$event 		=  $this->get( 'Event' );

 		if (JEMHelper::isValidDate($event->dates)) {
			$event->dates = JEMOutput::formatdate($event->dates);
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
		$options = array( JHTML::_('select.option', 0, JText::_('COM_JEM_ATT_FILTER_ALL')),
		                  JHTML::_('select.option', 1, JText::_('COM_JEM_ATT_FILTER_ATTENDING')),
		                  JHTML::_('select.option', 2, JText::_('COM_JEM_ATT_FILTER_WAITING')) ) ;
		$lists['waiting'] = JHTML::_('select.genericlist', $options, 'filter_waiting', 'onChange="this.form.submit();"', 'value', 'text', $filter_waiting);

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order']		= $filter_order;

		//assign to template
		$this->lists 		= $lists;
		$this->rows 		= $rows;
		$this->pagination 	= $pagination;
		$this->event 		= $event;

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
		$jemsettings = JEMAdmin::config();
		$document	=  JFactory::getDocument();
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		$rows      	=  $this->get( 'Data');
		$event 		=  $this->get( 'Event' );


		if (JEMHelper::isValidDate($event->dates)) {
			$event->dates = JEMOutput::formatdate($event->dates);
		}
		else {
			$event->dates	= JText::_('COM_JEM_OPEN_DATE');
		}

		//assign data to template
		$this->rows 		= $rows;
		$this->event 		= $event;

		parent::display($tpl);
	}
}
?>