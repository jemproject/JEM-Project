<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

namespace Joomla\Plugin\Task\Jem\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;

defined('_JEXEC') or die;

/**
 * Exposes JEM notification processing through Joomla's native scheduler.
 */
final class Jem extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    private const TASKS_MAP = array(
        'jem.notifications' => array(
            'langConstPrefix' => 'PLG_TASK_JEM_PROCESS_NOTIFICATIONS',
            'method'          => 'processNotifications',
            'form'            => 'process_notifications',
        ),
    );

    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return array(
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        );
    }

    private function processNotifications(ExecuteTaskEvent $event): int
    {
        $application = $this->getApplication();
        if (method_exists($application, 'getIdentity')
            && method_exists($application, 'loadIdentity')
            && $application->getIdentity() === null) {
            // Joomla 6 CLI may not create the guest identity before task
            // completion events are dispatched to third-party plugins.
            $application->loadIdentity();
        }

        if (!is_file(JPATH_SITE . '/components/com_jem/factory.php')) {
            $this->logTask('JEM component is not installed.', 'error');

            return Status::KNOCKOUT;
        }

        require_once JPATH_SITE . '/components/com_jem/factory.php';

        try {
            $service = new \JemReminderService();
            PluginHelper::importPlugin('jem');
            $result = $service->processDue(function ($notificationId) use ($application) {
                $responses = $application->triggerEvent(
                    'onJemNotificationAction',
                    array((int) $notificationId, 'retry', 0, 'scheduler')
                );

                return in_array(true, (array) $responses, true);
            }, 100);

            $this->logTask(sprintf(
                'JEM notifications: %d due, %d sent, %d failed, %d cancelled, %d recovered, %d purged.',
                $result->due,
                $result->sent,
                $result->failed,
                $result->cancelled,
                $result->recovered,
                $result->purged
            ));

            return Status::OK;
        } catch (\Throwable $error) {
            $this->logTask('JEM notification task failed: ' . $error->getMessage(), 'error');

            return Status::KNOCKOUT;
        }
    }
}
