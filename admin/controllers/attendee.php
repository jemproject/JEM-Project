<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * JEM Component Attendee Controller
 *
 * @package JEM
 *
 */
class JEMControllerAttendee extends JControllerLegacy
{
	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();

		// Register Extra task
		$this->registerTask('add', 		'edit');
		$this->registerTask('apply', 		'save');
	}


	/**
	 * redirect to events page
	 */
	function back()
	{
		$this->setRedirect('index.php?option=com_jem&view=events');
	}


	/**
	 * logic for cancel an action
	 *
	 * @access public
	 * @return void
	 *
	 */
	function cancel()
	{
		// Check for request forgeries.
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		$venue = JTable::getInstance('jem_register', '');
		$venue->bind(JRequest::get('post'));
		$venue->checkin();

		$this->setRedirect('index.php?option=com_jem&view=attendees&id='.JRequest::getInt('event'));
	}


	/**
	 * saves the attendee in the database
	 *
	 * @access public
	 * @return void
	 *
	 */
	function save()
	{
		// Check for request forgeries.
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		// Defining JInput
		$jinput = JFactory::getApplication()->input;

		// retrieving task "apply"
		$task = $jinput->get('task','','cmd');
// 		$task	= $this->getTask();

		// Retrieving $post
		$post = $jinput->getArray($_POST);

		// Retrieving email-setting
		$sendemail = $jinput->get('sendemail','0','int');

		// Retrieving event-id
		$eventid = $jinput->get('event','','int');

		$model = $this->getModel('attendee');

		if ($row = $model->store($post)) {
			if ($sendemail == 1) {
				JPluginHelper::importPlugin('jem');
				$dispatcher = JDispatcher::getInstance();
				$dispatcher->trigger('onEventUserRegistered', array($row->id));
			}

			switch ($task)
			{
				case 'apply':
					$link = 'index.php?option=com_jem&view=attendee&hidemainmenu=1&cid[]='.$row->id.'&event='.$row->event;
					break;

				default:
					$link = 'index.php?option=com_jem&view=attendees&id='.$row->event;
					break;
			}
			$msg = JText::_('COM_JEM_ATTENDEE_SAVED');

			$cache = JFactory::getCache('com_jem');
			$cache->clean();
		} else {
			$msg 	= '';
			$link 	= 'index.php?option=com_jem&view=attendees&id='.$eventid;
		}
		$this->setRedirect($link, $msg);
	}

	function selectUser()
	{
		$jinput = JFactory::getApplication()->input;
		$jinput->set('view', 'userelement');
		parent::display();
	}
}
?>