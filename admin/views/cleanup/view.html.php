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
 * View class for the EventList Cleanup screen
 *
 * @package Joomla
 * @subpackage EventList
 * @since 0.9
 */
class EventListViewCleanup extends JViewLegacy {

	function display($tpl = null) {

		$app =  JFactory::getApplication();

		//initialise variables
		$document		=  JFactory::getDocument();
		$user			=  JFactory::getUser();

		//only admins have access to this view
		if (!JFactory::getUser()->authorise('core.manage')) {
			JError::raiseWarning( 'SOME_ERROR_CODE', JText::_( 'COM_JEM_ALERTNOTAUTH'));
			$app->redirect( 'index.php?option=com_jem&view=eventlist' );
		}

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
		if (!JFactory::getUser()->authorise('core.manage')) {
			JSubMenuHelper::addEntry( JText::_( 'COM_JEM_SETTINGS' ), 'index.php?option=com_jem&controller=settings&task=edit');
		}

		//create the toolbar
		JToolBarHelper::title( JText::_( 'COM_JEM_CLEANUP' ), 'housekeeping' );
		JToolBarHelper::help( 'el.cleanup', true );

		parent::display($tpl);
	}
}
?>