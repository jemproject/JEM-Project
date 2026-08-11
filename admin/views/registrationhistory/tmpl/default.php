<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn = $this->escape($this->state->get('list.direction'));
$statusLabels = array(
    -1 => Text::_('COM_JEM_ATTENDEES_NOT_ATTENDING'),
    0 => Text::_('COM_JEM_ATTENDEES_INVITED'),
    1 => Text::_('COM_JEM_ATTENDEES_ATTENDING'),
    2 => Text::_('COM_JEM_ATTENDEES_ON_WAITINGLIST'),
);

$statusText = static function ($status) use ($statusLabels) {
    return $status === null ? '—' : ($statusLabels[(int) $status] ?? Text::_('COM_JEM_ATTENDEES_STATUS_UNKNOWN'));
};
?>

<form action="<?php echo Route::_('index.php?option=com_jem&view=registrationhistory'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <div class="alert alert-info">
            <?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_READ_ONLY_NOTICE'); ?>
        </div>

        <fieldset id="filter-bar" class="mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label" for="filter_search"><?php echo Text::_('JSEARCH_FILTER'); ?></label>
                    <div class="input-group">
                        <input type="text" name="filter_search" id="filter_search" class="form-control"
                               placeholder="<?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_SEARCH_HINT'); ?>"
                               value="<?php echo $this->escape($this->state->get('filter_search')); ?>" />
                        <button type="submit" class="btn btn-primary" title="<?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?>">
                            <span class="icon-search" aria-hidden="true"></span>
                        </button>
                    </div>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label" for="filter_action"><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_ACTION'); ?></label>
                    <select name="filter_action" id="filter_action" class="form-select" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('JALL'); ?></option>
                        <?php foreach ($this->actions as $action) : ?>
                            <option value="<?php echo $this->escape($action); ?>" <?php echo $this->state->get('filter_action') === $action ? 'selected' : ''; ?>><?php echo $this->escape($action); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label" for="filter_status"><?php echo Text::_('JSTATUS'); ?></label>
                    <select name="filter_status" id="filter_status" class="form-select" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('JALL'); ?></option>
                        <?php foreach ($statusLabels as $value => $label) : ?>
                            <option value="<?php echo (int) $value; ?>" <?php echo (string) $this->state->get('filter_status') === (string) $value ? 'selected' : ''; ?>><?php echo $this->escape($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label" for="filter_source"><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_SOURCE'); ?></label>
                    <select name="filter_source" id="filter_source" class="form-select" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('JALL'); ?></option>
                        <?php foreach ($this->sources as $source) : ?>
                            <option value="<?php echo $this->escape($source); ?>" <?php echo $this->state->get('filter_source') === $source ? 'selected' : ''; ?>><?php echo $this->escape($source); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label" for="filter_orphaned"><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_RECORD'); ?></label>
                    <select name="filter_orphaned" id="filter_orphaned" class="form-select" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('JALL'); ?></option>
                        <option value="0" <?php echo $this->state->get('filter_orphaned') === '0' ? 'selected' : ''; ?>><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_CURRENT'); ?></option>
                        <option value="1" <?php echo $this->state->get('filter_orphaned') === '1' ? 'selected' : ''; ?>><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_ORPHANED'); ?></option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label" for="filter_event_id"><?php echo Text::_('COM_JEM_EVENT_ID'); ?></label>
                    <input type="number" min="0" name="filter_event_id" id="filter_event_id" class="form-control" value="<?php echo (int) $this->state->get('filter_event_id'); ?>" />
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label" for="filter_actor_id"><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_ACTOR_ID'); ?></label>
                    <input type="number" min="0" name="filter_actor_id" id="filter_actor_id" class="form-control" value="<?php echo (int) $this->state->get('filter_actor_id'); ?>" />
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label" for="filter_begin"><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_FROM'); ?></label>
                    <input type="date" name="filter_begin" id="filter_begin" class="form-control" value="<?php echo $this->escape($this->state->get('filter_begin')); ?>" />
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label" for="filter_end"><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_TO'); ?></label>
                    <input type="date" name="filter_end" id="filter_end" class="form-control" value="<?php echo $this->escape($this->state->get('filter_end')); ?>" />
                </div>
                <div class="col-lg-2 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><?php echo Text::_('JFILTER'); ?></button>
                    <button type="button" class="btn btn-secondary"
                            onclick="['filter_search','filter_action','filter_status','filter_source','filter_orphaned','filter_event_id','filter_actor_id','filter_begin','filter_end'].forEach(function(id){document.getElementById(id).value='';});this.form.filter_registration_id.value='';this.form.submit();">
                        <?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>
                    </button>
                </div>
                <div class="col-lg-2 col-md-3 ms-auto">
                    <?php echo $this->pagination->getLimitBox(); ?>
                </div>
            </div>
        </fieldset>

        <div class="table-responsive">
            <table class="table table-striped itemList" id="registrationHistoryList">
                <thead>
                    <tr>
                        <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_REGISTRATION_HISTORY_OCCURRED', 'h.occurred', $listDirn, $listOrder); ?></th>
                        <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_REGISTRATION_HISTORY_REFERENCE', 'h.registration_reference', $listDirn, $listOrder); ?></th>
                        <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_EVENT', 'h.event_title', $listDirn, $listOrder); ?></th>
                        <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_REGISTRATION_HISTORY_ACTION', 'h.action', $listDirn, $listOrder); ?></th>
                        <th><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_CHANGE'); ?></th>
                        <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_REGISTRATION_HISTORY_HOLDER', 'holder_name', $listDirn, $listOrder); ?></th>
                        <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_REGISTRATION_HISTORY_ACTOR', 'actor_name', $listDirn, $listOrder); ?></th>
                        <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_REGISTRATION_HISTORY_SOURCE', 'h.source', $listDirn, $listOrder); ?></th>
                        <th class="text-center"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_REGISTRATION_HISTORY_REVISION', 'h.revision', $listDirn, $listOrder); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$this->items) : ?>
                    <tr><td colspan="9" class="text-center"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td></tr>
                <?php endif; ?>
                <?php foreach ($this->items as $item) : ?>
                    <?php $detailUrl = Route::_('index.php?option=com_jem&view=registrationhistoryentry&id=' . (int) $item->id); ?>
                    <tr>
                        <td class="text-nowrap"><a href="<?php echo $detailUrl; ?>"><?php echo $this->escape($item->occurred); ?></a></td>
                        <td>
                            <code><?php echo $this->escape($item->registration_reference); ?></code>
                            <?php if ($item->current_registration_id === null) : ?>
                                <span class="badge bg-secondary"><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_ORPHANED'); ?></span>
                            <?php endif; ?>
                            <br><small class="text-muted"><code><?php echo $this->escape($item->operation_reference); ?></code></small>
                        </td>
                        <td>
                            <?php if ($item->current_event_id) : ?>
                                <a href="<?php echo Route::_('index.php?option=com_jem&task=event.edit&id=' . (int) $item->event_id); ?>"><?php echo $this->escape($item->event_display_title); ?></a>
                            <?php else : ?>
                                <?php echo $this->escape($item->event_display_title ?: ('#' . (int) $item->event_id)); ?>
                            <?php endif; ?>
                            <br><small class="text-muted">#<?php echo (int) $item->event_id; ?></small>
                        </td>
                        <td><span class="badge bg-info text-dark"><?php echo $this->escape($item->action); ?></span></td>
                        <td>
                            <?php echo $this->escape($statusText($item->old_status)); ?> → <?php echo $this->escape($statusText($item->new_status)); ?>
                            <?php if ($item->old_places !== $item->new_places) : ?>
                                <br><small class="text-muted"><?php echo Text::_('COM_JEM_ATTENDEES_PLACES'); ?>: <?php echo $item->old_places === null ? '—' : (int) $item->old_places; ?> → <?php echo $item->new_places === null ? '—' : (int) $item->new_places; ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $this->escape($item->holder_name ?: ($item->holder_user_id ? '#' . (int) $item->holder_user_id : '—')); ?></td>
                        <td><?php echo $this->escape($item->actor_name ?: ($item->actor_user_id ? '#' . (int) $item->actor_user_id : Text::_('COM_JEM_REGISTRATION_HISTORY_SYSTEM'))); ?></td>
                        <td><code><?php echo $this->escape($item->source); ?></code></td>
                        <td class="text-center"><?php echo (int) $item->revision; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php echo $this->pagination->getListFooter(); ?>
        <input type="hidden" name="filter_registration_id" value="<?php echo (int) $this->state->get('filter_registration_id'); ?>" />
        <input type="hidden" name="task" value="" />
        <input type="hidden" name="boxchecked" value="0" />
        <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
        <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
