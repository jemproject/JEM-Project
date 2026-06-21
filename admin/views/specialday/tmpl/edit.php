<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');

$createdBy = '';
$modifiedBy = '';

if (!empty($this->item->created_by)) {
    $createdBy = Factory::getUser((int) $this->item->created_by)->name;
}

if (!empty($this->item->modified_by)) {
    $modifiedBy = Factory::getUser((int) $this->item->modified_by)->name;
}

$weekdayOptions = array(
    0 => array('short' => Text::_('COM_JEM_SUNDAY_SHORT'), 'full' => Text::_('COM_JEM_SUNDAY')),
    1 => array('short' => Text::_('COM_JEM_MONDAY_SHORT'), 'full' => Text::_('COM_JEM_MONDAY')),
    2 => array('short' => Text::_('COM_JEM_TUESDAY_SHORT'), 'full' => Text::_('COM_JEM_TUESDAY')),
    3 => array('short' => Text::_('COM_JEM_WEDNESDAY_SHORT'), 'full' => Text::_('COM_JEM_WEDNESDAY')),
    4 => array('short' => Text::_('COM_JEM_THURSDAY_SHORT'), 'full' => Text::_('COM_JEM_THURSDAY')),
    5 => array('short' => Text::_('COM_JEM_FRIDAY_SHORT'), 'full' => Text::_('COM_JEM_FRIDAY')),
    6 => array('short' => Text::_('COM_JEM_SATURDAY_SHORT'), 'full' => Text::_('COM_JEM_SATURDAY')),
);
$selectedWeekdays = $this->form->getValue('weekdays');

if (!is_array($selectedWeekdays)) {
    $selectedWeekdays = trim((string) $selectedWeekdays) === '' ? array() : explode(',', (string) $selectedWeekdays);
}

$selectedWeekdays = array_map('strval', $selectedWeekdays);
$dayTypePreviewData = array();

foreach ((array) ($this->dayTypes ?? array()) as $typeName => $type) {
    $dayTypePreviewData[$typeName] = array(
        'name' => $type['name'] ?? $typeName,
        'color' => $type['color'] ?? '#d1d5db',
        'block_events' => !empty($type['block_events']),
    );
}
?>

