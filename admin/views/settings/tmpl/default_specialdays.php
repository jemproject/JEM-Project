<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';

$group = 'globalattribs';
$deleteText = htmlspecialchars(Text::_('JACTION_DELETE'), ENT_QUOTES, 'UTF-8');
$yesText = htmlspecialchars(Text::_('JYES'), ENT_QUOTES, 'UTF-8');
$noText = htmlspecialchars(Text::_('JNO'), ENT_QUOTES, 'UTF-8');
$removeUsedText = htmlspecialchars(Text::_('COM_JEM_SETTINGS_CALENDAR_SPECIAL_DAY_TYPE_REMOVE_USED_CONFIRM'), ENT_QUOTES, 'UTF-8');
$rows = array_values(JemHelper::calendarSpecialDayTypes());
$usedDayTypes = array();

try {
    $db = Factory::getContainer()->get('DatabaseDriver');
    $query = $db->getQuery(true)
        ->select(array($db->quoteName('day_type'), 'COUNT(*) AS ' . $db->quoteName('total')))
        ->from($db->quoteName('#__jem_special_days'))
        ->where($db->quoteName('day_type') . ' <> ' . $db->quote(''))
        ->group($db->quoteName('day_type'));
    $db->setQuery($query);
    $usedDayTypes = array_map('intval', $db->loadAssocList('day_type', 'total') ?: array());
} catch (Exception $e) {
    $usedDayTypes = array();
}

if (empty($rows)) {
    $rows[] = array('id' => 0, 'name' => 'Weekend', 'color' => '#d1d5db', 'block_events' => 0);
}
?>
<div class="width-100" style="padding: 10px 1vw;">
    <div class="jem-special-days-settings">
        <ul class="adminformlist">
            <li><div class="label-form"><?php echo $this->form->renderfield('calendar_special_days_enabled', $group); ?></div></li>
        </ul>
        <div style="clear: both;"></div>

        <div class="jem-special-day-types-hidden">
            <?php echo $this->form->getInput('calendar_special_day_types', $group); ?>
        </div>

        <div class="jem-special-day-types-editor" style="clear: both; margin-top: 1.5rem;">
            <p class="form-text">
                <?php echo Text::_('COM_JEM_SETTINGS_CALENDAR_SPECIAL_DAY_TYPES_DESC'); ?>
            </p>

            <div class="table-responsive">
                <table class="table table-striped align-middle w-auto" id="jem-special-day-types-table">
                    <thead>
                        <tr>
                            <th style="width:7rem" class="text-center"><?php echo Text::_('COM_JEM_SETTINGS_CALENDAR_SPECIAL_DAY_TYPE_PRIORITY'); ?></th>
                            <th style="width:16rem"><?php echo Text::_('COM_JEM_SETTINGS_CALENDAR_SPECIAL_DAY_TYPE_NAME'); ?></th>
                            <th style="width:12rem"><?php echo Text::_('COM_JEM_SETTINGS_CALENDAR_SPECIAL_DAY_TYPE_COLOR'); ?></th>
                            <th style="width:12rem"><?php echo Text::_('COM_JEM_SETTINGS_CALENDAR_SPECIAL_DAY_TYPE_BLOCK_EVENTS'); ?></th>
                            <th style="width:4rem" class="text-center"><?php echo Text::_('JACTION_DELETE'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $position => $row) : ?>
                            <tr draggable="true">
                                <td class="text-center jem-special-day-type-order">
                                    <span class="jem-special-day-type-drag" aria-hidden="true">::</span>
                                    <span class="jem-special-day-type-position"><?php echo (int) ($position + 1); ?></span>
                                </td>
                                <td>
                                    <input type="hidden" name="special_day_type_id[]" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                                    <input type="hidden" name="special_day_type_original_name[]" value="<?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="text" name="special_day_type_name[]" class="form-control jem-special-day-type-name" value="<?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="color" class="form-control form-control-color jem-special-day-type-color-picker"
                                               style="width:4.5rem;height:2.5rem;padding:.25rem;"
                                               value="<?php echo htmlspecialchars($row['color'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="text" name="special_day_type_color[]" class="form-control jem-special-day-type-color-code"
                                               style="width:7.5rem;"
                                               maxlength="7"
                                               pattern="#[0-9a-fA-F]{6}"
                                               value="<?php echo htmlspecialchars($row['color'], ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                </td>
                                <td>
                                    <select name="special_day_type_block_events[]" class="form-select jem-special-day-type-block-events">
                                        <option value="0" <?php echo $row['block_events'] ? '' : 'selected'; ?>><?php echo Text::_('JNO'); ?></option>
                                        <option value="1" <?php echo $row['block_events'] ? 'selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
                                    </select>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger jem-special-day-type-remove"
                                            data-day-type="<?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-used-count="<?php echo (int) ($usedDayTypes[$row['name']] ?? 0); ?>"
                                            title="<?php echo $deleteText; ?>">
                                        <span class="icon-trash" aria-hidden="true"></span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button type="button" class="btn btn-primary" id="jem-special-day-type-add">
                <span class="icon-plus" aria-hidden="true"></span>
                <?php echo Text::_('COM_JEM_SETTINGS_CALENDAR_SPECIAL_DAY_TYPE_ADD'); ?>
            </button>
        </div>
    </div>
