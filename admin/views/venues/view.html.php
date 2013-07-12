<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;



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
		$user 		=  JFactory::getUser();
		$document	=  JFactory::getDocument();

		
		// Get data from the model
		$this->rows      	=	$this->get('Items');
		$this->pagination 	=	$this->get('Pagination');
		$this->state		=	$this->get('State');

		
		//add css and submenu to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		//add style to description of the tooltip (addtip)
		JHTML::_('behavior.tooltip');

		// add filter selection for the search
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
		
		/* 
		 * Adding title + icon, the icon is mapped within backend.css
		 * The word 'venues' is referring to the venues icon
		 * */
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
		
		/* Reference to help-page located in the folder help.
		 * The variable 'true' is saying to look in the component directory
		 */
		JToolBarHelper::help( 'listvenues', true );

		//create Submenu
		require_once JPATH_COMPONENT . '/helpers/helper.php';

	}



} // end of class
?>