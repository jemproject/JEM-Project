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
 * View class for the JEM attendee screen
 *
 * @package JEM
 *
 */
class JEMViewAttendee extends JViewLegacy {

	public function display($tpl = null)
	{
		//initialise variables
		$document	= JFactory::getDocument();

		// Load the form validation behavior
		JHtml::_('behavior.formvalidation');

		//get vars
		$event_id = JRequest::getInt('id');

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		//Get data from the model
		$row		= $this->get('Data');

		//build selectlists
		$lists = array();
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
		//get vars
		$cid = JRequest::getVar('cid');

		if ($cid) {
			JToolBarHelper::title(JText::_('COM_JEM_EDIT_ATTENDEE'), 'users');
		} else {
			JToolBarHelper::title(JText::_('COM_JEM_ADD_ATTENDEE'), 'users');
		}

		JToolBarHelper::apply('attendee.apply');
		JToolBarHelper::save('attendee.save');

		if (!$cid) {
			JToolBarHelper::cancel('attendee.cancel');
		} else {
			JToolBarHelper::cancel('attendee.cancel', 'JTOOLBAR_CLOSE');
		}

		JToolBarHelper::divider();
		JToolBarHelper::help('editattendee', true);
	}
}
?>