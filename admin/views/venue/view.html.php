<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


/**
 * View class Venue
 *
 * @package Joomla
 * @subpackage JEM
 *
 */
class JEMViewVenue extends JViewLegacy {

	protected $form;
	protected $item;
	protected $state;

	public function display($tpl = null)
	{
		// Initialise variables.
		$this->form	 = $this->get('Form');
		$this->item	 = $this->get('Item');
		$this->state = $this->get('State');

		// Check for errors.
		if (count($errors = $this->get('Errors'))) {
			JError::raiseError(500, implode("\n", $errors));
			return false;
		}

		JHtml::_('behavior.framework');
		JHtml::_('behavior.modal', 'a.modal');
		JHtml::_('behavior.tooltip');
		JHtml::_('behavior.formvalidation');

		//initialise variables
		$document	= JFactory::getDocument();
		$this->settings	= JEMAdmin::config();
		$task		= JRequest::getVar('task');
		$this->task 		= $task;

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);
		JHtml::_('stylesheet', 'com_jem/geostyle.css', array(), true);

		// Load Scripts
		$document->addScript(JURI::root().'media/com_jem/js/attachments.js');
		$document->addScript('http://maps.googleapis.com/maps/api/js?sensor=false&amp;libraries=places');

		// Noconflict
		$document->addCustomTag('<script type="text/javascript">jQuery.noConflict();</script>');

		// JQuery scripts
		$document->addScript('http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js');
		$document->addScript(JURI::root().'media/com_jem/js/jquery.geocomplete.js');

		$access2 = JEMHelper::getAccesslevelOptions();
		$this->access		= $access2;

		$this->addToolbar();
		parent::display($tpl);
	}


	/**
	 * Add the page title and toolbar.
	 *
	 */
	protected function addToolbar()
	{
		JRequest::setVar('hidemainmenu', true);

		$user		= JFactory::getUser();
		$isNew		= ($this->item->id == 0);
		$checkedOut	= !($this->item->checked_out == 0 || $this->item->checked_out == $user->get('id'));
		$canDo		= JEMHelperBackend::getActions();

		JToolBarHelper::title($isNew ? JText::_('COM_JEM_ADD_VENUE') : JText::_('COM_JEM_EDIT_VENUE'), 'venuesedit');

		// If not checked out, can save the item.
		if (!$checkedOut && ($canDo->get('core.edit')||$canDo->get('core.create'))) {
			JToolBarHelper::apply('venue.apply');
			JToolBarHelper::save('venue.save');
		}
		if (!$checkedOut && $canDo->get('core.create')) {
			JToolBarHelper::save2new('venue.save2new');
		}
		// If an existing item, can save to a copy.
		if (!$isNew && $canDo->get('core.create')) {
			JToolBarHelper::save2copy('venue.save2copy');
		}

		if (empty($this->item->id))  {
			JToolBarHelper::cancel('venue.cancel');
		} else {
			JToolBarHelper::cancel('venue.cancel', 'JTOOLBAR_CLOSE');
		}

		JToolBarHelper::divider();
		JToolBarHelper::help('editvenues', true);
	}
}
?>