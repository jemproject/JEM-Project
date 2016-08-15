<?php
/**
 * @version 2.1.7
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * Controller: Attendees
 */
class JemControllerAttendees extends JControllerLegacy
{
	/**
	 * Constructor
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		// Register Extra task
		$this->registerTask('add', 		'edit');
		$this->registerTask('apply', 		'save');

		$this->registerTask('onWaitinglist','toggleStatus');
		$this->registerTask('offWaitinglist','toggleStatus');

		$this->registerTask('setNotAttending','setStatus');
		$this->registerTask('setAttending','setStatus');
		$this->registerTask('setWaitinglist','setStatus');
	}

	/**
	 * Delete attendees
	 *
	 * @return true on sucess
	 * @access private
	 */
	function remove()
	{
		// Check for request forgeries.
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		$jinput = JFactory::getApplication()->input;
		$cid = $jinput->get('cid',  0, 'array');
		$eventid = $jinput->getInt('eventid');

		$total 	= count($cid);

		$modelAttendeeList = $this->getModel('attendees');

		if (!is_array($cid) || count($cid) < 1) {
			JError::raiseError(500, JText::_('COM_JEM_SELECT_ITEM_TO_DELETE'));
		}

		JPluginHelper::importPlugin('jem');
		$dispatcher = JemFactory::getDispatcher();

		$modelAttendeeItem = $this->getModel('attendee');

		// We need information about every entry to delete for mailer.
		// But we should first delete the entry and than on success send the mails.
		foreach ($cid as $reg_id) {
			$modelAttendeeItem->setId($reg_id);
			$entry = $modelAttendeeItem->getData();
			if($modelAttendeeList->remove(array($reg_id))) {
				$res = $dispatcher->trigger('onEventUserUnregistered', array($entry->event, $entry));
			} else {
				$error = true;
			}
		}
		if (!empty($error)) {
			echo "<script> alert('".$modelAttendeeList->getError()."'); window.history.go(-1); </script>\n";
		}

		$cache = JFactory::getCache('com_jem');
		$cache->clean();

		$msg = $total.' '.JText::_('COM_JEM_REGISTERED_USERS_DELETED');

		$this->setRedirect('index.php?option=com_jem&view=attendees&eventid='.$eventid, $msg);
	}


	/**
	 * Function to export
	 */
	public function export()
	{
		header('Content-Type: text/x-csv');
		header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Content-Disposition: attachment; filename=attendees.csv');
		header('Pragma: no-cache');
		$model = $this->getModel('attendees');
		$model->getCsv();
		jexit();
	}

	/**
	 * redirect to events page
	 */
	function back()
	{
		$this->setRedirect('index.php?option=com_jem&view=events');
	}

	/**
	 * Function to change status
	 */
	function toggleStatus()
	{
		// Check for request forgeries
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		$jinput = JFactory::getApplication()->input;
		$pks    = $this->input->get('cid', array(), 'array');
		$task   = $this->getTask();

		if (empty($pks))
		{
			JError::raiseWarning(500, JText::_('JERROR_NO_ITEMS_SELECTED'));
		} else {
			JArrayHelper::toInteger($pks);
			$model = $this->getModel('attendee');
			$app = JFactory::getApplication();

			foreach ($pks AS $pk) {
				$model->setId($pk);

				$attendee = $model->getData();
				$res = $model->toggle();

				if ($res)
				{
					JPluginHelper::importPlugin('jem');
					$dispatcher = JemFactory::getDispatcher();
					$res = $dispatcher->trigger('onUserOnOffWaitinglist', array($pk));

					if ($attendee->waiting)
					{
						$msg = JText::_('COM_JEM_ADDED_TO_ATTENDING');
					}
					else
					{
						$msg = JText::_('COM_JEM_ADDED_TO_WAITING');
					}
					$type = 'message';
				}
				else
				{
					$msg = JText::_('COM_JEM_WAITINGLIST_TOGGLE_ERROR').': '.$model->getError();
					$type = 'error';
				}

				if (!($task = 'toggleStatus')) {
					$app->enqueueMessage($msg,$type);
				}
			}
		}

		if ($task = 'toggleStatus') {
			# here we are selecting more rows so a general message would be better
			$msg = JText::_('COM_JEM_ATTENDEES_CHANGEDSTATUS');
			$type = "message";
			$app->enqueueMessage($msg,$type);
		}

		$this->setRedirect('index.php?option=com_jem&view=attendees&eventid='.$attendee->event);
		$this->redirect();
	}


	/**
	 * logic to create the edit attendee view
	 *
	 * @access public
	 * @return void
	 *
	 */
	function edit()
	{
		// Check for request forgeries.
		// JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		$jinput = JFactory::getApplication()->input;
		$jinput->set('view', 'attendee');
		// 'attendee' expects event id as 'event' not 'id'
		$jinput->set('event', $jinput->getInt('eventid'));
		$jinput->set('id', null);
		$jinput->set('hidemainmenu', '1');

		parent::display();
	}


	/**
	 * Method to change status of selected rows.
	 *
	 * @return  void
	 */
	public function setStatus()
	{
		// Check for request forgeries
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		$user   = JFactory::getUser();
		$ids    = $this->input->get('cid', array(), 'array');
		$values = array('setWaitinglist' => 2, 'setAttending' => 1, 'setInvited' => 0, 'setNotAttending' => -1);
		$task   = $this->getTask();
		$value  = JArrayHelper::getValue($values, $task, 0, 'int');

		if (empty($ids))
		{
			JError::raiseWarning(500, JText::_('JERROR_NO_ITEMS_SELECTED'));
		}
		else
		{
			// Get the model.
			$model = $this->getModel('attendee');

			// Publish the items.
			if (!$model->setStatus($ids, $value))
			{
				JemHelper::addLogEntry($model->getError(), __METHOD__, JLog::ERROR);
				JError::raiseWarning(500, $model->getError());
			}

			switch ($value) {
			case -1:
				$message = JText::plural('COM_JEM_ATTENDEES_N_ITEMS_NOTATTENDING', count($ids));
				break;
			case 0:
				$message = JText::plural('COM_JEM_ATTENDEES_N_ITEMS_INVITED', count($ids));
				break;
			case 1:
				$message = JText::plural('COM_JEM_ATTENDEES_N_ITEMS_ATTENDING', count($ids));
				break;
			case 2:
				$message = JText::plural('COM_JEM_ATTENDEES_N_ITEMS_WAITINGLIST', count($ids));
				break;
			}

			JemHelper::addLogEntry($message, __METHOD__, JLog::DEBUG);
		}

		$app = JFactory::getApplication();
		$jinput = $app->input;
		$eventid = $jinput->getInt('eventid');
		$this->setRedirect(JRoute::_('index.php?option=com_jem&view=attendees&eventid='.$eventid, false), $message);
	}
}
