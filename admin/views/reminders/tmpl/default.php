<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn = $this->escape($this->state->get('list.direction'));
?>
<form action="<?php echo Route::_('index.php?option=com_jem&view=reminders'); ?>" method="post" name="adminForm" id="adminForm">
<div id="j-main-container" class="j-main-container">
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item"><a class="nav-link" href="<?php echo Route::_('index.php?option=com_jem&view=notifications'); ?>"><?php echo Text::_('COM_JEM_NOTIFICATION_TAB_TEMPLATES'); ?></a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo Route::_('index.php?option=com_jem&view=notificationcontent&section=footer'); ?>"><?php echo Text::_('COM_JEM_NOTIFICATION_TAB_FOOTER'); ?></a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo Route::_('index.php?option=com_jem&view=notificationcontent&section=disclaimer'); ?>"><?php echo Text::_('COM_JEM_NOTIFICATION_TAB_DISCLAIMER'); ?></a></li>
        <li class="nav-item"><a class="nav-link active" aria-current="page" href="<?php echo Route::_('index.php?option=com_jem&view=reminders'); ?>"><?php echo Text::_('COM_JEM_NOTIFICATION_TAB_REMINDERS'); ?></a></li>
        <?php if (JemHelperBackend::canManage('jem.notifications.history')) : ?>
            <li class="nav-item"><a class="nav-link" href="<?php echo Route::_('index.php?option=com_jem&view=notificationhistory'); ?>"><?php echo Text::_('COM_JEM_NOTIFICATION_TAB_HISTORY'); ?></a></li>
        <?php endif; ?>
    </ul>
    <div class="alert alert-info"><?php echo Text::_('COM_JEM_REMINDERS_INTRO'); ?></div>
    <div class="row g-2 align-items-end mb-3">
        <div class="col-md-5"><label class="form-label" for="filter_search"><?php echo Text::_('JSEARCH_FILTER'); ?></label><input class="form-control" id="filter_search" name="filter_search" value="<?php echo $this->escape($this->state->get('filter_search')); ?>" placeholder="<?php echo Text::_('COM_JEM_REMINDER_SEARCH_HINT'); ?>"></div>
        <div class="col-md-3"><label class="form-label" for="filter_state"><?php echo Text::_('JSTATUS'); ?></label><select class="form-select" id="filter_state" name="filter_state" onchange="this.form.submit()"><option value=""><?php echo Text::_('JALL'); ?></option><option value="1" <?php echo $this->state->get('filter_state') === '1' ? 'selected' : ''; ?>><?php echo Text::_('JPUBLISHED'); ?></option><option value="0" <?php echo $this->state->get('filter_state') === '0' ? 'selected' : ''; ?>><?php echo Text::_('JUNPUBLISHED'); ?></option></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary" type="submit"><?php echo Text::_('JFILTER'); ?></button><a class="btn btn-secondary" href="<?php echo Route::_('index.php?option=com_jem&view=reminders&filter_search=&filter_state='); ?>"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></a></div>
    </div>
    <div class="table-responsive"><table class="table table-striped itemList"><thead><tr>
        <th class="w-1"><?php echo HTMLHelper::_('grid.checkall'); ?></th>
        <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_REMINDER_TITLE', 'a.title', $listDirn, $listOrder); ?></th>
        <th><?php echo Text::_('COM_JEM_REMINDER_INTERVAL'); ?></th>
        <th><?php echo HTMLHelper::_('grid.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?></th>
        <th><?php echo Text::_('COM_JEM_REMINDER_DEFAULT_NEW_EVENT'); ?></th>
        <th><?php echo Text::_('COM_JEM_REMINDER_EVENTS_USING'); ?></th>
    </tr></thead><tbody>
    <?php if (!$this->items) : ?><tr><td colspan="6" class="text-center"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td></tr><?php endif; ?>
    <?php foreach ($this->items as $i => $item) : ?>
        <?php $title = strpos((string) $item->title, 'COM_JEM_') === 0 ? Text::_((string) $item->title) : (string) $item->title; ?>
        <tr>
            <td><?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>
            <td><a href="<?php echo Route::_('index.php?option=com_jem&task=reminder.edit&id=' . (int) $item->id); ?>"><?php echo $this->escape($title); ?></a><br><small><code><?php echo $this->escape($item->code); ?></code></small></td>
            <td><?php echo Text::plural('COM_JEM_REMINDER_INTERVAL_' . strtoupper($item->unit), (int) $item->amount); ?><br><small><?php echo (int) $item->minutes; ?> <?php echo Text::_('COM_JEM_REMINDER_MINUTES_NORMALISED'); ?></small></td>
            <td><?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'reminders.', true); ?></td>
            <td><?php echo Text::_($item->default_new_event ? 'JYES' : 'JNO'); ?></td>
            <td><?php echo (int) $item->event_count; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php echo $this->pagination->getListFooter(); ?>
    <input type="hidden" name="task" value=""><input type="hidden" name="boxchecked" value="0"><input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>"><input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>"><?php echo HTMLHelper::_('form.token'); ?>
</div></form>
