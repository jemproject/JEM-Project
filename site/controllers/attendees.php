<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;

/**
 * JEM Component Attendees Controller
 *
 * @package JEM
 *
 */
class JemControllerAttendees extends BaseController
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * redirect to events page
	 */
	public function back()
	{
		$this->setRedirect(Route::_(JemHelperRoute::getMyEventsRoute(), false));
		$this->redirect();
	}

	/**
	 * addtask
	 */
	public function attendeeadd()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit('Invalid Token');

		$jinput  = Factory::getApplication()->input;
		$eventid = $jinput->getInt('id', 0);
		$status  = $jinput->getInt('status', 0);		
		$comment = '';
		$fid     = $jinput->getInt('Itemid', 0);
		$uids    = explode(',', $jinput->getString('uids', ''));
		\Joomla\Utilities\ArrayHelper::toInteger($uids);
		$uids    = array_filter($uids);
		$uids    = array_unique($uids);
		$total   = is_array($uids) ? count($uids) : 0;
		$msg     = '';
		
		if ($jinput->get('task', 0,'string')=="attendeeadd") {
			$places = $jinput->input->getInt('places', 0);
		} else {
			if ($status == 1)
			{
				$places = $jinput->input->getInt('addplaces', 0);
			}
			else
			{
				$places = $jinput->input->getInt('cancelplaces', 0);
			}
		}

		JemHelper::addLogEntry("Got attendee add - event: {$eventid}, status: {$status}, users: " . implode(',', $uids), __METHOD__, JLog::DEBUG);

		if ($total < 1) {
			$msg = '0 ' . Text::_('COM_JEM_REGISTERED_USERS_ADDED');
		} else {
			PluginHelper::importPlugin('jem');
			$dispatcher = JemFactory::getDispatcher();

			// We have to check all users first if there are already records for given event.
			// If not we have to add the records and than on success send the emails.
			$modelEventItem = $this->getModel('event');
			$modelAttendees = $this->getModel('attendees'); // required to ensure JemModelAttendees is loaded
			$regs = JemModelAttendees::getRegisteredUsers($eventid);
			$errMsgs = array();
			$errMsg  = '';
			$skip    = 0;
			$error   = 0;
			$changed = 0;

			foreach ($uids as $uid) {
				if (array_key_exists($uid, $regs)) {
					$reg = $regs[$uid];
					$old_status = ($reg->status == 1 && $reg->waiting == 1) ? 2 : $reg->status;
					if (!empty($reg->id) && ($old_status != $status)) {
						JemHelper::addLogEntry("Change user {$uid} already registered for event {$eventid}.", __METHOD__, JLog::DEBUG);
						$reg_id = $modelEventItem->adduser($eventid, $uid, $status, $places, $comment, $errMsg, $reg->id);
						if ($reg_id) {
							$res = $dispatcher->triggerEvent('onEventUserRegistered', array($reg_id));
							++$changed;
						} else {
							JemHelper::addLogEntry(implode(' - ', array("Model returned error while changing registration of user {$uid}", $errMsg)), __METHOD__, JLog::DEBUG);
							if (!empty($errMsg)) {
								$errMsgs[] = $errMsg;
							}
							++$error;
						}
					} else {
						JemHelper::addLogEntry("Skip user {$uid} already registered for event {$eventid}.", __METHOD__, JLog::DEBUG);
						++$skip;
					}
				} else {
					$reg_id = $modelEventItem->adduser($eventid, $uid, $status, $places, $comment, $errMsg);
					if ($reg_id) {
						$res = $dispatcher->triggerEvent('onEventUserRegistered', array($reg_id));
					} else {
						JemHelper::addLogEntry(implode(' - ', array("Model returned error while adding user {$uid}", $errMsg)), __METHOD__, JLog::DEBUG);
						if (!empty($errMsg)) {
							$errMsgs[] = $errMsg;
						}
						++$error;
					}
				}
			}

			$cache = Factory::getCache('com_jem');
			$cache->clean();

			$msg = ($total - $skip - $error - $changed) . ' ' . Text::_('COM_JEM_REGISTERED_USERS_ADDED');
			if ($changed > 0) {
				$msg .= ', ' . $changed . ' ' . Text::_('COM_JEM_REGISTERED_USERS_CHANGED');
			}
			$errMsgs = array_unique($errMsgs);
			if (count($errMsgs)) {
				$msg .= '<br />' . implode('<br />', $errMsgs);
			}
		}

		$this->setRedirect(Route::_('index.php?option=com_jem&view=attendees&id='.$eventid.'&Itemid='.$fid, false), $msg);
	}

	/**
	 * removetask
	 */
	public function attendeeremove()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit('Invalid Token');

		$jinput = Factory::getApplication()->input;
		$cid    = $jinput->get('cid', array(), 'array');
		$id     = $jinput->getInt('id', 0);
		$fid    = $jinput->getInt('Itemid', 0);
		$total  = is_array($cid) ? count($cid) : 0;

		if ($total < 1) {
			throw new Exception(Text::_('COM_JEM_SELECT_ITEM_TO_DELETE'), 500);
		}

		$modelAttendeeList = $this->getModel('attendees');

		PluginHelper::importPlugin('jem');
		$dispatcher = JemFactory::getDispatcher();

		$modelAttendeeItem = $this->getModel('attendee');

		// We need information about every entry to delete for mailer.
		// But we should first delete the entry and than on success send the mails.
		foreach ($cid as $reg_id) {
			$modelAttendeeItem->setId($reg_id);
			$entry = $modelAttendeeItem->getData();
			if($modelAttendeeList->remove(array($reg_id))) {
				$res = $dispatcher->triggerEvent('onEventUserUnregistered', array($entry->event, $entry));
			} else {
				$error = true;
			}
		}
		if (!empty($error)) {
			echo "<script> alert('".$modelAttendeeList->getError()."'); window.history.go(-1); </script>\n";
		}

		$cache = Factory::getCache('com_jem');
		$cache->clean();

		$msg = $total.' '.Text::_('COM_JEM_REGISTERED_USERS_DELETED');

		$this->setRedirect(Route::_('index.php?option=com_jem&view=attendees&id='.$id.'&Itemid='.$fid, false), $msg);
	}

	///@todo Add function to change registration status.

	/**
	 * toggletask
	 */
	public function attendeetoggle()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit('Invalid Token');

		$jinput = Factory::getApplication()->input;
		$id     = $jinput->getInt('id', 0);
		$fid    = $jinput->getInt('Itemid', 0);

		$model = $this->getModel('attendee');
		$model->setId($id);

		$attendee = $model->getData();
		$res = $model->toggle();

		$type = 'message';

		if ($res)
		{
			PluginHelper::importPlugin('jem');
			$dispatcher = JemFactory::getDispatcher();
			$res = $dispatcher->triggerEvent('onUserOnOffWaitinglist', array($id));

			if ($attendee->waiting) {
				$msg = Text::_('COM_JEM_ADDED_TO_ATTENDING');
			} else {
				$msg = Text::_('COM_JEM_ADDED_TO_WAITING');
			}
		}
		else
		{
			$msg = Text::_('COM_JEM_WAITINGLIST_TOGGLE_ERROR').': '.$model->getError();
			$type = 'error';
		}

		$this->setRedirect(Route::_('index.php?option=com_jem&view=attendees&id='.$attendee->event.'&Itemid='.$fid, false), $msg, $type);
		$this->redirect();
	}

	/**
	 * Exporttask
	 * view: attendees
	 */
	public function export()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit('Invalid Token');

		$app       = Factory::getApplication();
		$params    = $app->getParams();
		$jemconfig = JemConfig::getInstance()->toRegistry();

		$enableemailadress = $params->get('enableemailaddress', 0);
		$separator         = $jemconfig->get('csv_separator', ';');
		$delimiter         = $jemconfig->get('csv_delimiter', '"');
		$csv_bom           = $jemconfig->get('csv_bom', '1');
		$userfield         = $jemconfig->get('globalattribs.global_regname', 1) ? 'name' : 'username';
		$comments          = $jemconfig->get('regallowcomments', 0);

		$model = $this->getModel('attendees');
		$datas = $model->getData();
		$event = $model->getEvent();
		$waitinglist = isset($event->waitinglist) ? $event->waitinglist : false;

		header('Content-Type: text/csv; charset=utf-8');
		header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Content-Disposition: attachment; filename=attendees_event_' . $event->id . '.csv');
		header('Pragma: no-cache');

		$export = fopen('php://output', 'w');
		ob_end_clean();
		if ($csv_bom ==1 ) {
			//add BOM to fix UTF-8 in Excel
			fputs($export, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
		}

		$cols = array();
		$cols[] = Text::_('COM_JEM_NUM');
		$cols[] = Text::_($jemconfig->get('globalattribs.global_regname', 1) ? 'COM_JEM_NAME' : 'COM_JEM_USERNAME');
		if ($enableemailadress == 1) {
			$cols[] = Text::_('COM_JEM_EMAIL');
		}
		$cols[] = Text::_('COM_JEM_REGDATE');
		$cols[] = Text::_('COM_JEM_STATUS');
		$cols[] = Text::_('COM_JEM_PLACES');
		if ($comments) {
			$cols[] = Text::_('COM_JEM_COMMENT');
		}

		fputcsv($export, $cols, $separator, $delimiter);

		$i = 0;
		foreach ($datas as $data)
		{
			$cols = array();

			$cols[] = ++$i;
			$cols[] = $data->$userfield;
			if ($enableemailadress == 1) {
				$cols[] = $data->email;
			}
			$cols[] = empty($data->uregdate) ? '' : HTMLHelper::_('date',$data->uregdate, Text::_('DATE_FORMAT_LC5'));

			$status = isset($data->status) ? $data->status : 1;
			if ($status < 0) {
				$txt_stat = 'COM_JEM_ATTENDEES_NOT_ATTENDING';
			} elseif ($status > 0) {
				$txt_stat = $data->waiting ? 'COM_JEM_ATTENDEES_ON_WAITINGLIST' : 'COM_JEM_ATTENDEES_ATTENDING';
			} else {
				$txt_stat = 'COM_JEM_ATTENDEES_INVITED';
			}
			$cols[] = Text::_($txt_stat);
			$cols[] = $data->places;
			if ($comments) {
				$comment = strip_tags($data->comment);
				// comments are limited to 255 characters in db so we don't need to truncate them on export
				$cols[] = $comment;
			}

			fputcsv($export, $cols, $separator, $delimiter);
		}

		fclose($export);
		$app->close();
	}
}
?>
