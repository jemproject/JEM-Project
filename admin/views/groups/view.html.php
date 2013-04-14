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

defined( '_JEXEC' ) or die;

jimport( 'joomla.application.component.view');

/**
 * View class for the JEM groups screen
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewGroups extends JViewLegacy {

	function display($tpl = null)
	{
		$app = JFactory::getApplication();

		//initialise variables
		$document	=  JFactory::getDocument();
		$db			=  JFactory::getDBO();
		$user 		=  JFactory::getUser();

		//get vars
		$filter_order		= $app->getUserStateFromRequest( 'com_jem.groups.filter_order', 'filter_order', 	'name', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.groups.filter_order_Dir', 'filter_order_Dir', '', 'word' );
		$search 			= $app->getUserStateFromRequest( 'com_jem.groups.search', 'search', '', 'string' );
		$search 			= $db->getEscaped( trim(JString::strtolower( $search ) ) );
		$template			= $app->getTemplate();

		//add css and submenu to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		//Create Submenu
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_JEM' ), 'index.php?option=com_jem');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_EVENTS' ), 'index.php?option=com_jem&view=events');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_VENUES' ), 'index.php?option=com_jem&view=venues');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_CATEGORIES' ), 'index.php?option=com_jem&view=categories');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_ARCHIVESCREEN' ), 'index.php?option=com_jem&view=archive');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_GROUPS' ), 'index.php?option=com_jem&view=groups', true);
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_HELP' ), 'index.php?option=com_jem&view=help');
		if ($user->get('gid') > 24) {
			JSubMenuHelper::addEntry( JText::_( 'COM_JEM_SETTINGS' ), 'index.php?option=com_jem&controller=settings&task=edit');
		}

		JHTML::_('behavior.tooltip');

		// Get data from the model
		$rows      	=  $this->get( 'Data');
		//$total      = & $this->get( 'Total');
		$pagination 	=  $this->get( 'Pagination' );

		//create the toolbar
		JToolBarHelper::title( JText::_( 'COM_JEM_GROUPS' ), 'groups' );
		JToolBarHelper::addNew();
		JToolBarHelper::spacer();
		JToolBarHelper::editList();
		JToolBarHelper::spacer();
		JToolBarHelper::deleteList();
		JToolBarHelper::spacer();
		JToolBarHelper::help( 'el.listgroups', true );

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		// search filter
		$lists['search']= $search;

		//assign data to template
		$this->lists 		= $lists;
		$this->rows 		= $rows;
		$this->pagination 	= $pagination;
		$this->user 		= $user;
		$this->template 	= $template;

		parent::display($tpl);
	}
}
?>