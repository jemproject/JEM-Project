<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * Controller: Attendee
 */
class JemControllerAttendee extends JControllerLegacy
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		// Register Extra task
		$this->registerTask('add',       'edit');
		$this->registerTask('apply',     'save');
		$this->registerTask('save2new',  'save');
		$this->registerTask('save2copy', 'save');
	}

	/**
	 * redirect to events page
	 */
	public function back()
	{
		$this->setRedirect('index.php?option=com_jem&view=attendees&eventid='.JFactory::getApplication()->input->getInt('event', 0));
	}

	/**
	 * logic for cancel an action
	 *
	 * @access public
	 * @return void
	 */
	public function cancel()
	{
		// Check for request forgeries.
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		$attendee = JTable::getInstance('jem_register', '');
		if (version_compare(JVERSION, '3.2', 'lt')) {
			$attendee->bind(JRequest::get('post')); // before Joomla! 3.2.0 there is no good way to get them all from JInput :(
		} else {
			$attendee->bind(JFactory::getApplication()->input->post->getArray(/*get them all*/));
		}
		$attendee->checkin();

		$this->setRedirect('index.php?option=com_jem&view=attendees&eventid='.JFactory::getApplication()->input->getInt('event', 0));
	}

	/**
	 * saves the attendee in the database
	 *
	 * @access public
	 * @return void
	 */
	public function save()
	{
		// Check for request forgeries.
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		// Defining JInput
		$jinput = JFactory::getApplication()->input;

		// retrieving task "apply"
		$task = $jinput->getCmd('task');

		// Retrieving $post
		if (version_compare(JVERSION, '3.2', 'lt')) {
			$post = JRequest::get('post'); // before Joomla! 3.2.0 we must and can use JRequest
		} else {
			$post = $jinput->post->getArray(/*get them all*/);
		}

		// Retrieving email-setting
		$sendemail = $jinput->getInt('sendemail','0');

		// Retrieving event-id
		$eventid = $jinput->getInt('event');

		// the id in case of edit
		$id = (!empty($post['id']) ? $post['id'] : 0);

		$model = $this->getModel('attendee');

		// Handle task 'save2copy' - reset id to store as new record, then like 'apply'.
		if ($task == 'save2copy') {
			$post['id'] = 0;
			$id = 0;
			$task = 'apply';
		}

		// handle changing the user - must also trigger onEventUserUnregistered
		$uid = (!empty($post['uid']) ? $post['uid'] : 0);
		if ($uid && $id) {
			$model->setId($id);
			$old_data = $model->getData();
		}
		$old_uid    = (!empty($old_data->uid)    ? $old_data->uid    : 0);
		$old_status = (!empty($old_data->status) ? $old_data->status : 0);

		if ($row = $model->store($post)) {
			if ($sendemail == 1) {
				JPluginHelper::importPlugin('jem');
				$dispatcher = JemFactory::getDispatcher();
				// there was a user and it's overwritten by a new user -> send unregister mails
				if ($old_uid && ($old_uid != $uid)) {
					$dispatcher->trigger('onEventUserUnregistered', array($old_data->event, $old_data));
				}
				// there is a new user which wasn't before -> send register mails
				if ($uid && (($old_uid != $uid) || ($row->status != $old_status))) {
					$dispatcher->trigger('onEventUserRegistered', array($row->id));
				}
				// but show warning if mailer is disabled
				if (!JPluginHelper::isEnabled('jem', 'mailer')) {
					JError::raiseNotice(100, JText::_('COM_JEM_GLOBAL_MAILERPLUGIN_DISABLED'));
				}
			}

			switch ($task)
			{
			case 'apply':
				// Redirect back to the edit screen.
				$link = 'index.php?option=com_jem&view=attendee&hidemainmenu=1&cid[]='.$row->id.'&event='.$row->event;
				break;

			case 'save2new':
				// Redirect back to the edit screen for new record.
				$link = 'index.php?option=com_jem&view=attendee&hidemainmenu=1&event='.$row->event;
				break;

			default:
				// Redirect to the list screen.
				$link = 'index.php?option=com_jem&view=attendees&eventid='.$row->event;
				break;
			}
			$msg = JText::_('COM_JEM_ATTENDEE_SAVED');

			$cache = JFactory::getCache('com_jem');
			$cache->clean();
		} else {
			$msg 	= '';
			$link 	= 'index.php?option=com_jem&view=attendees&eventid='.$eventid;
		}
		$this->setRedirect($link, $msg);
	}

	public function selectUser()
	{
		$jinput = JFactory::getApplication()->input;
		$jinput->set('view', 'userelement');
		parent::display();
	}
}
