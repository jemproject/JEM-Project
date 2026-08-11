<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
?>
<div id="jem" class="jem-registration-detail">
    <h1><?php echo Text::_('COM_JEM_REGISTRATION_DETAILS'); ?></h1>
    <div class="card mb-4"><div class="card-body">
        <h2><?php echo $this->escape($this->item->event_title); ?></h2>
        <dl class="row">
            <dt class="col-sm-3"><?php echo Text::_('COM_JEM_REGISTRATION_REFERENCE'); ?></dt><dd class="col-sm-9"><code><?php echo $this->escape($this->item->reference); ?></code></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_JEM_STATUS'); ?></dt><dd class="col-sm-9"><?php echo $this->escape((string) $this->item->status); ?><?php echo $this->item->waiting ? ' / ' . Text::_('COM_JEM_ATTENDEES_ON_WAITINGLIST') : ''; ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_JEM_TABLE_PLACES'); ?></dt><dd class="col-sm-9"><?php echo (int) $this->item->places; ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_JEM_EMAIL'); ?></dt><dd class="col-sm-9"><?php echo $this->escape($this->item->user_email); ?></dd>
        </dl>
        <?php if ($this->latestSent && $this->resendPolicy->allowed) : ?>
        <form method="post" action="<?php echo Route::_('index.php?option=com_jem&task=notification.resend'); ?>">
            <input type="hidden" name="notification_id" value="<?php echo (int) $this->latestSent->id; ?>"><input type="hidden" name="registration_id" value="<?php echo (int) $this->item->id; ?>">
            <button class="btn btn-primary" type="submit"><span class="icon-envelope" aria-hidden="true"></span> <?php echo Text::_('COM_JEM_NOTIFICATION_RESEND_TO_ME'); ?></button>
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
        <?php elseif ($this->latestSent) : ?><div class="alert alert-info"><?php echo Text::_($this->resendPolicy->reason === 'cooldown' ? 'COM_JEM_NOTIFICATION_RESEND_COOLDOWN' : 'COM_JEM_NOTIFICATION_RESEND_LIMIT'); ?></div><?php endif; ?>
    </div></div>

    <h2><?php echo Text::_('COM_JEM_NOTIFICATION_HISTORY'); ?></h2>
    <div class="table-responsive"><table class="table table-striped"><thead><tr><th><?php echo Text::_('COM_JEM_NOTIFICATION_CREATED'); ?></th><th><?php echo Text::_('COM_JEM_NOTIFICATION_STATE'); ?></th><th><?php echo Text::_('COM_JEM_NOTIFICATION_MESSAGE'); ?></th></tr></thead><tbody>
    <?php if (!$this->notifications) : ?><tr><td colspan="3"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td></tr><?php endif; ?>
    <?php foreach ($this->notifications as $notification) : ?><tr><td><?php echo HTMLHelper::_('date', $notification->created, Text::_('DATE_FORMAT_LC5')); ?></td><td><?php echo $this->escape($notification->state); ?></td><td><?php echo $this->escape($notification->subject); ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <p><a href="<?php echo Route::_('index.php?option=com_jem&view=myattendances'); ?>"><?php echo Text::_('COM_JEM_BACK_TO_MY_ATTENDANCES'); ?></a></p>
</div>
