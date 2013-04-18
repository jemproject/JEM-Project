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
 * View class for the JEM eventelement screen
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewEventelement extends JViewLegacy {

	function display($tpl = null)
	{
		$app =  JFactory::getApplication();

		//initialise variables
		$user 		=  JFactory::getUser();
		$db			=  JFactory::getDBO();
		$jemsettings =  JEMAdmin::config();
		$document	=  JFactory::getDocument();
		
		JHTML::_('behavior.tooltip');
		JHTML::_('behavior.modal');

		//get var
		$filter_order		= $app->getUserStateFromRequest( 'com_jem.eventelement.filter_order', 'filter_order', 'a.dates', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.eventelement.filter_order_Dir', 'filter_order_Dir', '', 'word' );
		$filter 			= $app->getUserStateFromRequest( 'com_jem.eventelement.filter', 'filter', '', 'int' );
		$filter_state 		= $app->getUserStateFromRequest( 'com_jem.eventelement.filter_state', 'filter_state', '*', 'word' );
		$search 			= $app->getUserStateFromRequest( 'com_jem.eventelement.search', 'search', '', 'string' );
		$search 			= $db->getEscaped( trim(JString::strtolower( $search ) ) );
		$template 			= $app->getTemplate();

		//prepare the document
		$document->setTitle(JText::_( 'SELECTEVENT' ));
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		//Get data from the model
		$rows      	=  $this->get( 'Data');
//		$total      = $this->get( 'Total');
		$pagination 	=  $this->get( 'Pagination' );

		//publish unpublished filter
		$lists['state']	= JHTML::_('grid.state', $filter_state );

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		//Create the filter selectlist
		$filters = array();
		$filters[] = JHTML::_('select.option', '1', JText::_( 'EVENT TITLE' ) );
		$filters[] = JHTML::_('select.option', '2', JText::_( 'VENUE' ) );
		$filters[] = JHTML::_('select.option', '3', JText::_( 'CITY' ) );
	//	$filters[] = JHTML::_('select.option', '4', JText::_( 'CATEGORY' ) );
		$lists['filter'] = JHTML::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $filter );

		// search filter
		$lists['search']= $search;

		//assign data to template
		$this->lists 		= $lists;
		$this->rows 		= $rows;
		$this->pagination 	= $pagination;
		$this->jemsettings 	= $jemsettings;
		$this->user 		= $user;

		parent::display($tpl);
	}

}//end class
?>