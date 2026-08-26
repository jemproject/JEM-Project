<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
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
use Joomla\CMS\Log\Log;

use Joomla\Utilities\ArrayHelper;

require_once JPATH_SITE . '/components/com_jem/classes/csv.class.php';
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
    public function __construct() {
        parent::__construct();
    }

    /**
     * redirect to events page
     */
    public function back() {
        $this->setRedirect(Route::_(JemHelperRoute::getMyEventsRoute(), false));
        $this->redirect();
    }

    /**
     * Ensure the current user can manage attendees for the event.
     *
     * @param  int  $eventId
     * @return object
     *
     * @throws Exception
     */
    protected function assertCanManageAttendees($eventId) {
        $eventId = (int) $eventId;

        if ($eventId < 1) {
            throw new Exception(Text::_('COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND'), 404);
        }

        $model = $this->getModel('attendees');
        $model->setId($eventId);
        $event = $model->getEvent();

        if (!$event) {
            throw new Exception(Text::_('COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND'), 404);
        }

        $user = JemFactory::getUser();

        if (!$model->canManageAttendees($user)) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return $event;
    }

    /**
     * addtask
     */
    public function attendeeadd() {
        JemHelper::requirePostToken();

        $app     = Factory::getApplication();
        $input  = $app->getInput();
        $eventid = $input->post->getInt('id', 0);
        $status  = $input->post->getInt('status', 0);
        $checkseries  = $input->post->getString('series', '');
        $comment = '';
        $fid     = $input->post->getInt('Itemid', 0);

        $this->assertCanManageAttendees($eventid);

        if (!JemRegistrationTransition::isValidStatus($status)) {
            throw new Exception(Text::_('COM_JEM_ATTENDEES_STATUS_UNKNOWN'), 400);
        }

        $uids    = explode(',', $input->post->getString('uids', ''));
        ArrayHelper::toInteger($uids);
        $uids    = array_filter($uids);
        $uids    = array_unique($uids);
        $total   = is_array($uids) ? count($uids) : 0;
        $msg     = '';
        $placesByUser = array();

        try {
            $task = $input->post->getCmd('task', '');
            if (in_array($task, array('attendeeadd', 'attendees.attendeeadd'), true)) {
                $selection = JemRegistrationQuantity::parseManagerSelection(
                    $input->post->get('places', '0', 'raw'),
                    $uids
                );
                $places = $selection->places;
                $placesByUser = $selection->byUser;
            } else {
                $field = $status === JemRegistrationTransition::ATTENDING ? 'addplaces' : 'cancelplaces';
                $places = JemRegistrationQuantity::parseOptional($input->post->get($field, null, 'raw'));
            }
        } catch (InvalidArgumentException $e) {
            throw new Exception(Text::_('COM_JEM_ERROR_REGISTRATION'), 400);
        }

        if ($checkseries == "on") {
            $checkseries = 1;
        } else {
            $checkseries = 0;
        }

        JemHelper::addLogEntry("Got attendee add - event: {$eventid}, status: {$status}, users: " . implode(',', $uids), __METHOD__, Log::DEBUG);

        if ($total < 1) {
            $msg = '0 ' . Text::_('COM_JEM_REGISTERED_USERS_ADDED');
        } else {
            PluginHelper::importPlugin('jem');
            PluginHelper::importPlugin('actionlog', 'jem');
            $dispatcher = JemFactory::getDispatcher();

            // We have to check all users first if there are already records for given event.
            // If not we have to add the records and than on success send the emails.
            $modelEventItem = $this->getModel('event');
            $modelAttendees = $this->getModel('attendees'); // required to ensure JemModelAttendees is loaded
            $modelAttendeeItem = $this->getModel('attendee');
            $errMsgs = array();
            $errMsg  = '';
            $skip    = 0;
            $error   = 0;
            $changed = 0;

            // Get event
            try {
                $event = $modelEventItem->getItem($eventid);
            } catch (Exception $e) {
                $event = false;
            }

            if (!$event) {
                throw new Exception(Text::_('COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND'), 404);
            }

            // If event has 'seriesbooking' active and $series is true then get all recurrence events of series from now (register or unregister)
            if ($event->recurrence_type || !empty($event->series_id)) {
                if (($event->seriesbooking && $checkseries)) {
                    $events = $modelEventItem->getListRecurrenceEventsbyId($eventid, $event->recurrence_first_id, time());
                }
            }

            if (!isset($events) || !count ($events)) {
                $events [] = clone $event;
            }

            foreach ($events as $key => $row) {

                $this->assertCanManageAttendees($row->id);
                $modelAttendees->setId((int) $row->id);
                $regs = $modelAttendees->getRegisteredUsers();
                $skip = $error = $changed = 0;
                $transitions = array();
                $releasedCapacityForEvent = false;
                $excludedPromotionIds = array();

                foreach ($uids as $uid) {
                    $userPlaces = isset($placesByUser[$uid]) ? $placesByUser[$uid] : $places;

                    if (array_key_exists($uid, $regs)) {
                        $reg = $regs[$uid];
                        $old_status = ($reg->status == 1 && $reg->waiting == 1) ? 2 : $reg->status;
                        if (!empty($reg->id) && ($old_status != $status || (int) $reg->places !== (int) $userPlaces)) {
                            JemHelper::addLogEntry("Change user {$uid} already registered for event {$row->id}.", __METHOD__, Log::DEBUG);
                            $manualPromotion = (int) $old_status === JemRegistrationTransition::WAITING_LIST
                                && $status === JemRegistrationTransition::ATTENDING;
                            $storedStatus = $manualPromotion ? JemRegistrationTransition::WAITING_LIST : $status;
                            $reg_id = $modelEventItem->adduser($row->id, $uid, $storedStatus, $userPlaces, $comment, $errMsg, $reg->id);
                            if ($reg_id) {
                                $modelAttendeeItem->setId($reg_id);
                                $after = $modelAttendeeItem->getData();

                                if ($manualPromotion) {
                                    $promotion = JemWaitingListPromotion::promote((int) $row->id, array(
                                        'mode' => JemWaitingListPromotion::MODE_MANUAL,
                                        'registrationIds' => array((int) $reg_id),
                                        'notify' => true,
                                        'actorId' => (int) Factory::getApplication()->getIdentity()->id,
                                        'source' => 'site.attendees.manual',
                                    ));

                                    if (!$promotion->success) {
                                        $errMsgs[] = $promotion->reason === 'capacity_exceeded'
                                            ? Text::_('COM_JEM_WAITINGLIST_PROMOTION_CAPACITY_EXCEEDED')
                                            : Text::_('COM_JEM_WAITINGLIST_PROMOTION_FAILED');
                                        ++$error;
                                        continue;
                                    }

                                    if ($promotion->reason === 'notification_failed') {
                                        Factory::getApplication()->enqueueMessage(
                                            Text::_('COM_JEM_WAITINGLIST_PROMOTION_NOTIFICATION_FAILED'),
                                            'warning'
                                        );
                                    }
                                } else {
                                    $transition = JemRegistrationTransition::create(
                                        $reg,
                                        $after,
                                        (int) Factory::getApplication()->getIdentity()->id,
                                        'site.attendees.edit'
                                    );
                                    JemRegistrationTransition::dispatchStatusMail($dispatcher, $after, $transition, false, true);
                                    $transitions[] = $transition;
                                    $releasedCapacityForEvent = $releasedCapacityForEvent
                                        || JemRegistrationTransition::releasesCapacity($reg, $after);

                                    if (JemRegistrationTransition::logicalStatus($after) === JemRegistrationTransition::WAITING_LIST) {
                                        $excludedPromotionIds[] = (int) $reg_id;
                                    }
                                }
                                ++$changed;
                            } else {
                                JemHelper::addLogEntry(implode(' - ', array("Model returned error while changing registration of user {$uid}", $errMsg)), __METHOD__, Log::DEBUG);
                                if (!empty($errMsg)) {
                                    $errMsgs[] = $errMsg;
                                }
                                ++$error;
                            }
                        } else {
                            JemHelper::addLogEntry("Skip user {$uid} already registered for event {$row->id}.", __METHOD__, Log::DEBUG);
                            ++$skip;
                        }
                    } else {
                        $reg_id = $modelEventItem->adduser($row->id, $uid, $status, $userPlaces, $comment, $errMsg);
                        if ($reg_id) {
                            $modelAttendeeItem->setId($reg_id);
                            $after = $modelAttendeeItem->getData();
                            $transition = JemRegistrationTransition::create(
                                null,
                                $after,
                                (int) Factory::getApplication()->getIdentity()->id,
                                'site.attendees.add'
                            );
                            JemRegistrationTransition::dispatchStatusMail($dispatcher, $after, $transition, false, true);
                            $transitions[] = $transition;
                        } else {
                            JemHelper::addLogEntry(implode(' - ', array("Model returned error while adding user {$uid}", $errMsg)), __METHOD__, Log::DEBUG);
                            if (!empty($errMsg)) {
                                $errMsgs[] = $errMsg;
                            }
                            ++$error;
                        }
                    }
                }

                JemRegistrationTransition::dispatchAudit($dispatcher, $transitions);

                if ($releasedCapacityForEvent) {
                    JemHelper::reconcileWaitingList((int) $row->id, array(
                        'source' => 'site.attendees.edit',
                        'excludeIds' => $excludedPromotionIds,
                    ));
                }

                $cache = Factory::getCache('com_jem');
                $cache->clean();

                $msg = ($total - $skip - $error - $changed) . ' ' . Text::_('COM_JEM_REGISTERED_USERS_ADDED') . ' [ID: ' . $row->id . ']';
                if ($changed > 0) {
                    $msg .= ', ' . $changed . ' ' . Text::_('COM_JEM_REGISTERED_USERS_CHANGED');
                }
                $errMsgs = array_unique($errMsgs);

                if (count($errMsgs)) {
                    $msg .= '<br>' . implode('<br>', $errMsgs);
                }
            }
        }
        $this->setRedirect(Route::_('index.php?option=com_jem&view=attendees&id='.$eventid.'&Itemid='.$fid, false), $msg);
    }

    /**
     * removetask
     */
    public function attendeeremove() {
        JemHelper::requirePostToken();

        $input = Factory::getApplication()->input;
        $cid    = $input->post->get('cid', array(), 'array');
        $id     = $input->post->getInt('id', 0);
        $fid    = $input->post->getInt('Itemid', 0);
        $total  = is_array($cid) ? count($cid) : 0;

        $this->assertCanManageAttendees($id);

        if ($total < 1) {
            throw new Exception(Text::_('COM_JEM_SELECT_ITEM_TO_DELETE'), 500);
        }

        $modelAttendeeList = $this->getModel('attendees');

        PluginHelper::importPlugin('jem');
        PluginHelper::importPlugin('actionlog', 'jem');
        $dispatcher = JemFactory::getDispatcher();

        $modelAttendeeItem = $this->getModel('attendee');
        $releasedCapacity = false;

        // We need information about every entry to delete for mailer.
        // But we should first delete the entry and than on success send the mails.
        foreach ($cid as $reg_id) {
            $modelAttendeeItem->setId($reg_id);
            $entry = $modelAttendeeItem->getData();

            if (empty($entry->id) || (int) $entry->event !== (int) $id) {
                throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }

            if ($modelAttendeeList->remove(array($reg_id), $id)) {
                JemRegistrationTransition::dispatchDeletionMail($dispatcher, $entry);
                $dispatcher->triggerEvent('onJemAfterAttendeeDelete', array($entry));
                $releasedCapacity = $releasedCapacity || JemRegistrationTransition::releasesCapacity($entry);
            } else {
                $error = true;
            }
        }
        if (!empty($error)) {
            Factory::getApplication()->enqueueMessage($modelAttendeeList->getError() ?: Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'warning');
        }

        if ($releasedCapacity) {
            JemHelper::reconcileWaitingList($id, array('source' => 'site.attendees.remove'));
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
    public function attendeetoggle() {
        JemHelper::requirePostToken();

        $input = Factory::getApplication()->input;
        $id     = $input->post->getInt('attendee_id', 0);
        $fid    = $input->post->getInt('Itemid', 0);

        $model = $this->getModel('attendee');
        $model->setId($id);

        $attendee = $model->getData();

        if (empty($attendee->id)) {
            throw new Exception(Text::_('COM_JEM_MISSING_ATTENDEE_ID'), 404);
        }

        $this->assertCanManageAttendees($attendee->event);

        $after = clone $attendee;
        $after->status = JemRegistrationTransition::ATTENDING;
        $after->waiting = $attendee->waiting ? 0 : 1;
        $transition = JemRegistrationTransition::create(
            $attendee,
            $after,
            (int) Factory::getApplication()->getIdentity()->id,
            'site.attendees.waitinglist'
        );

        $type = 'message';

        if ($attendee->waiting) {
            $promotion = JemWaitingListPromotion::promote((int) $attendee->event, array(
                'mode' => JemWaitingListPromotion::MODE_MANUAL,
                'registrationIds' => array((int) $attendee->id),
                'notify' => (bool) $input->post->getInt('waitinglist_notify', 0),
                'actorId' => (int) Factory::getApplication()->getIdentity()->id,
                'source' => 'site.attendees.manual',
            ));
            $res = $promotion->success && in_array((int) $attendee->id, $promotion->promotedIds, true);
        } else {
            $res = $model->toggle();
        }

        if ($res && !$attendee->waiting) {
            PluginHelper::importPlugin('jem');
            PluginHelper::importPlugin('actionlog', 'jem');
            $dispatcher = JemFactory::getDispatcher();
            JemRegistrationTransition::dispatchStatusMail($dispatcher, $after, $transition);
            JemRegistrationTransition::dispatchAudit($dispatcher, array($transition));

            if (JemRegistrationTransition::releasesCapacity($attendee, $after)) {
                JemHelper::reconcileWaitingList((int) $attendee->event, array(
                    'source' => 'site.attendees.waitinglist',
                    'excludeIds' => array((int) $attendee->id),
                ));
            }

            $msg = Text::_('COM_JEM_ADDED_TO_WAITING');
        } elseif ($res) {
            $msg = Text::_('COM_JEM_ADDED_TO_ATTENDING');
        } else {
            $reason = isset($promotion) && $promotion->reason === 'capacity_exceeded'
                ? Text::_('COM_JEM_WAITINGLIST_PROMOTION_CAPACITY_EXCEEDED')
                : $model->getError();
            $msg = Text::_('COM_JEM_WAITINGLIST_TOGGLE_ERROR').': '.$reason;
            $type = 'error';
        }

        $this->setRedirect(Route::_('index.php?option=com_jem&view=attendees&id='.$attendee->event.'&Itemid='.$fid, false), $msg, $type);
        $this->redirect();
    }

    /**
     * Exporttask
     * view: attendees
     */
    public function export() {
        $app       = Factory::getApplication();
        $params    = $app->getParams();
        $jemconfig = JemConfig::getInstance()->toRegistry();
        $eventid   = $app->input->getInt('id', 0);

        $this->assertCanManageAttendees($eventid);

        $enableemailaddress = $params->get('enableemailaddress', 0);
        $separator         = $jemconfig->get('csv_separator', ';');
        $delimiter         = $jemconfig->get('csv_delimiter', '"');
        $csv_bom           = $jemconfig->get('csv_bom', '1');
        $userfield         = $jemconfig->get('globalattribs.global_regname', 1) ? 'name' : 'username';
        $comments          = $jemconfig->get('regallowcomments', 0);

        $model = $this->getModel('attendees');
        $datas = $model->getData();
        $event = $model->getEvent();
        $waitinglist = isset($event->waitinglist) ? $event->waitinglist : false;

        JemHelper::setNoStoreHeaders();
        $app->sendHeaders();
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
        if ($enableemailaddress == 1) {
            $cols[] = Text::_('COM_JEM_EMAIL');
        }
        $cols[] = Text::_('COM_JEM_REGDATE');
        $cols[] = Text::_('COM_JEM_STATUS');
        $cols[] = Text::_('COM_JEM_PLACES');
        if ($comments) {
            $cols[] = Text::_('COM_JEM_COMMENT');
        }

        JemCsv::putRow($export, $cols, $separator, $delimiter, '\\');

        $i = 0;
        foreach ($datas as $data) {
            $cols = array();

            $cols[] = ++$i;
            $cols[] = $data->$userfield;
            if ($enableemailaddress == 1) {
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

            JemCsv::putRow($export, $cols, $separator, $delimiter, '\\');
        }

        fclose($export);
        $app->close();
    }
}
?>
