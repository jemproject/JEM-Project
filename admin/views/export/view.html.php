<?php
/**
 * $Id$
 * @package Joomla
 * @subpackage Eventlist
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 *
 * Eventlist is maintained by the community located at
 * http://www.joomlaeventmanager.net
 *
 * Eventlist is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * Eventlist is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

defined( '_JEXEC' ) or die;

jimport( 'joomla.application.component.view');

/**
 * View class (based on the import screen)
 * 
 * @package Joomla
 * @subpackage EventList
 * @since 0.9
 */
class EventListViewExport extends JViewLegacy {

	function display($tpl = null)
	{
		//Load pane behavior
		jimport('joomla.html.pane');

		//initialise variables
		$document	=  JFactory::getDocument();
		$user 		=  JFactory::getUser();

		//build toolbar
		JToolBarHelper::title( JText::_( 'COM_EVENTLIST_EXPORT' ), 'home' );
		JToolBarHelper::help( 'el.import', true );

		//add css and submenu to document
		$document->addStyleSheet('components/com_eventlist/assets/css/eventlistbackend.css');

		//Create Submenu
		JSubMenuHelper::addEntry( JText::_( 'COM_EVENTLIST_EVENTLIST' ), 'index.php?option=com_eventlist', true);
		JSubMenuHelper::addEntry( JText::_( 'COM_EVENTLIST_EVENTS' ), 'index.php?option=com_eventlist&view=events');
		JSubMenuHelper::addEntry( JText::_( 'COM_EVENTLIST_VENUES' ), 'index.php?option=com_eventlist&view=venues');
		JSubMenuHelper::addEntry( JText::_( 'COM_EVENTLIST_CATEGORIES' ), 'index.php?option=com_eventlist&view=categories');
		JSubMenuHelper::addEntry( JText::_( 'COM_EVENTLIST_ARCHIVESCREEN' ), 'index.php?option=com_eventlist&view=archive');
		JSubMenuHelper::addEntry( JText::_( 'COM_EVENTLIST_GROUPS' ), 'index.php?option=com_eventlist&view=groups');
		JSubMenuHelper::addEntry( JText::_( 'COM_EVENTLIST_HELP' ), 'index.php?option=com_eventlist&view=help');
	//	if ($user->get('gid') > 24) {
	//		JSubMenuHelper::addEntry( JText::_( 'SETTINGS' ), 'index.php?option=com_eventlist&controller=settings&task=edit');
	//	}

		parent::display($tpl);

	}
}
?>