<?php
/**
 * @version 2.1.7
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


/**
 * View class: Attendee
 */
class JemViewAttendee extends JViewLegacy {

	public function display($tpl = null)
	{
		//initialise variables
		$document = JFactory::getDocument();
		$jinput   = JFactory::getApplication()->input;

		$this->jemsettings = JemHelper::config();

		// Load the form validation behavior
		JHtml::_('behavior.formvalidation');

		//get vars
		$event_id = $jinput->getInt('event', 0);

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		//Get data from the model
		$row = $this->get('Data');

		//build selectlists
		$lists = array();
		// TODO: On J! 2.5 we need last param 0 because it defaults to 1 activating a useless feature.
		//       On J! 3.x this param and the useless feature has been removed so we should remove last param.
		//       Such changes are of sort "grrr".
		$lists['users'] = JHtml::_('list.users', 'uid', $row->uid, false, NULL, 'name', 0);

		//assign data to template
		$this->lists 	= $lists;
		$this->row		= $row;
		$this->event 	= $event_id;

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
	}


	/**
	 * Add Toolbar
	 */
	protected function addToolbar()
	{
		JFactory::getApplication()->input->set('hidemainmenu', true);

		//get vars
		$cid        = JFactory::getApplication()->input->get('cid', array(), 'array');
		$user       = JemFactory::getUser();
		$checkedOut = false; // don't know, table hasn't such a field
		$canDo      = JemHelperBackend::getActions();

		if (empty($cid[0])) {
			JToolBarHelper::title(JText::_('COM_JEM_ADD_ATTENDEE'), 'users');
		} else {
			JToolBarHelper::title(JText::_('COM_JEM_EDIT_ATTENDEE'), 'users');
		}

		// If not checked out, can save the item.
		if (!$checkedOut && ($canDo->get('core.edit')||$canDo->get('core.create'))) {
			JToolBarHelper::apply('attendee.apply');
			JToolBarHelper::save('attendee.save');
		}

		if (!$checkedOut && $canDo->get('core.create')) {
			JToolBarHelper::save2new('attendee.save2new');
		}

		// If an existing item, can save to a copy.
		if (!empty($cid[0]) && $canDo->get('core.create')) {
			JToolBarHelper::save2copy('attendee.save2copy');
		}

		if (empty($cid[0])) {
			JToolBarHelper::cancel('attendee.cancel');
		} else {
			JToolBarHelper::cancel('attendee.cancel', 'JTOOLBAR_CLOSE');
		}

		JToolBarHelper::divider();
		JToolBarHelper::help('editattendee', true);
	}
}
