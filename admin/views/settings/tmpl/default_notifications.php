<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<div class="width-100" style="padding: 10px 1vw;">
    <div class="alert alert-info"><?php echo Text::_('COM_JEM_NOTIFICATION_SETTINGS_INTRO'); ?></div>
    <fieldset class="options-form">
        <?php echo $this->form->renderFieldset('notifications'); ?>
    </fieldset>
    <div class="alert alert-secondary mt-3"><?php echo Text::_('COM_JEM_NOTIFICATION_RETENTION_SCHEDULER_NOTE'); ?></div>
</div>
