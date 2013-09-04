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

		$jemsettings = $this->get('Data');
		$document 	= JFactory::getDocument();
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		$style = '
		    div.current fieldset.radio input {
		        cursor: pointer;
		    }';

		$document->addStyleDeclaration($style);

		/* Bootstrap Css
		 * - Toolbar icons will be moved due to the background-position (icons)
		 */

		// $document->addStyleSheet(JURI::root().'media/com_jem/bootstrap/css/bootstrap.css'); */

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

		//only admins have access to this view
		if (!JFactory::getUser()->authorise('core.manage')) {
			JError::raiseWarning('SOME_ERROR_CODE', JText::_('COM_JEM_ALERTNOTAUTH'));
			$app->redirect('index.php?option=com_jem&view=jem');
		}

		//add css, js and submenu to document
		//$document->addScript(JURI::root().'media/com_jem/js/settings.js');

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
		JToolBarHelper::help('settings', true);
	}


	function WarningIcon()
	{
		$url = JURI::root();
		$tip = '<img src="'.$url.'media/system/images/tooltip.png" border="0"  alt="" />';

		return $tip;
	}
}