</div>

<style>
    #jem-special-day-types-table .jem-special-day-type-order {
        cursor: grab;
        user-select: none;
        white-space: nowrap;
    }

    #jem-special-day-types-table tr.is-dragging {
        opacity: .55;
    }

    #jem-special-day-types-table .jem-special-day-type-drag {
        color: #6c757d;
        display: inline-block;
        font-weight: 700;
        letter-spacing: 1px;
        margin-right: .35rem;
        transform: rotate(90deg);
    }

    #jem-special-day-types-table .jem-special-day-type-position {
        display: inline-block;
        font-weight: 700;
        min-width: 1.35rem;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('jem-special-day-types-table');
    const addButton = document.getElementById('jem-special-day-type-add');
    const settingsForm = document.getElementById('settings-form') || document.adminForm;
    const removeUsedMessage = '<?php echo $removeUsedText; ?>';

    if (!table || !addButton) {
        return;
    }

    const getHiddenField = function () {
        return document.getElementById('jform_globalattribs_calendar_special_day_types')
            || document.querySelector('.jem-special-day-types-hidden [name="jform[globalattribs][calendar_special_day_types]"]')
            || document.querySelector('[name="jform[globalattribs][calendar_special_day_types]"]')
            || document.querySelector('[name$="[calendar_special_day_types]"]');
    };

    const syncTypes = function (normaliseColors) {
        const hidden = getHiddenField();
        const lines = [];

        table.querySelectorAll('tbody tr').forEach(function (row) {
            const name = row.querySelector('.jem-special-day-type-name').value.trim();
            const colorInput = row.querySelector('.jem-special-day-type-color-code');
            const picker = row.querySelector('.jem-special-day-type-color-picker');
            let color = colorInput ? colorInput.value.trim() : '';

            if (!/^#[0-9a-fA-F]{6}$/.test(color)) {
                color = picker ? picker.value.trim() : '#d1d5db';
            }

            color = /^#[0-9a-fA-F]{6}$/.test(color) ? color.toLowerCase() : '#d1d5db';
            const blockEvents = row.querySelector('.jem-special-day-type-block-events').value === '1' ? '1' : '0';

            if (normaliseColors && colorInput && colorInput.value !== color) {
                colorInput.value = color;
            }

            if (normaliseColors && picker && picker.value.toLowerCase() !== color) {
                picker.value = color;
            }

            if (name !== '') {
                lines.push(name + ' | ' + color + ' | ' + blockEvents);
            }
        });

        if (hidden) {
            hidden.value = lines.join("\n");
        }
    };

    const updateSpecialDayTypeOrder = function () {
        table.querySelectorAll('tbody tr').forEach(function (row, index) {
            const positionLabel = row.querySelector('.jem-special-day-type-position');

            if (positionLabel) {
                positionLabel.textContent = index + 1;
            }
        });

        syncTypes(true);
    };

    const createRow = function () {
        const row = document.createElement('tr');
        row.draggable = true;
        row.innerHTML = ''
            + '<td class="text-center jem-special-day-type-order"><span class="jem-special-day-type-drag" aria-hidden="true">::</span><span class="jem-special-day-type-position"></span></td>'
            + '<td><input type="hidden" name="special_day_type_id[]" value="0"><input type="hidden" name="special_day_type_original_name[]" value=""><input type="text" name="special_day_type_name[]" class="form-control jem-special-day-type-name" value=""></td>'
            + '<td><div class="d-flex align-items-center gap-2"><input type="color" class="form-control form-control-color jem-special-day-type-color-picker" style="width:4.5rem;height:2.5rem;padding:.25rem;" value="#d1d5db"><input type="text" name="special_day_type_color[]" class="form-control jem-special-day-type-color-code" style="width:7.5rem;" maxlength="7" pattern="#[0-9a-fA-F]{6}" value="#d1d5db"></div></td>'
            + '<td><select name="special_day_type_block_events[]" class="form-select jem-special-day-type-block-events"><option value="0"><?php echo $noText; ?></option><option value="1"><?php echo $yesText; ?></option></select></td>'
            + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger jem-special-day-type-remove" data-day-type="" data-used-count="0" title="<?php echo $deleteText; ?>"><span class="icon-trash" aria-hidden="true"></span></button></td>';
        table.querySelector('tbody').appendChild(row);
        row.querySelector('.jem-special-day-type-name').focus();
        updateSpecialDayTypeOrder();
    };

    addButton.addEventListener('click', createRow);
    table.addEventListener('input', function (event) {
        const row = event.target.closest('tr');

        if (event.target.classList.contains('jem-special-day-type-color-picker') && row) {
            const colorInput = row.querySelector('.jem-special-day-type-color-code');
            if (colorInput) {
                colorInput.value = event.target.value.toLowerCase();
            }
        }

        if (event.target.classList.contains('jem-special-day-type-color-code') && row) {
            const picker = row.querySelector('.jem-special-day-type-color-picker');
            const color = event.target.value.trim();
            if (picker && /^#[0-9a-fA-F]{6}$/.test(color)) {
                picker.value = color.toLowerCase();
            }
        }

        syncTypes(false);
    });
    table.addEventListener('change', function () {
        syncTypes(true);
    });
    let draggedRow = null;
    const body = table.querySelector('tbody');

    body.addEventListener('dragstart', function (event) {
        draggedRow = event.target.closest('tr');

        if (!draggedRow) {
            return;
        }

        draggedRow.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', draggedRow.rowIndex);
    });

    body.addEventListener('dragover', function (event) {
        const targetRow = event.target.closest('tr');

        if (!draggedRow || !targetRow || targetRow === draggedRow) {
            return;
        }

        event.preventDefault();

        const bounds = targetRow.getBoundingClientRect();
        const before = event.clientY < bounds.top + bounds.height / 2;
        targetRow.parentNode.insertBefore(draggedRow, before ? targetRow : targetRow.nextSibling);
    });

    body.addEventListener('drop', function (event) {
        event.preventDefault();
    });

    body.addEventListener('dragend', function () {
        if (!draggedRow) {
            return;
        }

        draggedRow.classList.remove('is-dragging');
        draggedRow = null;
        updateSpecialDayTypeOrder();
    });

    table.addEventListener('click', function (event) {
        const removeButton = event.target.closest('.jem-special-day-type-remove');

        if (!removeButton) {
            return;
        }

        const row = removeButton.closest('tr');
        const nameInput = row.querySelector('.jem-special-day-type-name');
        const dayType = removeButton.dataset.dayType || (nameInput ? nameInput.value.trim() : '');
        const usedCount = parseInt(removeButton.dataset.usedCount || '0', 10);

        if (usedCount > 0 && !window.confirm(removeUsedMessage.replace('%s', dayType).replace('%d', usedCount))) {
            return;
        }

        row.remove();
        updateSpecialDayTypeOrder();
    });

    if (settingsForm) {
        settingsForm.addEventListener('submit', function () {
            syncTypes(true);
        });
    }

    if (window.Joomla && typeof window.Joomla.submitbutton === 'function') {
        const originalSubmitbutton = window.Joomla.submitbutton;

        window.Joomla.submitbutton = function (task) {
            syncTypes(true);
            originalSubmitbutton.call(this, task);
        };
    }
});
</script>
