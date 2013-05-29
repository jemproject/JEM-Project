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
 * View class for the JEM Venues screen
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewVenues extends JViewLegacy {
	
	protected $state;
	protected $items;
	protected $pagination;

	
	public function display($tpl = null)
	{
		$app =  JFactory::getApplication();
		$template			= $app->getTemplate();

		//initialise variables
		$user 		=  JFactory::getUser();
		$db 		=  JFactory::getDBO();
		$document	=  JFactory::getDocument();

		
		//add css and submenu to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		JHTML::_('behavior.tooltip');

		// Get data from the model
		$this->rows      	=  $this->get( 'Items');
		$this->pagination 	=  $this->get( 'Pagination' );
		$this->state		= $this->get('State');
		
		$filters = array();
		$filters[] = JHTML::_('select.option', '1', JText::_( 'COM_JEM_VENUE' ) );
		$filters[] = JHTML::_('select.option', '2', JText::_( 'COM_JEM_CITY' ) );
		$filters[] = JHTML::_('select.option', '3', JText::_( 'COM_JEM_STATE' ) );
		$filters[] = JHTML::_('select.option', '4', JText::_( 'COM_JEM_COUNTRY' ) );
		$filters[] = JHTML::_('select.option', '5', JText::_( 'JALL' ) );		
		$lists['filter'] = JHTML::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $this->state->get('filter') );
		
		
		//assign data to template
		$this->lists		= $lists;
		$this->user			= $user;
		$this->template		= $template;

		
		// add toolbar
		$this->addToolbar();
		
		parent::display($tpl);
	}
	
	
	/*
	* Add Toolbar
	*/
	
	protected function addToolbar()
	{
		
	//create the toolbar
	JToolBarHelper::title( JText::_( 'COM_JEM_VENUES' ), 'venues' );
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
	JToolBarHelper::custom( 'copy', 'copy.png', 'copy_f2.png', 'COM_JEM_COPY' );
	JToolBarHelper::spacer();
	JToolBarHelper::help( 'el.listvenues', true );
	
	//Create Submenu
	require_once JPATH_COMPONENT . '/helpers/helper.php';
	
	}
	
	
	
} // end of class
?>