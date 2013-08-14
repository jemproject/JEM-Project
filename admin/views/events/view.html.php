<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 */

defined( '_JEXEC' ) or die;


/**
 * View class for the JEM Events screen
 *
 * @package Joomla
 * @subpackage JEM
 *
 */

 class JEMViewEvents extends JViewLegacy {


	protected $items;
	protected $pagination;
	protected $state;



	public function display($tpl = null)
	{

		$app =  JFactory::getApplication();
		$user 		=  JFactory::getUser();
		$document	=  JFactory::getDocument();


		$jemsettings = JEMAdmin::config();
		$url 		= JURI::root();

        // Initialise variables.
		$this->items		= $this->get('Items');
		$this->pagination	= $this->get('Pagination');
		$this->state		= $this->get('State');

		// Retrieving params
		$params = $this->state->get('params');

		// highlighter
		$highlighter = $params->get('highlight','0');


		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			JError::raiseError(500, implode("\n", $errors));
			return false;
		}

		// loading Mootools
		JHtml::_('behavior.framework');

		//add css and submenu to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');
		$document->addScript('http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js');
		$document->addCustomTag( '<script type="text/javascript">jQuery.noConflict();</script>' );

		if ($highlighter){
		$document->addScript($url.'media/com_jem/js/highlighter.js');
		$style = '
        .red a:link, .red a:visited, .red a:active {
        color:red;}
        '
		 ;
		 $document->addStyleDeclaration( $style );
		}

		//add style to description of the tooltip (hastip)
		JHTML::_('behavior.tooltip');

		// add filter selection for the search
		$filters = array();
		$filters[] = JHTML::_('select.option', '1', JText::_( 'COM_JEM_EVENT_TITLE' ) );
		$filters[] = JHTML::_('select.option', '2', JText::_( 'COM_JEM_CITY' ) );
		$filters[] = JHTML::_('select.option', '3', JText::_( 'COM_JEM_STATE' ) );
		$filters[] = JHTML::_('select.option', '4', JText::_( 'COM_JEM_COUNTRY' ) );
		$filters[] = JHTML::_('select.option', '5', JText::_( 'COM_JEM_CATEGORY' ) );
		$filters[] = JHTML::_('select.option', '6', JText::_( 'JALL' ) );
		$lists['filter'] = JHTML::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $this->state->get('filter') );


		//assign data to template
		$this->lists		= $lists;
		$this->user			= $user;
		$this->jemsettings  = $jemsettings;

		// add toolbar
		$this->addToolbar();


		parent::display($tpl);
		}





	 /**
	  * Add Toolbar
	  */

	protected function addToolbar()
	{

		/* submenu */
		require_once JPATH_COMPONENT . '/helpers/helper.php';

		/* Adding title + icon
		 *
		 * the icon is mapped within backend.css
		 * The word 'venues' is referring to the venues icon
		 * */
		JToolBarHelper::title( JText::_( 'COM_JEM_EVENTS' ), 'events' );

		/* retrieving the allowed actions for the user */
		$canDo = JEMHelperBackend::getActions(0);
		$user = JFactory::getUser();

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

			if ($this->state->get('filter_state') != 2)
			{
				JToolBarHelper::publishList('events.publish', 'JTOOLBAR_PUBLISH', true);
				JToolBarHelper::unpublishList('events.unpublish', 'JTOOLBAR_UNPUBLISH', true);
			}

			if ($this->state->get('filter_state') != -1)
			{
				JToolBarHelper::divider();
				if ($this->state->get('filter_state') != 2)
				{
					JToolBarHelper::archiveList('events.archive');
				}
				elseif ($this->state->get('filter_state') == 2)
				{
					JToolBarHelper::unarchiveList('events.publish');
				}

			}

		}


		if ($canDo->get('core.edit.state'))
		{
			JToolBarHelper::checkin('events.checkin');
		}


		if ($this->state->get('filter_state') == -2 && $canDo->get('core.delete'))
		{
			JToolBarHelper::deleteList('', 'events.delete', 'JTOOLBAR_EMPTY_TRASH');
			JToolBarHelper::divider();
		}
		elseif ($canDo->get('core.edit.state'))
		{
			JToolBarHelper::trash('events.trash');
			JToolBarHelper::divider();
		}


		JToolBarHelper::help( 'listevents', true );



	}



} // end of class



?>