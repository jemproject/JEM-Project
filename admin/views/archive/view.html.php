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
 * View class for the JEM archive screen
 *
 * @package JEM
 *
 */
class JEMViewArchive extends JViewLegacy {

	protected $items;
	protected $pagination;
	protected $state;


	public function display($tpl = null)
	{

		//initialise variables
		$app =  JFactory::getApplication();
		$user 		=  JFactory::getUser();
		$document	=  JFactory::getDocument();
		$db			=  JFactory::getDBO();
		$jemsettings = JEMAdmin::config();
		$template			= $app->getTemplate();
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



	/*
	* Add Toolbar
	*/

	protected function addToolbar()
	{

	require_once JPATH_COMPONENT . '/helpers/helper.php';

	//create the toolbar
	JToolBarHelper::title( JText::_( 'COM_JEM_ARCHIVESCREEN' ), 'archive' );
	JToolBarHelper::unarchiveList('archive.unarchivetask');
	JToolBarHelper::spacer();
	JToolBarHelper::deleteList($msg = 'COM_JEM_CONFIRM_DELETE', $task = 'archive.removetask', $alt = 'JACTION_DELETE');
	JToolBarHelper::spacer();
	//JToolBarHelper::custom( 'archive.copy', 'copy.png', 'copy_f2.png', 'COM_JEM_COPY' );
	//JToolBarHelper::spacer();
	JToolBarHelper::help( 'archive', true );

	}


}
?>