<style>
    #adminForm.jem-specialday-form .jem-field-short {
        max-width: 14rem;
    }

    #adminForm.jem-specialday-form .jem-field-medium {
        max-width: 28rem;
    }

    #adminForm.jem-specialday-form .jem-field-long {
        max-width: 44rem;
    }

    #adminForm.jem-specialday-form .jem-field-date {
        max-width: 22rem;
    }

    #adminForm.jem-specialday-form .jem-field-textarea {
        max-width: none;
    }

    #adminForm.jem-specialday-form .jem-field-auto select,
    #adminForm.jem-specialday-form .jem-field-auto .form-select,
    #adminForm.jem-specialday-form .jem-field-auto joomla-field-fancy-select {
        width: auto;
        max-width: min(100%, 36rem);
    }

    #adminForm.jem-specialday-form .jem-field-textarea input,
    #adminForm.jem-specialday-form .jem-field-textarea textarea {
        width: 100%;
    }

    #adminForm.jem-specialday-form .jem-field-textarea textarea {
        min-height: 8rem;
    }

    #adminForm.jem-specialday-form .jem-weekday-toggle-group {
        display: inline-flex;
        flex-wrap: wrap;
        column-gap: 0;
        row-gap: 0;
    }

    #adminForm.jem-specialday-form .jem-weekdays-field > label,
    #adminForm.jem-specialday-form .jem-weekdays-field > .control-label {
        display: block;
        margin-bottom: .25rem;
    }

    #adminForm.jem-specialday-form .jem-weekday-toggle-group input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    #adminForm.jem-specialday-form .jem-weekday-toggle-group label {
        min-width: 3.2rem;
        padding: .35rem .55rem;
        border: 1px solid #adb5bd;
        border-radius: .25rem;
        background: #fff;
        color: #1d2b36;
        font-weight: 600;
        text-align: center;
        cursor: pointer;
        user-select: none;
    }

    #adminForm.jem-specialday-form .jem-weekday-toggle-group input:focus + label {
        box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .25);
    }

    #adminForm.jem-specialday-form .jem-weekday-toggle-group input:checked + label {
        border-color: #2f5f9c;
        background: #2f5f9c;
        color: #fff;
    }

    #adminForm.jem-specialday-form .jem-specialday-type-row {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: .75rem;
    }

    #adminForm.jem-specialday-form .jem-specialday-type-preview {
        min-width: 12rem;
        min-height: 2.55rem;
        padding: .45rem .75rem;
        border: 1px solid #adb5bd;
        border-radius: .25rem;
        color: #1d2b36;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var typeData = <?php echo json_encode($dayTypePreviewData); ?>;
    var typeSelect = document.getElementById('jform_day_type');
    var preview = document.getElementById('jem-specialday-type-preview');
    var blockText = <?php echo json_encode(Text::_('COM_JEM_SPECIAL_DAY_BLOCK_EVENTS')); ?>;
    var permitText = <?php echo json_encode(Text::_('COM_JEM_SPECIAL_DAY_PERMIT_EVENTS')); ?>;

    if (!typeSelect || !preview) {
        return;
    }

    var getContrastTextColor = function(color) {
        var hex = (color || '').replace('#', '').trim();
        var rgbMatch = (color || '').match(/\d+/g);
        var red;
        var green;
        var blue;

        if (hex.length === 3) {
            hex = hex.split('').map(function(value) {
                return value + value;
            }).join('');
        }

        if (/^[0-9a-f]{6}$/i.test(hex)) {
            red = parseInt(hex.substring(0, 2), 16);
            green = parseInt(hex.substring(2, 4), 16);
            blue = parseInt(hex.substring(4, 6), 16);
        } else if (rgbMatch && rgbMatch.length >= 3) {
            red = parseInt(rgbMatch[0], 10);
            green = parseInt(rgbMatch[1], 10);
            blue = parseInt(rgbMatch[2], 10);
        } else {
            return '#1d2b36';
        }

        return ((red * 299 + green * 587 + blue * 114) / 1000) >= 150 ? '#1d2b36' : '#ffffff';
    };

    var updatePreview = function() {
        var value = typeSelect.value || '';
        var data = typeData[value] || null;
        var backgroundColor;

        if (!data) {
            preview.style.backgroundColor = '#f8f9fa';
            preview.style.color = '#1d2b36';
            preview.style.display = 'none';
            preview.textContent = '';
            return;
        }

        backgroundColor = data.color || '#d1d5db';
        preview.style.backgroundColor = backgroundColor;
        preview.style.color = getContrastTextColor(backgroundColor);
        preview.style.display = 'inline-flex';
        preview.textContent = data.block_events ? blockText : permitText;
    };

    typeSelect.addEventListener('change', updatePreview);
    updatePreview();
});
</script>

