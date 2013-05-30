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


/**
 * View class for the JEM categories screen
 *
 *
*/
class JEMViewCategories extends JViewLegacy {

	public function display($tpl = null)
	{
		$app =  JFactory::getApplication();

		//initialise variables
		$user 		=  JFactory::getUser();
		$db  		=  JFactory::getDBO();
		$document	=  JFactory::getDocument();

		JHTML::_('behavior.tooltip');

		//get vars
		$filter_order		= $app->getUserStateFromRequest( 'com_jem.categories.filter_order', 		'filter_order', 	'c.catname', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.categories.filter_order_Dir',	'filter_order_Dir',	'', 'word' );
		$filter_state 		= $app->getUserStateFromRequest( 'com_jem.categories.filter_state', 		'filter_state', 	'', 'string' );
		$search 			= $app->getUserStateFromRequest( 'com_jem.categories.search', 			'search', 			'', 'string' );
		$search 			= $db->escape( trim(JString::strtolower( $search ) ) );

		//add css and submenu to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		//Get data from the model
		$rows      	=  $this->get( 'Data');
		$pagination 	=  $this->get( 'Pagination' );

		//publish unpublished filter
		$lists['state']	= $filter_state;
		// search filter
		$lists['search']= $search;

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		$ordering = ($lists['order'] == 'c.ordering');

		//assign data to template
		$this->lists 		= $lists;
		$this->rows 		= $rows;
		$this->pagination 	= $pagination;
		$this->ordering 	= $ordering;
		$this->user 		= $user;


		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
	}



	/*
	* Add Toolbar
	*/

	protected function addToolbar()
	{
		
		require_once JPATH_COMPONENT . '/helpers/helper.php';
		
		//create the toolbar
		JToolBarHelper::title( JText::_( 'COM_JEM_CATEGORIES' ), 'elcategories' );
		JToolBarHelper::publishList();
		JToolBarHelper::spacer();
		JToolBarHelper::unpublishList();
		JToolBarHelper::spacer();
		JToolBarHelper::addNew();
		JToolBarHelper::spacer();
		JToolBarHelper::editList();
		JToolBarHelper::spacer();
		JToolBarHelper::deleteList();
		JToolBarHelper::spacer();
		JToolBarHelper::help( 'listcategories', true );
		
	}

}
?>