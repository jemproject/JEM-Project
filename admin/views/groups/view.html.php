<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


/**
 * View class for the JEM groups screen
 *
 * @package JEM
 * 
 */
class JEMViewGroups extends JViewLegacy {

	public function display($tpl = null)
	{
		$app = JFactory::getApplication();

		// initialise variables
		$document	=  JFactory::getDocument();
		$db			=  JFactory::getDBO();
		$user 		=  JFactory::getUser();

		// get vars
		$filter_order		= $app->getUserStateFromRequest( 'com_jem.groups.filter_order', 'filter_order', 	'name', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.groups.filter_order_Dir', 'filter_order_Dir', '', 'word' );
		$search 			= $app->getUserStateFromRequest( 'com_jem.groups.search', 'search', '', 'string' );
		$search 			= $db->escape( trim(JString::strtolower( $search ) ) );
		$template			= $app->getTemplate();

		//add css and submenu to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		// Tooltipping
		/*
		 * can be used by using a ::
		 * 
		 */
		JHTML::_('behavior.tooltip');

		// get data from the model
		$rows      	=  $this->get( 'Data');
		
		// add pagination
		$pagination 	=  $this->get( 'Pagination' );


		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		// search filter
		$lists['search']= $search;

		// assign data to template
		$this->lists 		= $lists;
		$this->rows 		= $rows;
		$this->pagination 	= $pagination;
		$this->user 		= $user;
		$this->template 	= $template;

		
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
	
	// create the toolbar
	JToolBarHelper::title( JText::_( 'COM_JEM_GROUPS' ), 'groups' );
	JToolBarHelper::addNew('groups.add');
	JToolBarHelper::spacer();
	JToolBarHelper::editList('groups.edit');
	JToolBarHelper::spacer();
	JToolBarHelper::deleteList($msg = 'COM_JEM_CONFIRM_DELETE', $task = 'groups.remove', $alt = 'JACTION_DELETE');
	JToolBarHelper::spacer();
	JToolBarHelper::help( 'listgroups', true );

	}
	
	
} // end of class
?>