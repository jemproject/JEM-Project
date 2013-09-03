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
// 		$user 		= JFactory::getUser();
// 		$app 		= JFactory::getApplication();

		// Load the form validation behavior
		JHTML::_('behavior.formvalidation');

		//get vars
		$event_id = JRequest::getInt('id');

		//add css to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		//Get data from the model
// 		$model		= $this->getModel();
		$row		= $this->get('Data');

		// fail if checked out not by 'me'
		if ($row->id) {
//			if ($model->isCheckedOut($user->get('id'))) {
//				JError::raiseWarning('SOME_ERROR_CODE', $row->catname.' '.JText::_('COM_JEM_EDITED_BY_ANOTHER_ADMIN'));
//				$app->redirect('index.php?option=com_jem&view=attendees&id='.$event_id);
//			}
		}

		//build selectlists
		$lists = array();
		$lists['users'] = JHTML::_('list.users', 'uid', $row->uid, false, NULL, 'name', 0);

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

		//create the toolbar
		if ($cid) {
			JToolBarHelper::title(JText::_('COM_JEM_EDIT_ATTENDEE'), 'users');
		} else {
			JToolBarHelper::title(JText::_('COM_JEM_ADD_ATTENDEE'), 'users');
		}
		JToolBarHelper::apply('attendee.apply');
		JToolBarHelper::save('attendee.save');
		JToolBarHelper::spacer();
		JToolBarHelper::cancel('attendee.cancel');
		JToolBarHelper::spacer();
		JToolBarHelper::help('editattendee', true);
	}
}
?>