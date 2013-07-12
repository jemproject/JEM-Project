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
 * View class for the JEM archive screen
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewArchive extends JViewLegacy {

	public function display($tpl = null)
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

		JHTML::_('behavior.tooltip');

		// Get data from the model
		$rows      	=  $this->get( 'Data');
		$pagination 	=  $this->get( 'Pagination' );

		//search filter
		$filters = array();
		$filters[] = JHTML::_('select.option', '1', JText::_( 'COM_JEM_EVENT_TITLE' ) );
		$filters[] = JHTML::_('select.option', '2', JText::_( 'COM_JEM_VENUE' ) );
		$filters[] = JHTML::_('select.option', '3', JText::_( 'COM_JEM_CITY' ) );
		$filters[] = JHTML::_('select.option', '4', JText::_( 'COM_JEM_CATEGORY' ) );
		$filters[] = JHTML::_('select.option', '5', JText::_( 'JALL' ) );
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
	JToolBarHelper::title( JText::_( 'COM_JEM_ARCHIVESCREEN' ), 'archive' );
	JToolBarHelper::unarchiveList();
	JToolBarHelper::spacer();
	JToolBarHelper::deleteList();
	JToolBarHelper::spacer();
	JToolBarHelper::custom( 'copy', 'copy.png', 'copy_f2.png', 'COM_JEM_COPY' );
	JToolBarHelper::spacer();
	JToolBarHelper::help( 'archive', true );

	}
	

}
?>