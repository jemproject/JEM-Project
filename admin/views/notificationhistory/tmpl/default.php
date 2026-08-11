<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn = $this->escape($this->state->get('list.direction'));
?>
<form action="<?php echo Route::_('index.php?option=com_jem&view=notificationhistory'); ?>" method="post" name="adminForm" id="adminForm">
<div id="j-main-container" class="j-main-container">
    <ul class="nav nav-tabs mb-4" role="tablist">
        <?php if (JemHelperBackend::canManage('jem.notifications.templates')) : ?>
            <li class="nav-item"><a class="nav-link" href="<?php echo Route::_('index.php?option=com_jem&view=notifications'); ?>"><?php echo Text::_('COM_JEM_NOTIFICATION_TAB_TEMPLATES'); ?></a></li>
            <li class="nav-item"><a class="nav-link" href="<?php echo Route::_('index.php?option=com_jem&view=notificationcontent&section=footer'); ?>"><?php echo Text::_('COM_JEM_NOTIFICATION_TAB_FOOTER'); ?></a></li>
            <li class="nav-item"><a class="nav-link" href="<?php echo Route::_('index.php?option=com_jem&view=notificationcontent&section=disclaimer'); ?>"><?php echo Text::_('COM_JEM_NOTIFICATION_TAB_DISCLAIMER'); ?></a></li>
        <?php endif; ?>
        <li class="nav-item"><a class="nav-link active" aria-current="page" href="<?php echo Route::_('index.php?option=com_jem&view=notificationhistory'); ?>"><?php echo Text::_('COM_JEM_NOTIFICATION_TAB_HISTORY'); ?></a></li>
    </ul>

    <div class="alert alert-info"><?php echo Text::_('COM_JEM_NOTIFICATION_HISTORY_INTRO'); ?></div>
    <div class="row g-2 align-items-end mb-3">
        <div class="col-lg-4"><label class="form-label" for="filter_search"><?php echo Text::_('JSEARCH_FILTER'); ?></label><input class="form-control" id="filter_search" name="filter_search" value="<?php echo $this->escape($this->state->get('filter_search')); ?>" placeholder="<?php echo Text::_('COM_JEM_NOTIFICATION_HISTORY_SEARCH_HINT'); ?>"></div>
        <?php foreach (array('state' => $this->states, 'type' => $this->types, 'language' => $this->languages) as $filter => $options) : ?>
        <div class="col-lg-2"><label class="form-label" for="filter_<?php echo $filter; ?>"><?php echo Text::_('COM_JEM_NOTIFICATION_' . strtoupper($filter)); ?></label><select class="form-select" id="filter_<?php echo $filter; ?>" name="filter_<?php echo $filter; ?>" onchange="this.form.submit()"><option value=""><?php echo Text::_('JALL'); ?></option><?php foreach ($options as $option) : ?><option value="<?php echo $this->escape($option); ?>" <?php echo $this->state->get('filter_' . $filter) === $option ? 'selected' : ''; ?>><?php echo $this->escape($option); ?></option><?php endforeach; ?></select></div>
        <?php endforeach; ?>
        <div class="col-lg-2 d-flex gap-2"><button class="btn btn-primary" type="submit"><?php echo Text::_('JFILTER'); ?></button><a class="btn btn-secondary" href="<?php echo Route::_('index.php?option=com_jem&view=notificationhistory&filter_search=&filter_state=&filter_type=&filter_language='); ?>"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></a></div>
    </div>

    <div class="table-responsive"><table class="table table-striped itemList"><thead><tr>
        <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_NOTIFICATION_CREATED', 'n.created', $listDirn, $listOrder); ?></th>
        <th><?php echo Text::_('COM_JEM_NOTIFICATION_STATE'); ?></th><th><?php echo Text::_('COM_JEM_NOTIFICATION_TYPE'); ?></th>
        <th><?php echo Text::_('COM_JEM_EVENT'); ?></th><th><?php echo Text::_('COM_JEM_NOTIFICATION_RECIPIENT'); ?></th>
        <th><?php echo Text::_('COM_JEM_NOTIFICATION_MESSAGE'); ?></th><th><?php echo Text::_('COM_JEM_NOTIFICATION_ATTEMPTS'); ?></th><th><?php echo Text::_('COM_JEM_NOTIFICATION_ACTIONS'); ?></th>
    </tr></thead><tbody>
    <?php if (!$this->items) : ?><tr><td colspan="8" class="text-center"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td></tr><?php endif; ?>
    <?php foreach ($this->items as $item) : ?>
        <tr><td><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC5')); ?><br><small>#<?php echo (int) $item->id; ?></small></td>
        <td><span class="badge bg-<?php echo $item->state === 'sent' ? 'success' : ($item->state === 'failed' ? 'danger' : 'secondary'); ?>"><?php echo $this->escape($item->state); ?></span><?php if (!empty($item->last_error)) : ?><br><small class="text-danger"><?php echo $this->escape($item->last_error); ?></small><?php endif; ?></td>
        <td><?php echo $this->escape($item->notification_type); ?><br><small><?php echo $this->escape($item->resolved_language); ?> · <?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_REVISION'); ?> <?php echo (int) $item->registration_revision; ?></small></td>
        <td><?php echo $this->escape($item->event_title ?: ('#' . $item->event_id)); ?><br><a href="<?php echo Route::_('index.php?option=com_jem&view=attendee&id=' . (int) $item->registration_id . '&eventid=' . (int) $item->event_id); ?>"><code><?php echo $this->escape($item->registration_reference); ?></code></a></td>
        <td><?php echo $this->escape($item->recipient_name); ?><br><small><?php echo $this->escape($item->recipient_email); ?> (<?php echo $this->escape($item->recipient_type); ?>)</small></td>
        <td><details><summary><?php echo $this->escape($item->subject); ?></summary><pre class="mt-2 text-wrap"><?php echo $this->escape($item->body); ?></pre></details></td>
        <td><?php echo (int) $item->attempts_total; ?> / <?php echo (int) $item->max_attempts; ?></td>
        <td><?php if ($this->canResend && in_array($item->state, array('queued', 'failed'), true) && $item->attempt_count < $item->max_attempts) : ?><button class="btn btn-sm btn-warning" type="submit" form="notification-action-<?php echo (int) $item->id; ?>" name="task" value="notification.retry"><?php echo Text::_('COM_JEM_NOTIFICATION_RETRY'); ?></button><?php elseif ($this->canResend && $item->state === 'sent') : ?><button class="btn btn-sm btn-primary" type="submit" form="notification-action-<?php echo (int) $item->id; ?>" name="task" value="notification.resend"><?php echo Text::_('COM_JEM_NOTIFICATION_RESEND'); ?></button><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php echo $this->pagination->getListFooter(); ?>
    <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>"><input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>"><?php echo HTMLHelper::_('form.token'); ?>
</div></form>
<?php foreach ($this->items as $item) : ?><form id="notification-action-<?php echo (int) $item->id; ?>" action="<?php echo Route::_('index.php?option=com_jem'); ?>" method="post"><input type="hidden" name="notification_id" value="<?php echo (int) $item->id; ?>"><input type="hidden" name="return_view" value="notificationhistory"><?php echo HTMLHelper::_('form.token'); ?></form><?php endforeach; ?>
