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

$return = $this->return_page ?: Route::_('index.php?option=com_jem&view=specialdays', false);
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
$contentColumnClass = empty($this->item->id) ? 'col-md-12' : 'col-md-8';
$renderLabel = function ($fieldName) {
    $label = $this->form->getLabel($fieldName);

    return preg_replace(
        '/\s(?:title|data-bs-original-title|data-original-title)="[^"]*Please fill in this field[^"]*"/i',
        '',
        $label
    );
};
$renderFullWidthInput = function ($fieldName) {
    $input = $this->form->getInput($fieldName);

    return preg_replace(
        '/(<(?:input|textarea)\b[^>]*\bid="jform_' . preg_quote($fieldName, '/') . '"[^>]*)(>)/i',
        '$1 style="width: 100%; max-width: none;"$2',
        $input,
        1
    );
};

foreach ((array) ($this->dayTypes ?? array()) as $typeName => $type) {
    $dayTypePreviewData[$typeName] = array(
        'name' => $type['name'] ?? $typeName,
        'color' => $type['color'] ?? '#d1d5db',
        'block_events' => !empty($type['block_events']),
    );
}
?>

<script>
Joomla.submitbutton = function(task) {
    if (task === 'specialday.cancel' || document.formvalidator.isValid(document.getElementById('specialday-form'))) {
        Joomla.submitform(task, document.getElementById('specialday-form'));
    }
};

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
        preview.style.alignItems = 'center';
        preview.style.justifyContent = 'center';
        preview.style.height = typeSelect.offsetHeight ? typeSelect.offsetHeight + 'px' : '2.55rem';
        preview.style.width = 'auto';
        preview.style.minWidth = '0';
        preview.style.maxWidth = 'max-content';
        preview.style.whiteSpace = 'nowrap';
        preview.textContent = data.block_events ? blockText : permitText;
    };

    typeSelect.addEventListener('change', updatePreview);
    updatePreview();

    document.querySelectorAll('.jem-weekday-toggle-group input[type="checkbox"]').forEach(function(input) {
        var label = document.querySelector('label[for="' + input.id + '"]');

        if (!label) {
            return;
        }

        var updateWeekdayLabel = function() {
            label.style.borderColor = input.checked ? '#2f5f9c' : '#adb5bd';
            label.style.backgroundColor = input.checked ? '#2f5f9c' : '#fff';
            label.style.color = input.checked ? '#fff' : '#1d2b36';
        };

        input.addEventListener('change', updateWeekdayLabel);
        updateWeekdayLabel();
    });
});
</script>

