<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Language\Text;

/**
 * Controller: Attendees
 */
class JemControllerAttendees extends BaseController
{
	/**
	 * Constructor
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		// Register Extra task
		$this->registerTask('add',   'edit');
		$this->registerTask('apply', 'save');

		$this->registerTask('onWaitinglist',  'toggleStatus');
		$this->registerTask('offWaitinglist', 'toggleStatus');

		$this->registerTask('setNotAttending','setStatus');
		$this->registerTask('setAttending',   'setStatus');
		$this->registerTask('setWaitinglist', 'setStatus');
	}

	/**
	 * Delete attendees
	 *
	 * @return true on sucess
	 * @access private
	 */
	public function remove()
	{
		// Check for request forgeries.
		JSession::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		$jinput = Factory::getApplication()->input;
		$cid = $jinput->get('cid',  0, 'array');
		$eventid = $jinput->getInt('eventid');

		if (!is_array($cid) || count($cid) < 1) {
			throw new Exception(Text::_('COM_JEM_SELECT_ITEM_TO_DELETE'), 500);
		}

		$total = count($cid);

		JPluginHelper::importPlugin('jem');
		$dispatcher = JemFactory::getDispatcher();

		$modelAttendeeList = $this->getModel('attendees');
		$modelAttendeeItem = $this->getModel('attendee');

		// We need information about every entry to delete for mailer.
		// But we should first delete the entry and than on success send the mails.
		foreach ($cid as $reg_id) {
			$modelAttendeeItem->setId($reg_id);
			$entry = $modelAttendeeItem->getData();
			if ($modelAttendeeList->remove(array($reg_id))) {
				$dispatcher->triggerEvent('onEventUserUnregistered', array($entry->event, $entry));
			} else {
				$error = true;
			}
		}
		if (!empty($error)) {
			echo "<script> alert('" . $modelAttendeeList->getError() . "'); window.history.go(-1); </script>\n";
		}

		$cache = Factory::getCache('com_jem');
		$cache->clean();

		$msg = $total . ' ' . Text::_('COM_JEM_REGISTERED_USERS_DELETED');

		$this->setRedirect('index.php?option=com_jem&view=attendees&eventid=' . $eventid, $msg);
	}

	/**
	 * Function to export
	 */
	public function export()
	{
		// Check for request forgeries
		JSession::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

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
	public function back()
	{
		$this->setRedirect('index.php?option=com_jem&view=events');
	}

	/**
	 * Function to change status
	 */
	public function toggleStatus()
	{
		// Check for request forgeries
		JSession::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		$app  = Factory::getApplication();
		$pks  = $app->input->get('cid', array(), 'array');
		$task = $this->getTask();

		if (empty($pks)) {
			Factory::getApplication()->enqueueMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'warning');
		} else {
			\Joomla\Utilities\ArrayHelper::toInteger($pks);
			$model = $this->getModel('attendee');

			JPluginHelper::importPlugin('jem');
			$dispatcher = JemFactory::getDispatcher();

			foreach ($pks AS $pk) {
				$model->setId($pk);
				$attendee = $model->getData();
				$res = $model->toggle();

				if ($res) {
					$dispatcher->triggerEvent('onUserOnOffWaitinglist', array($pk));

					if ($attendee->waiting) {
						$msg = Text::_('COM_JEM_ADDED_TO_ATTENDING');
					} else {
						$msg = Text::_('COM_JEM_ADDED_TO_WAITING');
					}
					$type = 'message';
				} else {
					$msg = Text::_('COM_JEM_WAITINGLIST_TOGGLE_ERROR') . ': ' . $model->getError();
					$type = 'error';
				}

				if ($task !== 'toggleStatus') {
					$app->enqueueMessage($msg, $type);
				}
			}
		}

		if ($task === 'toggleStatus') {
			# here we are selecting more rows so a general message would be better
			$msg = Text::_('COM_JEM_ATTENDEES_CHANGEDSTATUS');
			$type = "message";
			$app->enqueueMessage($msg, $type);
		}

		$this->setRedirect('index.php?option=com_jem&view=attendees&eventid=' . $attendee->event);
		$this->redirect();
	}

	/**
	 * logic to create the edit attendee view
	 *
	 * @access public
	 * @return void
	 *
	 */
	public function edit()
	{
		// Check for request forgeries.
		JSession::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		$jinput = Factory::getApplication()->input;
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
		JSession::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		$app = Factory::getApplication();
        $user = $app->getIdentity();

		$eventid = $app->input->getInt('eventid');
		$ids     = $app->input->get('cid', array(), 'array');
		$values  = array('setWaitinglist' => 2, 'setAttending' => 1, 'setInvited' => 0, 'setNotAttending' => -1);
		$task    = $this->getTask();
		$value   = \Joomla\Utilities\ArrayHelper::getValue($values, $task, 0, 'int');

		if (empty($ids))
		{
			$message = Text::_('JERROR_NO_ITEMS_SELECTED');
			Factory::getApplication()->enqueueMessage($message, 'warning');
		}
		else
		{
			// Get the model.
			$model = $this->getModel('attendee');

			// Publish the items.
			if (!$model->setStatus($ids, $value))
			{
				$message = $model->getError();
				JemHelper::addLogEntry($message, __METHOD__, JLog::ERROR);
				Factory::getApplication()->enqueueMessage($message, 'warning');
			}
			else
			{
				JPluginHelper::importPlugin('jem');
				$dispatcher = JemFactory::getDispatcher();

				switch ($value) {
				case -1:
					$message = Text::plural('COM_JEM_ATTENDEES_N_ITEMS_NOTATTENDING', count($ids));
					foreach ($ids AS $pk) {
						// onEventUserUnregistered($eventid, $record, $recordid)
						$dispatcher->triggerEvent('onEventUserUnregistered', array($eventid, false, $pk));
					}
					break;
				case 0:
					$message = Text::plural('COM_JEM_ATTENDEES_N_ITEMS_INVITED', count($ids));
					foreach ($ids AS $pk) {
						// onEventUserRegistered($recordid)
						$dispatcher->triggerEvent('onEventUserRegistered', array($pk));
					}
					break;
				case 1:
					$message = Text::plural('COM_JEM_ATTENDEES_N_ITEMS_ATTENDING', count($ids));
					foreach ($ids AS $pk) {
						// onEventUserRegistered($recordid)
						$dispatcher->triggerEvent('onEventUserRegistered', array($pk));
					}
					break;
				case 2:
					$message = Text::plural('COM_JEM_ATTENDEES_N_ITEMS_WAITINGLIST', count($ids));
					foreach ($ids AS $pk) {
						// onUserOnOffWaitinglist($recordid)
						$dispatcher->triggerEvent('onUserOnOffWaitinglist', array($pk));
					}
					break;
				}

				JemHelper::addLogEntry($message, __METHOD__, JLog::DEBUG);
			}
		}

		$this->setRedirect(JRoute::_('index.php?option=com_jem&view=attendees&eventid=' . $eventid, false), $message);
	}
}
