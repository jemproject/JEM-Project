<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$statusLabels = array(
    -1 => Text::_('COM_JEM_ATTENDEES_NOT_ATTENDING'),
    0 => Text::_('COM_JEM_ATTENDEES_INVITED'),
    1 => Text::_('COM_JEM_ATTENDEES_ATTENDING'),
    2 => Text::_('COM_JEM_ATTENDEES_ON_WAITINGLIST'),
);
$status = static function ($value) use ($statusLabels) {
    return $value === null ? '—' : ($statusLabels[(int) $value] ?? Text::_('COM_JEM_ATTENDEES_STATUS_UNKNOWN'));
};
$user = static function ($id, $name, $username) {
    if ($id === null) {
        return '—';
    }
    if ((int) $id === 0) {
        return Text::_('COM_JEM_REGISTRATION_HISTORY_SYSTEM');
    }
    $identity = trim((string) $name);
    if ($identity !== '' && $username) {
        $identity .= ' (' . $username . ')';
    }
    return $identity !== '' ? $identity . ' [#' . (int) $id . ']' : '#' . (int) $id;
};
$changed = json_decode((string) $this->item->changed_fields, true);
$changed = is_array($changed) ? $changed : array();
?>

<div id="j-main-container" class="j-main-container">
    <div class="card mb-4">
        <div class="card-header"><strong><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_ENTRY'); ?> #<?php echo (int) $this->item->id; ?></strong></div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3"><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_REFERENCE'); ?></dt>
                <dd class="col-sm-9"><code><?php echo $this->escape($this->item->registration_reference); ?></code></dd>
                <dt class="col-sm-3"><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_OPERATION'); ?></dt>
                <dd class="col-sm-9"><code><?php echo $this->escape($this->item->operation_reference); ?></code></dd>
                <dt class="col-sm-3"><?php echo Text::_('COM_JEM_EVENT'); ?></dt>
                <dd class="col-sm-9">
                    <?php if ($this->item->current_event_id) : ?>
                        <a href="<?php echo Route::_('index.php?option=com_jem&task=event.edit&id=' . (int) $this->item->event_id); ?>"><?php echo $this->escape($this->item->event_display_title); ?></a>
                    <?php else : ?>
                        <?php echo $this->escape($this->item->event_display_title ?: ('#' . (int) $this->item->event_id)); ?>
                    <?php endif; ?>
                    [#<?php echo (int) $this->item->event_id; ?>]
                </dd>
                <dt class="col-sm-3"><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_ACTION'); ?></dt>
                <dd class="col-sm-9"><?php echo $this->escape($this->item->action); ?></dd>
                <dt class="col-sm-3"><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_OCCURRED'); ?></dt>
                <dd class="col-sm-9"><?php echo $this->escape($this->item->occurred); ?> UTC</dd>
                <dt class="col-sm-3"><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_SOURCE'); ?></dt>
                <dd class="col-sm-9"><code><?php echo $this->escape($this->item->source); ?></code></dd>
                <dt class="col-sm-3"><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_REASON'); ?></dt>
                <dd class="col-sm-9"><?php echo $this->escape($this->item->reason_code ?: '—'); ?></dd>
                <dt class="col-sm-3"><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_ACTOR'); ?></dt>
                <dd class="col-sm-9"><?php echo $this->escape($user($this->item->actor_user_id, $this->item->actor_name, $this->item->actor_username)); ?></dd>
                <dt class="col-sm-3"><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_FORCED'); ?></dt>
                <dd class="col-sm-9"><?php echo $this->item->forced ? Text::_('JYES') : Text::_('JNO'); ?></dd>
                <dt class="col-sm-3"><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_CHANGED_FIELDS'); ?></dt>
                <dd class="col-sm-9">
                    <?php if (!$changed) : ?>—<?php endif; ?>
                    <?php foreach ($changed as $field) : ?><span class="badge bg-secondary me-1"><?php echo $this->escape($field); ?></span><?php endforeach; ?>
                </dd>
            </dl>
        </div>
    </div>

    <h2><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_TIMELINE'); ?></h2>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_REVISION'); ?></th>
                    <th><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_OCCURRED'); ?></th>
                    <th><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_ACTION'); ?></th>
                    <th><?php echo Text::_('JSTATUS'); ?></th>
                    <th><?php echo Text::_('COM_JEM_ATTENDEES_PLACES'); ?></th>
                    <th><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_HOLDER'); ?></th>
                    <th><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_ACTOR'); ?></th>
                    <th><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_SOURCE'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($this->timeline as $entry) : ?>
                <tr class="<?php echo (int) $entry->id === (int) $this->item->id ? 'table-info' : ''; ?>">
                    <td><?php echo (int) $entry->revision; ?></td>
                    <td><a href="<?php echo Route::_('index.php?option=com_jem&view=registrationhistoryentry&id=' . (int) $entry->id); ?>"><?php echo $this->escape($entry->occurred); ?></a></td>
                    <td><?php echo $this->escape($entry->action); ?></td>
                    <td><?php echo $this->escape($status($entry->old_status)); ?> → <?php echo $this->escape($status($entry->new_status)); ?></td>
                    <td><?php echo $entry->old_places === null ? '—' : (int) $entry->old_places; ?> → <?php echo $entry->new_places === null ? '—' : (int) $entry->new_places; ?></td>
                    <td><?php echo $this->escape($user($entry->new_user_id ?? $entry->old_user_id, $entry->new_holder_name ?? $entry->old_holder_name, $entry->new_holder_username ?? $entry->old_holder_username)); ?></td>
                    <td><?php echo $this->escape($user($entry->actor_user_id, $entry->actor_name, $entry->actor_username)); ?></td>
                    <td><code><?php echo $this->escape($entry->source); ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