<div id="jem" class="jem_specialday_edit">
    <?php if ($this->params->get('show_page_heading', 1)) : ?>
        <h1 class="componentheading"><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
    <?php endif; ?>

    <form action="<?php echo Route::_('index.php?option=com_jem&view=specialday&layout=edit&id=' . (int) $this->item->id); ?>"
          method="post" name="adminForm" id="specialday-form" class="form-validate jem-specialday-form">

        <div class="jem-specialday-toolbar mb-3">
            <div class="jem-specialday-toolbar-title">
                <h2>
                    <?php echo empty($this->item->id) ? Text::_('COM_JEM_SPECIAL_DAY_ADD') : Text::_('COM_JEM_SPECIAL_DAY_EDIT'); ?>
                </h2>
            </div>
        </div>

        <div class="row">
            <div class="<?php echo $contentColumnClass; ?>">
                <div class="card jem-specialday-card mb-3">
                    <div class="card-body">
                        <h2 class="h3 mb-3"><?php echo Text::_('JDETAILS'); ?></h2>

                        <div class="row">
                            <div class="col-md-6 mb-3 jem-field-fluid">
                                <?php echo $renderLabel('title'); ?>
                                <?php echo $renderFullWidthInput('title'); ?>
                            </div>
                            <div class="col-md-6 mb-3 jem-field-fluid">
                                <?php echo $renderLabel('alias'); ?>
                                <?php echo $renderFullWidthInput('alias'); ?>
                            </div>
                        </div>

                        <div class="mb-3 jem-field-fluid">
                            <?php echo $renderLabel('description'); ?>
                            <?php echo $renderFullWidthInput('description'); ?>
                        </div>

                        <div class="mb-3 jem-field-auto">
                            <?php echo $renderLabel('day_type'); ?>
                            <div class="jem-specialday-type-row" style="display: flex; flex-wrap: nowrap; align-items: center; gap: .75rem; width: max-content; max-width: 100%;">
                                <?php echo $this->form->getInput('day_type'); ?>
                                <span id="jem-specialday-type-preview"
                                      class="jem-specialday-type-preview"
                                      style="display: none; flex: 0 0 auto; width: auto; min-width: 0; max-width: max-content; white-space: nowrap; padding: .45rem .75rem; border: 1px solid #adb5bd; border-radius: .25rem; font-weight: 600; line-height: 1;"
                                      aria-live="polite"></span>
                            </div>
                        </div>

                        <div class="mb-3 jem-field-auto">
                            <?php echo $renderLabel('published'); ?>
                            <?php echo $this->form->getInput('published'); ?>
                        </div>
                        <div class="mb-3 jem-field-auto">
                            <?php echo $renderLabel('access'); ?>
                            <?php echo $this->form->getInput('access'); ?>
                        </div>
                        <div class="mb-3 jem-field-auto">
                            <?php echo $renderLabel('show_dates'); ?>
                            <?php echo $this->form->getInput('show_dates'); ?>
                        </div>
                    </div>
                </div>

                <div class="card jem-specialday-card mb-3">
                    <div class="card-header">
                        <?php echo Text::_('COM_JEM_SPECIAL_DAY_RULE'); ?>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-start jem-specialday-rule-row">
                            <div class="col-md-4 mb-3 jem-field-date">
                                <?php echo $renderLabel('start_date'); ?>
                                <?php echo $this->form->getInput('start_date'); ?>
                            </div>
                            <div class="col-md-4 mb-3 jem-field-date">
                                <?php echo $renderLabel('end_date'); ?>
                                <?php echo $this->form->getInput('end_date'); ?>
                            </div>
                            <div class="col-md-4 mb-3 jem-field-auto jem-weekdays-field" style="display: flex; flex-direction: column; justify-content: flex-start;">
                                <div class="jem-weekdays-label" style="display: block; margin-bottom: .25rem;">
                                    <?php echo $renderLabel('weekdays'); ?>
                                </div>
                                <div class="jem-weekday-toggle-group"
                                     style="display: inline-flex; align-items: center; min-height: 2.75rem;"
                                     role="group"
                                     aria-label="<?php echo Text::_('COM_JEM_SPECIAL_DAY_FIELD_WEEKDAYS'); ?>">
                                    <?php foreach ($weekdayOptions as $weekday => $weekdayOption) : ?>
                                        <?php $weekdayId = 'jform_weekdays_' . (int) $weekday; ?>
                                        <?php $weekdayChecked = in_array((string) $weekday, $selectedWeekdays, true); ?>
                                        <input type="checkbox"
                                               name="jform[weekdays][]"
                                               id="<?php echo $weekdayId; ?>"
                                               value="<?php echo (int) $weekday; ?>"
                                               style="position: absolute; opacity: 0; pointer-events: none;"
                                               <?php echo $weekdayChecked ? 'checked' : ''; ?>>
                                        <label for="<?php echo $weekdayId; ?>"
                                               title="<?php echo htmlspecialchars($weekdayOption['full'], ENT_QUOTES, 'UTF-8'); ?>"
                                               style="display: inline-flex; align-items: center; justify-content: center; min-width: 3.2rem; padding: .45rem .65rem; border: 1px solid <?php echo $weekdayChecked ? '#2f5f9c' : '#adb5bd'; ?>; border-radius: .25rem; background: <?php echo $weekdayChecked ? '#2f5f9c' : '#fff'; ?>; color: <?php echo $weekdayChecked ? '#fff' : '#1d2b36'; ?>; font-weight: 600; text-align: center; cursor: pointer; user-select: none;">
                                            <?php echo htmlspecialchars($weekdayOption['short'], ENT_QUOTES, 'UTF-8'); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card jem-specialday-card mb-3">
                    <div class="card-header">
                        <?php echo Text::_('COM_JEM_SPECIAL_DAY_LOCATION'); ?>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3 jem-field-auto">
                                <?php echo $renderLabel('country'); ?>
                                <?php echo $this->form->getInput('country'); ?>
                            </div>
                            <div class="col-md-4 mb-3 jem-field-fluid">
                                <?php echo $renderLabel('region'); ?>
                                <?php echo $renderFullWidthInput('region'); ?>
                            </div>
                            <div class="col-md-4 mb-3 jem-field-fluid">
                                <?php echo $renderLabel('city'); ?>
                                <?php echo $renderFullWidthInput('city'); ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <?php if (!empty($this->item->id)) : ?>
                <div class="col-md-4">
                    <div class="card jem-specialday-card mb-3">
                        <div class="card-header">
                            <?php echo Text::_('JDETAILS'); ?>
                        </div>
                        <div class="card-body">
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
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="buttons jem-buttons jem-specialday-actions mt-3">
            <button type="button" class="btn btn-primary" onclick="Joomla.submitbutton('specialday.save')">
                <?php echo Text::_('JSAVE'); ?>
            </button>
            <button type="button" class="btn btn-secondary" onclick="Joomla.submitbutton('specialday.cancel')">
                <?php echo Text::_('JCANCEL'); ?>
            </button>
        </div>

        <?php echo $this->form->getInput('id'); ?>
        <input type="hidden" name="return" value="<?php echo base64_encode($return); ?>">
        <input type="hidden" name="task" value="">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
