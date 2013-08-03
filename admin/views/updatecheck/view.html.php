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
 * View class for the JEM Updatecheck screen
 *
 * @package JEM
 * @since 0.9
 */
class JEMViewUpdatecheck extends JViewLegacy {

	public function display($tpl = null) {

		$app 	   =  JFactory::getApplication();

		//initialise variables
		$document	= JFactory::getDocument();

		//get vars
		$template	= $app->getTemplate();

		//add css
		$document->addStyleSheet('templates/'.$template.'/css/general.css');
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		//Get data from the model
		$updatedata      = $this->get( 'Updatedata');

		//assign data to template
		$this->template		= $template;
		$this->updatedata	= $updatedata;
		
		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
	}
	
	
	/**
	 * Add Toolbar
	 */
	protected function addToolbar()
	{
	
		
		//create the toolbar
		JToolBarHelper::title( JText::_( 'COM_JEM_UPDATE_CHECK' ), 'settings' );

		JToolBarHelper::back();
		
		//JToolBarHelper::help( 'updatecheck', true );
	}
	
}