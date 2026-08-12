<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$schedulerHealth = null;
try {
    require_once JPATH_SITE . '/components/com_jem/factory.php';
    $schedulerHealth = (new JemReminderSchedulerService())->getHealth();
} catch (Throwable $error) {
    $schedulerHealth = null;
}
?>
<div class="width-100" style="padding: 10px 1vw;">
    <div class="alert alert-info"><?php echo Text::_('COM_JEM_NOTIFICATION_SETTINGS_INTRO'); ?></div>
    <fieldset class="options-form">
        <?php echo $this->form->renderFieldset('notifications'); ?>
    </fieldset>
    <?php if ($schedulerHealth) : ?>
        <?php $healthy = $schedulerHealth->plugin_enabled && $schedulerHealth->task_exists && $schedulerHealth->task_enabled; ?>
        <div class="alert alert-<?php echo $healthy ? 'success' : 'secondary'; ?> mt-3">
            <strong><?php echo Text::_('COM_JEM_REMINDER_SCHEDULER_STATUS'); ?>:</strong>
            <?php echo Text::_($healthy ? 'COM_JEM_REMINDER_SCHEDULER_ACTIVE' : 'COM_JEM_REMINDER_SCHEDULER_INACTIVE'); ?>
            <?php if ($schedulerHealth->task_id) : ?>
                &mdash; <a href="<?php echo Route::_('index.php?option=com_scheduler&task=task.edit&id=' . (int) $schedulerHealth->task_id); ?>">
                    <?php echo Text::_('COM_JEM_REMINDER_SCHEDULER_OPEN'); ?>
                </a>
            <?php endif; ?>
            <?php if ($schedulerHealth->last_execution) : ?>
                <br><small><?php echo Text::sprintf('COM_JEM_REMINDER_SCHEDULER_LAST_RUN', $schedulerHealth->last_execution); ?></small>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="alert alert-warning mt-3"><?php echo Text::_('COM_JEM_REMINDER_SCHEDULER_UNAVAILABLE'); ?></div>
    <?php endif; ?>
    <div class="alert alert-secondary mt-3"><?php echo Text::_('COM_JEM_NOTIFICATION_RETENTION_SCHEDULER_NOTE'); ?></div>
</div>