<form action="<?php echo Route::_('index.php?option=com_jem&layout=edit&id=' . (int) $this->item->id); ?>"
      method="post" name="adminForm" id="adminForm" class="form-validate jem-specialday-form">

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h3 mb-3">
                        <?php echo empty($this->item->id) ? Text::_('COM_JEM_SPECIAL_DAY_ADD') : Text::_('COM_JEM_SPECIAL_DAY_EDIT'); ?>
                    </h2>

                    <div class="row">
                        <div class="col-md-7 mb-3 jem-field-long">
                            <?php echo $this->form->getLabel('title'); ?>
                            <?php echo $this->form->getInput('title'); ?>
                        </div>
                        <div class="col-md-5 mb-3 jem-field-medium">
                            <?php echo $this->form->getLabel('alias'); ?>
                            <?php echo $this->form->getInput('alias'); ?>
                        </div>
                    </div>

                    <div class="mb-3 jem-field-auto">
                        <?php echo $this->form->getLabel('day_type'); ?>
                        <div class="jem-specialday-type-row">
                            <?php echo $this->form->getInput('day_type'); ?>
                            <div id="jem-specialday-type-preview" class="jem-specialday-type-preview" style="display: none;" aria-live="polite"></div>
                        </div>
                    </div>

                    <div class="mb-3 jem-field-textarea">
                        <?php echo $this->form->getLabel('description'); ?>
                        <?php echo $this->form->getInput('description'); ?>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <?php echo Text::_('COM_JEM_SPECIAL_DAY_RULE'); ?>
                </div>
                <div class="card-body">
                    <div class="row align-items-start">
                        <div class="col-lg-3 col-md-6 mb-3 jem-field-date">
                            <?php echo $this->form->getLabel('start_date'); ?>
                            <?php echo $this->form->getInput('start_date'); ?>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3 jem-field-date">
                            <?php echo $this->form->getLabel('end_date'); ?>
                            <?php echo $this->form->getInput('end_date'); ?>
                        </div>
                        <div class="col-lg-6 col-md-12 mb-3 jem-field-auto jem-weekdays-field">
                            <?php echo $this->form->getLabel('weekdays'); ?>
                            <div class="jem-weekday-toggle-group" role="group" aria-label="<?php echo Text::_('COM_JEM_SPECIAL_DAY_FIELD_WEEKDAYS'); ?>">
                                <?php foreach ($weekdayOptions as $weekday => $weekdayOption) : ?>
                                    <?php $weekdayId = 'jform_weekdays_' . (int) $weekday; ?>
                                    <input type="checkbox"
                                           name="jform[weekdays][]"
                                           id="<?php echo $weekdayId; ?>"
                                           value="<?php echo (int) $weekday; ?>"
                                           <?php echo in_array((string) $weekday, $selectedWeekdays, true) ? 'checked' : ''; ?>>
                                    <label for="<?php echo $weekdayId; ?>" title="<?php echo htmlspecialchars($weekdayOption['full'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($weekdayOption['short'], ENT_QUOTES, 'UTF-8'); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <?php echo Text::_('COM_JEM_SPECIAL_DAY_LOCATION'); ?>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3 jem-field-auto">
                            <?php echo $this->form->getLabel('country'); ?>
                            <?php echo $this->form->getInput('country'); ?>
                        </div>
                        <div class="col-md-4 mb-3 jem-field-medium">
                            <?php echo $this->form->getLabel('region'); ?>
                            <?php echo $this->form->getInput('region'); ?>
                        </div>
                        <div class="col-md-4 mb-3 jem-field-medium">
                            <?php echo $this->form->getLabel('city'); ?>
                            <?php echo $this->form->getInput('city'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header">
                    <?php echo Text::_('JDETAILS'); ?>
                </div>
                <div class="card-body">
                    <div class="mb-3 jem-field-auto">
                        <?php echo $this->form->getLabel('published'); ?>
                        <?php echo $this->form->getInput('published'); ?>
                    </div>
                    <div class="mb-3 jem-field-short">
                        <?php echo $this->form->getLabel('ordering'); ?>
                        <?php echo $this->form->getInput('ordering'); ?>
                    </div>

                    <?php if (!empty($this->item->id)) : ?>
                        <hr>
                        <dl class="row mb-0">
                            <dt class="col-sm-5"><?php echo Text::_('COM_JEM_SPECIAL_DAY_CREATED'); ?></dt>
                            <dd class="col-sm-7">
                                <?php echo !empty($this->item->created) ? HTMLHelper::_('date', $this->item->created, Text::_('DATE_FORMAT_LC6')) : '-'; ?>
                            </dd>

                            <dt class="col-sm-5"><?php echo Text::_('COM_JEM_SPECIAL_DAY_CREATED_BY'); ?></dt>
                            <dd class="col-sm-7">
                                <?php echo $createdBy !== '' ? htmlspecialchars($createdBy, ENT_QUOTES, 'UTF-8') : '-'; ?>
                            </dd>

                            <dt class="col-sm-5"><?php echo Text::_('COM_JEM_SPECIAL_DAY_MODIFIED'); ?></dt>
                            <dd class="col-sm-7">
                                <?php echo !empty($this->item->modified) ? HTMLHelper::_('date', $this->item->modified, Text::_('DATE_FORMAT_LC6')) : '-'; ?>
                            </dd>

                            <dt class="col-sm-5"><?php echo Text::_('COM_JEM_SPECIAL_DAY_MODIFIED_BY'); ?></dt>
                            <dd class="col-sm-7">
                                <?php echo $modifiedBy !== '' ? htmlspecialchars($modifiedBy, ENT_QUOTES, 'UTF-8') : '-'; ?>
                            </dd>
                        </dl>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php echo $this->form->getInput('id'); ?>
    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
