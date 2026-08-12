<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Idempotent bridge to Joomla's native Task Scheduler.
 */
final class JemReminderSchedulerService
{
    public const TASK_TYPE = 'jem.notifications';
    public const DEFAULT_INTERVAL_MINUTES = 10;

    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Factory::getContainer()->get('DatabaseDriver');
    }

    public function ensureTask($enabled = false)
    {
        $this->enableTaskPlugin();
        $task = $this->getTask();
        if ($task) {
            $query = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__scheduler_tasks'))
                ->set($this->db->quoteName('state') . ' = ' . ((bool) $enabled ? 1 : 0))
                ->where($this->db->quoteName('id') . ' = ' . (int) $task->id);
            $this->db->setQuery($query)->execute();

            return (int) $task->id;
        }

        $model = Factory::getApplication()
            ->bootComponent('com_scheduler')
            ->getMVCFactory()
            ->createModel('Task', 'Administrator', array('ignore_request' => true));
        $data = array(
            'title' => 'JEM - Process scheduled notifications',
            'type' => self::TASK_TYPE,
            'state' => (bool) $enabled ? 1 : 0,
            'execution_rules' => array(
                'rule-type' => 'interval-minutes',
                'interval-minutes' => self::DEFAULT_INTERVAL_MINUTES,
                'exec-day' => gmdate('d'),
                'exec-time' => gmdate('H:i'),
            ),
            'priority' => 0,
            'params' => array('individual_log' => 0, 'log_file' => ''),
            'note' => 'Managed by JEM global reminder settings.',
        );
        if (!$model || !$model->save($data)) {
            throw new RuntimeException($model ? implode('; ', (array) $model->getErrors()) : 'Joomla Scheduler model unavailable.');
        }

        $task = $this->getTask();

        return $task ? (int) $task->id : 0;
    }

    public function syncFromConfig()
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('value'))
            ->from($this->db->quoteName('#__jem_config'))
            ->where($this->db->quoteName('keyname') . ' = ' . $this->db->quote('reminders_enabled'));
        $this->db->setQuery($query);
        $enabled = (int) $this->db->loadResult() === 1;

        return $this->ensureTask($enabled);
    }

    public function getHealth()
    {
        $query = $this->db->getQuery(true)
            ->select(array('extension_id', 'enabled'))
            ->from($this->db->quoteName('#__extensions'))
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
            ->where($this->db->quoteName('folder') . ' = ' . $this->db->quote('task'))
            ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('jem'));
        $this->db->setQuery($query);
        $plugin = $this->db->loadObject();
        $task = $this->getTask();

        return (object) array(
            'plugin_installed' => (bool) $plugin,
            'plugin_enabled' => $plugin && (int) $plugin->enabled === 1,
            'task_exists' => (bool) $task,
            'task_enabled' => $task && (int) $task->state === 1,
            'task_id' => $task ? (int) $task->id : 0,
            'last_execution' => $task ? (string) $task->last_execution : '',
            'next_execution' => $task ? (string) $task->next_execution : '',
            'times_executed' => $task ? (int) $task->times_executed : 0,
            'times_failed' => $task ? (int) $task->times_failed : 0,
        );
    }

    private function getTask()
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__scheduler_tasks'))
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote(self::TASK_TYPE))
            ->where($this->db->quoteName('state') . ' <> -2')
            ->order($this->db->quoteName('id') . ' ASC');
        $this->db->setQuery($query, 0, 1);

        return $this->db->loadObject();
    }

    private function enableTaskPlugin()
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__extensions'))
            ->set($this->db->quoteName('enabled') . ' = 1')
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
            ->where($this->db->quoteName('folder') . ' = ' . $this->db->quote('task'))
            ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('jem'));
        $this->db->setQuery($query)->execute();
    }
}
