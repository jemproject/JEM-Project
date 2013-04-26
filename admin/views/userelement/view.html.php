<?php
/**
 * @version 1.0 $Id: view.html.php 662 2008-05-09 22:28:53Z schlu $
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
 * View class for the JEM user element screen
 *
 * @package JEM
 * @since 1.1
 */
class JEMViewUserElement extends JViewLegacy {

	function display($tpl = null)
	{
		$mainframe = JFactory::getApplication();
		
		//initialise variables
		$document	=  JFactory::getDocument();
		$user 		=  JFactory::getUser();
		$jemsettings = JEMAdmin::config();
		$db = JFactory::getDBO();
		
		//get var
		$filter_order		= $mainframe->getUserStateFromRequest( 'com_jem.users.filter_order', 'filter_order', 'u.name', 'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( 'com_jem.users.filter_order_Dir', 'filter_order_Dir', '', 'word' );
		$search 			= $mainframe->getUserStateFromRequest( 'com_jem.users.search', 'search', '', 'string' );
		$search 			= $db->escape( trim(JString::strtolower( $search ) ) );
		
		//add css to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');
		
		$modelusers = JModelLegacy::getInstance('Users', 'JEMModel');
		
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
		$this->lists		= $lists;
		$this->rows			= $users;
		$this->jemsettings	= $jemsettings;
		$this->pagination	= $pagination;

		parent::display($tpl);
	}
}
?>