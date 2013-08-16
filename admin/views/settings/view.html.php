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
 * View class for the JEM Settings screen
 *
 * @package JEM
 *
 */
class JEMViewSettings extends JViewLegacy {


	protected $form;
	protected $data;
	protected $state;


	public function display($tpl = null) {


		$form	= $this->get('Form');
		$data	= $this->get('Data');

		$jemsettings	= $this->get('Data');
		$document 	= JFactory::getDocument();
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');



		/*
		 * Bootstrap Css
		 * - Toolbar icons will be moved due to the background-position (icons)
		 *
		 */

		// $document->addStyleSheet(JURI::root().'media/com_jem/bootstrap/css/bootstrap.css'); */


		//var_dump($form);exit;
		//var_dump($data);exit;

		// Check for model errors.
		if ($errors = $this->get('Errors')) {
			JError::raiseError(500, implode('<br />', $errors));
			return false;
		}

		// Bind the form to the data.
		if ($form && $data) {
			$form->bind($data);
		}

		// Check for errors.
		if (count($errors = $this->get('Errors'))) {
			JError::raiseError(500, implode("\n", $errors));
			return false;
		}
		JHTML::_('behavior.modal', 'a.modal');
		JHTML::_('behavior.tooltip');
		JHTML::_('behavior.formvalidation');
		JHTML::_('behavior.framework');




		$app = JFactory::getApplication();

		//initialise variables

		$acl		= JFactory::getACL();
		$uri 		= JFactory::getURI();
		$user 		= JFactory::getUser();

		//get data from model
		//$model		= $this->getModel();
		// $jemsettings = $this->get( 'Data');

		//only admins have access to this view
		//if (!JFactory::getUser()->authorise('core.manage')) {
		//	JError::raiseWarning( 'SOME_ERROR_CODE', JText::_('COM_JEM_ALERTNOTAUTH'));
		//	$app->redirect( 'index.php?option=com_jem&view=jem' );
		//}

		// fail if checked out not by 'me'
		//if ($model->isCheckedOut( $user->get('id') )) {
		//	JError::raiseWarning( 'SOME_ERROR_CODE', JText::_('COM_JEM_EDITED_BY_ANOTHER_ADMIN'));
		//	$app->redirect( 'index.php?option=com_jem&view=jem' );
		//}

		//JHTML::_('behavior.tooltip');
		//JHTML::_('behavior.switcher');

		//add css, js and submenu to document
		//$document->addScript( JURI::root().'media/com_jem/js/settings.js' );


	   $accessLists = array();

		//Create custom group levels to include into the public group selectList
		$access   = array();
		$access[] = JHTML::_('select.option', -2, JText::_( 'COM_JEM_ONLYADMINS' ) );
		$access[] = JHTML::_('select.option', 0 , JText::_( 'COM_JEM_EVERYBODY' ) );
		$access[] = JHTML::_('select.option', -1, JText::_( 'COM_JEM_ALLREGISTERED' ) );
		//$pub_groups = array_merge( $pub_groups, $acl->get_group_children_tree( null, 'Registered', true ) );
		//$access = array_merge( $access, $acl->get_group_children_tree( null, 'USERS', false ) );

		// Create the access control list
		// $accessLists['evdel_access']	= JHTML::_('select.genericlist', $access, 'delivereventsyes', 'class="inputbox" size="4"', 'value', 'text', $jemsettings->delivereventsyes );
		// $accessLists['locdel_access']	= JHTML::_('select.genericlist', $access, 'deliverlocsyes', 'class="inputbox" size="4"', 'value', 'text', $jemsettings->deliverlocsyes );
		// $accessLists['evpub_access']	= JHTML::_('select.genericlist', $access, 'autopubl', 'class="inputbox" size="4"', 'value', 'text', $jemsettings->autopubl );
		// $accessLists['locpub_access']	= JHTML::_('select.genericlist', $access, 'autopublocate', 'class="inputbox" size="4"', 'value', 'text', $jemsettings->autopublocate );
		// $accessLists['ev_edit']			= JHTML::_('select.genericlist', $access, 'eventedit', 'class="inputbox" size="4"', 'value', 'text', $jemsettings->eventedit );
		// $accessLists['venue_edit']		= JHTML::_('select.genericlist', $access, 'venueedit', 'class="inputbox" size="4"', 'value', 'text', $jemsettings->venueedit );

		//$uri = JFactory::getURI();
		//$uri2 = $uri->toString();

		//assign data to template
		$this->accessLists	= $accessLists;
		//$this->jemsettings	= $jemsettings;
		//$this->request_url	= $uri2;
		$this->countries	= JEMHelper::getCountryOptions();

		//$this->WarningIcon();


		$this->assignRef('form',	$form);
		$this->assignRef('data',	$data);
		$this->assignRef('jemsettings',	$jemsettings);

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
	}



	/**
	 * Add the page title and toolbar.
	 *
	 * @since	1.6
	 */
	protected function addToolbar()
	{

		//Create Submenu
		require_once JPATH_COMPONENT . '/helpers/helper.php';

		JToolBarHelper::title(JText::_('COM_JEM_SETTINGS'), 'settings');
		JToolBarHelper::apply('settings.apply');
		JToolBarHelper::save('settings.save');
		JToolBarHelper::divider();
		JToolBarHelper::cancel('settings.cancel');
		JToolBarHelper::divider();
		JToolBarHelper::help( 'settings', true );
	}





	function WarningIcon()
	{
		$app = JFactory::getApplication();

		$url = JURI::root();
		$tip = '<img src="'.$url.'media/system/images/tooltip.png" border="0"  alt="" />';

		return $tip;
	}
}
