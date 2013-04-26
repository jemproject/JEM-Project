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
 * View class for the JEM archive screen
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewArchive extends JViewLegacy {

	function display($tpl = null)
	{
		$app =  JFactory::getApplication();

		//initialise variables
		$document	=  JFactory::getDocument();
		$db			=  JFactory::getDBO();
		$user		=  JFactory::getUser();
		$jemsettings = JEMAdmin::config();

		//get vars
		$filter_order		= $app->getUserStateFromRequest( 'com_jem.archive.filter_order', 'filter_order', 'a.dates', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.archive.filter_order_Dir',	'filter_order_Dir',	'', 'word' );
		$filter 			= $app->getUserStateFromRequest( 'com_jem.archive.filter', 'filter', '', 'int' );
		$filter 			= intval( $filter );
		$search 			= $app->getUserStateFromRequest( 'com_jem.archive.search', 'search', '', 'string' );
		$search 			= $db->escape( trim(JString::strtolower( $search ) ) );
		$template			= $app->getTemplate();

		//add css and submenu to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		//Create Submenu
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_JEM' ), 'index.php?option=com_jem');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_EVENTS' ), 'index.php?option=com_jem&view=events');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_VENUES' ), 'index.php?option=com_jem&view=venues');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_CATEGORIES' ), 'index.php?option=com_jem&view=categories');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_ARCHIVESCREEN' ), 'index.php?option=com_jem&view=archive', true);
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_GROUPS' ), 'index.php?option=com_jem&view=groups');
		JSubMenuHelper::addEntry( JText::_( 'COM_JEM_HELP' ), 'index.php?option=com_jem&view=help');
		if (JFactory::getUser()->authorise('core.manage')) {
			JSubMenuHelper::addEntry( JText::_( 'COM_JEM_SETTINGS' ), 'index.php?option=com_jem&controller=settings&task=edit');
		}

		JHTML::_('behavior.tooltip');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'COM_JEM_ARCHIVESCREEN' ), 'archive' );
		JToolBarHelper::unarchiveList();
		JToolBarHelper::spacer();
		JToolBarHelper::deleteList();
		JToolBarHelper::spacer();
		JToolBarHelper::help( 'el.archive', true );

		// Get data from the model
		$rows      	=  $this->get( 'Data');
		//$total      = $this->get( 'Total');
		$pagination 	=  $this->get( 'Pagination' );

		//search filter
		$filters = array();
		$filters[] = JHTML::_('select.option', '1', JText::_( 'COM_JEM_EVENT_TITLE' ) );
		$filters[] = JHTML::_('select.option', '2', JText::_( 'COM_JEM_VENUE' ) );
		$filters[] = JHTML::_('select.option', '3', JText::_( 'COM_JEM_CITY' ) );
		$filters[] = JHTML::_('select.option', '4', JText::_( 'COM_JEM_CATEGORY' ) );
		$lists['filter'] = JHTML::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $filter );

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		// search filter
		$lists['search']= $search;

		//assign data to template
		$this->lists 		= $lists;
		$this->rows 		= $rows;
		$this->pagination 	= $pagination;
		$this->jemsettings 	= $jemsettings;
		$this->template 	= $template;
		$this->user 		= $user;

		parent::display($tpl);
	}
}
?>