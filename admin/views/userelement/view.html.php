<?php
/**
 * @version 1.0 $Id: view.html.php 662 2008-05-09 22:28:53Z schlu $
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2008 Christoph Lukes
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

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.application.component.view');

/**
 * View class for the EventList user element screen
 *
 * @package Joomla
 * @subpackage EventList
 * @since 1.1
 */
class EventListViewUserElement extends JView {

	function display($tpl = null)
	{
		$mainframe = &JFactory::getApplication();
		
		//initialise variables
		$document	= & JFactory::getDocument();
		$user 		= & JFactory::getUser();
		$elsettings = ELAdmin::config();
		$db = &JFactory::getDBO();
		
		//get var
		$filter_order		= $mainframe->getUserStateFromRequest( 'com_eventlist.users.filter_order', 'filter_order', 'u.name', 'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( 'com_eventlist.users.filter_order_Dir', 'filter_order_Dir', '', 'word' );
		$search 			= $mainframe->getUserStateFromRequest( 'com_eventlist.users.search', 'search', '', 'string' );
		$search 			= $db->getEscaped( trim(JString::strtolower( $search ) ) );
		
		//add css to document
		$document->addStyleSheet('components/com_eventlist/assets/css/eventlistbackend.css');
		
		$modelusers = &JModel::getInstance('Users', 'EventlistModel');
		
		$users = $modelusers->getData();
		$pagination = $modelusers->getPagination();
		
		//build selectlists
		$lists = array();
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;
		// search filter
		$lists['search']= $search;

		//assign data to template
		$this->assignRef('lists'      , $lists);
		$this->assignRef('rows'       , $users);
		$this->assignRef('elsettings'	, $elsettings);
		$this->assignRef('pageNav'	  , $pagination);

		parent::display($tpl);
	}
}
?>