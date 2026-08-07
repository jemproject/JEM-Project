<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$scheduleJson = (string) ($this->item->custom_schedule_json ?? '');
$isExistingSeries = !empty($this->item->series_id);
?>
<div id="custom_schedule_editor" class="jem-custom-schedule mt-3" hidden>
    <h4><?php echo Text::_('COM_JEM_CUSTOM_DATES'); ?></h4>
    <p><?php echo Text::_('COM_JEM_CUSTOM_DATES_DESC'); ?></p>

    <?php if ($isExistingSeries) : ?>
        <div class="alert alert-info">
            <?php echo Text::sprintf('COM_JEM_CUSTOM_SERIES_MEMBER_NOTICE', (int) $this->item->series_id); ?>
        </div>
        <label for="custom_series_scope"><?php echo Text::_('COM_JEM_CUSTOM_SERIES_EDIT_SCOPE'); ?></label>
        <select name="custom_series_scope" id="custom_series_scope" class="form-select mb-3">
            <option value="occurrence"><?php echo Text::_('COM_JEM_CUSTOM_SERIES_SCOPE_OCCURRENCE'); ?></option>
            <option value="schedule"><?php echo Text::_('COM_JEM_CUSTOM_SERIES_SCOPE_SCHEDULE'); ?></option>
            <option value="all"><?php echo Text::_('COM_JEM_CUSTOM_SERIES_SCOPE_ALL'); ?></option>
        </select>
        <p class="form-text"><?php echo Text::_('COM_JEM_CUSTOM_SERIES_EDIT_SCOPE_DESC'); ?></p>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_JEM_STARTDATE'); ?></th>
                    <th><?php echo Text::_('COM_JEM_STARTTIME'); ?></th>
                    <th><?php echo Text::_('COM_JEM_ENDDATE'); ?></th>
                    <th><?php echo Text::_('COM_JEM_ENDTIME'); ?></th>
                    <th><?php echo Text::_('COM_JEM_ACTIONS'); ?></th>
                </tr>
            </thead>
            <tbody id="custom_schedule_rows"></tbody>
        </table>
    </div>
    <button type="button" id="custom_schedule_add" class="btn btn-outline-primary">
        <?php echo Text::_('COM_JEM_CUSTOM_DATES_ADD'); ?>
    </button>
    <input type="hidden" name="custom_schedule_json" id="custom_schedule_json"
           value="<?php echo htmlspecialchars($scheduleJson, ENT_QUOTES, 'UTF-8'); ?>">
</div>
<script>
window.jemCustomScheduleLabels = <?php echo json_encode(array(
    'duplicate'      => Text::_('COM_JEM_CUSTOM_DATES_DUPLICATE'),
    'remove'         => Text::_('COM_JEM_CUSTOM_DATES_REMOVE'),
    'cancelExisting' => Text::_('COM_JEM_CUSTOM_DATES_CANCEL_EXISTING'),
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
