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
 * View class for the JEM events screen
 *
 * @package JEM
 * 
*/
class JEMViewEvents extends JViewLegacy {

	public function display($tpl = null)
	{
		$app =  JFactory::getApplication();

		//initialise variables
		$user 		=  JFactory::getUser();
		$document	=  JFactory::getDocument();
		$db  		=  JFactory::getDBO();
		$jemsettings = JEMAdmin::config();

		//get vars
		$filter_order		= $app->getUserStateFromRequest( 'com_jem.events.filter_order', 'filter_order', 	'a.dates', 'cmd' );
		$filter_order_Dir	= $app->getUserStateFromRequest( 'com_jem.events.filter_order_Dir', 'filter_order_Dir',	'', 'word' );
		$filter_state 		= $app->getUserStateFromRequest( 'com_jem.events.filter_state', 'filter_state', 	'', 'string' );
		$filter 			= $app->getUserStateFromRequest( 'com_jem.events.filter', 'filter', '', 'int' );
		$search 			= $app->getUserStateFromRequest( 'com_jem.events.search', 'search', '', 'string' );
		$search 			= $db->escape( trim(JString::strtolower( $search ) ) );
		$template			= $app->getTemplate();

		//add css and submenu to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		JHTML::_('behavior.tooltip');

		// Get data from the model
		$rows      	=  $this->get( 'Data');
		$pagination 	=  $this->get( 'Pagination' );

		//publish unpublished filter
		$lists['state']	= $filter_state;

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		//search filter
		$filters = array();
		$filters[] = JHTML::_('select.option', '1', JText::_( 'COM_JEM_EVENT_TITLE' ) );
		$filters[] = JHTML::_('select.option', '2', JText::_( 'COM_JEM_VENUE' ) );
		$filters[] = JHTML::_('select.option', '3', JText::_( 'COM_JEM_CITY' ) );
		$filters[] = JHTML::_('select.option', '4', JText::_( 'COM_JEM_CATEGORY' ) );
		$filters[] = JHTML::_('select.option', '5', JText::_( 'COM_JEM_STATE' ) );
		$filters[] = JHTML::_('select.option', '6', JText::_( 'JALL' ) );
		$lists['filter'] = JHTML::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $filter );

		// search filter
		$lists['search']= $search;
		

		//assign data to template
		$this->lists 		= $lists;
		$this->rows 		= $rows;
		$this->pagination 	= $pagination;
		$this->user 		= $user;
		$this->template 	= $template;
		$this->jemsettings 	= $jemsettings;


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
		
		/* @todo check code
		 * categoryid/actions has to be altered
		 */

		/*
		 * retrieving the allowed actions for the user
		 * */
		$canDo = JEMHelperBackend::getActions(0);
		$user = JFactory::getUser();
		
		/* title, icon is mapped in css */
		JToolBarHelper::title( JText::_( 'COM_JEM_EVENTS' ), 'events' );
		
		
		/* create */
		if (($canDo->get('core.create')))
		{
			JToolBarHelper::addNew('event.add');
		}
		
		/* edit */
		JToolBarHelper::spacer();
		if (($canDo->get('core.edit')))
		{
			JToolBarHelper::editList('event.edit');
		}
		
		/* state */
		if ($canDo->get('core.edit.state'))
		{
		
		if ($this->lists['state'] != 2)
		{
		JToolBarHelper::publishList('events.publish');
		JToolBarHelper::spacer();
		JToolBarHelper::unpublishList('events.unpublish');
		JToolBarHelper::spacer();
	    }
	    
	    if ($this->lists['state'] != -1)
	    {
	    	JToolBarHelper::divider();
	    	if ($this->lists['state'] != 2)
	    	{
	    		JToolBarHelper::archiveList('events.archive');
	    	}
	    	elseif ($this->lists['state'] == 2)
	    	{
	    		JToolBarHelper::unarchiveList('events.unarchive');
	    	}

	    }
	    
		}
		
	
		/* delete-trash */
	    if ($this->lists['state'] == -2 && $canDo->get('core.delete'))
	    {
	    	JToolBarHelper::deleteList($msg = 'COM_JEM_CONFIRM_DELETE', $task = 'events.remove', $alt = 'JACTION_DELETE');
	    	JToolBarHelper::divider();
	    }
		elseif ($canDo->get('core.edit.state'))
		{
			JToolBarHelper::trash('events.trash');
			JToolBarHelper::divider();
		}
	    
		
		JToolBarHelper::spacer();
		JToolBarHelper::custom( 'events.copy', 'copy.png', 'copy_f2.png', 'COM_JEM_COPY' );
		JToolBarHelper::spacer();
		JToolBarHelper::help( 'listevents', true );
		
	}


}
?>