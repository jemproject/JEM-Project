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
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Log\Log;
use Joomla\Utilities\ArrayHelper;

/**
 * Controller: Attendees
 */
class JemControllerAttendees extends BaseController
{
    /**
     * Constructor
     */
    public function __construct($config = array()) {
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
     * Check whether the current user can manage attendees in the backend.
     *
     * @return void
     */
    private function assertCanManageAttendees()
    {
        if (!JemHelperBackend::canManage('jem.attendees.manage')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * Delete attendees
     *
     * @return true on sucess
     * @access private
     */
    public function remove() {
        // Check for request forgeries.
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertCanManageAttendees();

        $jinput = Factory::getApplication()->input;
        $cid = $jinput->get('cid',  0, 'array');
        $eventid = $jinput->getInt('eventid');

        if (!is_array($cid) || count($cid) < 1) {
            throw new Exception(Text::_('COM_JEM_SELECT_ITEM_TO_DELETE'), 500);
        }

        ArrayHelper::toInteger($cid);
        $cid = array_filter($cid);

        if (empty($cid) || $eventid < 1) {
            throw new Exception(Text::_('COM_JEM_SELECT_ITEM_TO_DELETE'), 500);
        }

        $total = count($cid);

        PluginHelper::importPlugin('jem');
        PluginHelper::importPlugin('actionlog', 'jem');
        $dispatcher = JemFactory::getDispatcher();

        $modelAttendeeList = $this->getModel('attendees');
        $modelAttendeeItem = $this->getModel('attendee');

        // We need information about every entry to delete for mailer.
        // But we should first delete the entry and than on success send the mails.
        foreach ($cid as $reg_id) {
            $modelAttendeeItem->setId($reg_id);
            $entry = $modelAttendeeItem->getData();
            if (empty($entry->event) || (int)$entry->event !== (int)$eventid) {
                $error = true;
                continue;
            }
            if ($modelAttendeeList->remove(array($reg_id), $eventid)) {
                JemRegistrationTransition::dispatchDeletionMail($dispatcher, $entry);
                $dispatcher->triggerEvent('onJemAfterAttendeeDelete', array($entry));
            } else {
                $error = true;
            }
        }
        if (!empty($error)) {
            Factory::getApplication()->enqueueMessage($modelAttendeeList->getError() ?: Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'warning');
        }

        $cache = Factory::getCache('com_jem');
        $cache->clean();

        $msg = $total . ' ' . Text::_('COM_JEM_REGISTERED_USERS_DELETED');

        $this->setRedirect('index.php?option=com_jem&view=attendees&eventid=' . $eventid, $msg);
    }

    /**
     * Function to export
     */
    public function export() {
        // Check for request forgeries
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertCanManageAttendees();

        header('Content-Description: File Transfer');
        header('Content-Type: text/csv; charset=utf-8');
        header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Content-Disposition: attachment; filename="attendees_'.date('Y-m-d').'.csv"');
        header('Content-Transfer-Encoding: binary');
        header('Pragma: no-cache');

        echo "\xEF\xBB\xBF"; // Add BOM

        $model = $this->getModel('attendees');
        $model->getCsv();
        jexit();
    }

    /**
     * redirect to events page
     */
    public function back() {
        $this->setRedirect('index.php?option=com_jem&view=events');
    }

    /**
     * Function to change status
     */
    public function toggleStatus() {
        // Check for request forgeries
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertCanManageAttendees();

        $app  = Factory::getApplication();
        $pks  = $app->input->get('cid', array(), 'array');
        $task = $this->getTask();
        $redirectEvent = $app->input->getInt('eventid', 0);

        if (empty($pks)) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'warning');
        } else {
            ArrayHelper::toInteger($pks);
            $pks = array_filter($pks);
            $model = $this->getModel('attendee');

            PluginHelper::importPlugin('jem');
            PluginHelper::importPlugin('actionlog', 'jem');
            $dispatcher = JemFactory::getDispatcher();

            foreach ($pks AS $pk) {
                $model->setId($pk);
                $attendee = $model->getData();
                if (empty($attendee->event)) {
                    continue;
                }
                $redirectEvent = (int)$attendee->event;
                $after = clone $attendee;
                $after->status = JemRegistrationTransition::ATTENDING;
                $after->waiting = $attendee->waiting ? 0 : 1;
                $transition = JemRegistrationTransition::create(
                    $attendee,
                    $after,
                    (int) $app->getIdentity()->id,
                    'administrator.attendees.waitinglist'
                );
                $res = $model->toggle();

                if ($res) {
                    JemRegistrationTransition::dispatchStatusMail($dispatcher, $after, $transition);
                    JemRegistrationTransition::dispatchAudit($dispatcher, array($transition));

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

        $this->setRedirect('index.php?option=com_jem&view=attendees&eventid=' . $redirectEvent);
        $this->redirect();
    }

    /**
     * logic to create the edit attendee view
     *
     * @access public
     * @return void
     *
     */
    public function edit() {
        // Check for request forgeries.
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertCanManageAttendees();

        $jinput = Factory::getApplication()->input;
        $jinput->set('view', 'attendee');
        // 'attendee' expects event id as 'event' not 'id'
        $jinput->set('event', $jinput->getInt('eventid'));
        $cid = $jinput->get('cid', array(), 'array');
        $jinput->set('id', (int) ($cid[0] ?? 0));
        $jinput->set('hidemainmenu', '1');

        parent::display();
    }

    /**
     * Method to change status of selected rows.
     *
     * @return  void
     */
    public function setStatus() {
        // Check for request forgeries
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertCanManageAttendees();

        $app = Factory::getApplication();
        $user = $app->getIdentity();

        $eventid = $app->input->getInt('eventid');
        $ids     = $app->input->get('cid', array(), 'array');
        $values  = array('setWaitinglist' => 2, 'setAttending' => 1, 'setInvited' => 0, 'setNotAttending' => -1);
        $task    = $this->getTask();
        $value   = array_key_exists($task, $values) ? (int) $values[$task] : null;

        if ($value === null || !JemRegistrationTransition::isValidStatus($value)) {
            throw new Exception(Text::_('COM_JEM_ATTENDEES_STATUS_UNKNOWN'), 400);
        }

        if (empty($ids)) {
            $message = Text::_('JERROR_NO_ITEMS_SELECTED');
            Factory::getApplication()->enqueueMessage($message, 'warning');
        } else {
            ArrayHelper::toInteger($ids);
            $ids = array_filter($ids);

            // Get the model.
            $model = $this->getModel('attendee');
            $transitions = array();
            $changedIds = array();
            $changedRows = array();

            // Validate the complete selection before changing any row. This
            // prevents cross-event IDs from being updated or notified.
            foreach ($ids as $pk) {
                $model->setId($pk);
                $before = $model->getData();

                if (empty($before->id) || (int) $before->event !== (int) $eventid) {
                    throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
                }

                $after = clone $before;
                JemRegistrationTransition::applyLogicalStatus($after, $value);
                $transition = JemRegistrationTransition::create(
                    $before,
                    $after,
                    (int) $user->id,
                    'administrator.attendees.batch'
                );

                if ($transition->changed) {
                    $changedIds[] = (int) $pk;
                    $changedRows[(int) $pk] = $after;
                    $transitions[] = $transition;
                }
            }

            // Publish the items.
            if ($changedIds && !$model->setStatus($changedIds, $value, $eventid)) {
                $message = $model->getError();
                JemHelper::addLogEntry($message, __METHOD__, Log::ERROR);
                Factory::getApplication()->enqueueMessage($message, 'warning');
            } else {
                PluginHelper::importPlugin('jem');
                PluginHelper::importPlugin('actionlog', 'jem');
                $dispatcher = JemFactory::getDispatcher();

                switch ($value) {
                    case -1:
                        $message = Text::plural('COM_JEM_ATTENDEES_N_ITEMS_NOTATTENDING', count($changedIds));
                        break;
                    case 0:
                        $message = Text::plural('COM_JEM_ATTENDEES_N_ITEMS_INVITED', count($changedIds));
                        break;
                    case 1:
                        $message = Text::plural('COM_JEM_ATTENDEES_N_ITEMS_ATTENDING', count($changedIds));
                        break;
                    case 2:
                        $message = Text::plural('COM_JEM_ATTENDEES_N_ITEMS_WAITINGLIST', count($changedIds));
                        break;
                }

                foreach ($transitions as $transition) {
                    JemRegistrationTransition::dispatchStatusMail(
                        $dispatcher,
                        $changedRows[(int) $transition->registrationId],
                        $transition
                    );
                }

                JemRegistrationTransition::dispatchAudit($dispatcher, $transitions);

                JemHelper::addLogEntry($message, __METHOD__, Log::DEBUG);
            }
        }

        $this->setRedirect(Route::_('index.php?option=com_jem&view=attendees&eventid=' . $eventid, false), $message);
    }

    public function renotify()
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));
        $this->assertCanManageAttendees();

        $app = Factory::getApplication();
        $eventid = $app->input->getInt('eventid', 0);
        $ids = $app->input->get('cid', array(), 'array');

        if (empty($ids)) {
            $message = Text::_('JERROR_NO_ITEMS_SELECTED');
            $type = 'warning';
        } else {
            ArrayHelper::toInteger($ids);
            $ids = array_filter($ids);

            PluginHelper::importPlugin('jem');
            $dispatcher = JemFactory::getDispatcher();
            $model = $this->getModel('attendee');
            $sent = 0;

            foreach ($ids as $id) {
                $model->setId($id);
                $attendee = $model->getData();

                if (empty($attendee->id) || (int) $attendee->event !== (int) $eventid) {
                    continue;
                }

                $transition = JemRegistrationTransition::create(
                    $attendee,
                    $attendee,
                    (int) $app->getIdentity()->id,
                    'backend.renotify'
                );

                if (JemRegistrationTransition::dispatchStatusMail(
                    $dispatcher,
                    $attendee,
                    $transition,
                    true,
                    true
                )) {
                    ++$sent;
                }
            }

            if (!PluginHelper::isEnabled('jem', 'mailer')) {
                $app->enqueueMessage(Text::_('COM_JEM_GLOBAL_MAILERPLUGIN_DISABLED'), 'notice');
            }

            $message = Text::plural('COM_JEM_ATTENDEE_REGISTRATION_RENOTIFIED_N', $sent);
            $type = 'message';
        }

        $this->setRedirect(Route::_('index.php?option=com_jem&view=attendees&eventid=' . $eventid, false), $message, $type);
    }
}
