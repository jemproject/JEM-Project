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
use Joomla\CMS\Session\Session;

$user = JemFactory::getUser();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn = $this->escape($this->state->get('list.direction'));
$canEdit = $user->authorise('core.edit', 'com_jem');
$canEditState = $user->authorise('core.edit.state', 'com_jem');
$saveOrder = $canEditState && $listOrder === 'a.ordering' && strtolower($listDirn) === 'asc';
$saveOrderingUrl = Route::_('index.php?option=com_jem&task=specialdays.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1', false);

$weekdayLabels = array(
    0 => Text::_('SUN'),
    1 => Text::_('MON'),
    2 => Text::_('TUE'),
    3 => Text::_('WED'),
    4 => Text::_('THU'),
    5 => Text::_('FRI'),
    6 => Text::_('SAT'),
);

$renderRule = static function ($item) use ($weekdayLabels) {
    $parts = array();
    $weekdays = array_filter(array_map('trim', explode(',', (string) ($item->weekdays ?? ''))), 'strlen');

    if ($weekdays) {
        $labels = array();
        foreach ($weekdays as $weekday) {
            $weekday = (int) $weekday;
            if (isset($weekdayLabels[$weekday])) {
                $labels[] = $weekdayLabels[$weekday];
            }
        }
        $parts[] = Text::_('COM_JEM_SPECIAL_DAY_WEEKDAYS') . ': ' . implode(', ', $labels);
    }

    if (!empty($item->start_date) && $item->start_date !== '0000-00-00') {
        $range = HTMLHelper::_('date', $item->start_date, Text::_('DATE_FORMAT_LC4'));
        if (!empty($item->end_date) && $item->end_date !== '0000-00-00') {
            $range .= ' - ' . HTMLHelper::_('date', $item->end_date, Text::_('DATE_FORMAT_LC4'));
        }
        $parts[] = $range;
    }

    return $parts ? implode('<br>', $parts) : Text::_('COM_JEM_SPECIAL_DAY_RULE_NOT_SET');
};

$renderLocation = function ($item) {
    $country = trim((string) ($item->country ?? ''));
    $region = trim((string) ($item->region ?? ''));
    $city = trim((string) ($item->city ?? ''));
    $parts = array_filter(array($country, $region, $city), 'strlen');

    if (!$parts) {
        return '<span class="text-muted">-</span>';
    }

    if (preg_match('/^[A-Za-z]{2}$/', $country)) {
        $countryCode = strtoupper($country);
        $flag = '&#' . (127397 + ord($countryCode[0])) . ';&#' . (127397 + ord($countryCode[1])) . ';';
        $parts[0] = '<span class="jem-specialdays-location-country">'
            . '<span class="jem-specialdays-location-flag" aria-hidden="true">' . $flag . '</span>'
            . '<span>' . $this->escape($countryCode) . '</span>'
            . '</span>';
    } else {
        $parts[0] = $this->escape($country);
    }

    foreach ($parts as $key => $part) {
        if ($key === 0) {
            continue;
        }

        $parts[$key] = $this->escape($part);
    }

    return implode(', ', $parts);
};
?>

<style>
    #specialDayList .jem-specialdays-order {
        cursor: grab;
        text-align: center;
        user-select: none;
        white-space: nowrap;
        width: 5rem;
    }

    #specialDayList tr.is-dragging {
        opacity: .55;
    }

    #specialDayList .jem-specialdays-drag {
        color: #6c757d;
        display: inline-block;
        font-weight: 700;
        letter-spacing: 1px;
        margin-right: .35rem;
        transform: rotate(90deg);
    }

    #specialDayList .jem-specialdays-position {
        display: inline-block;
        font-weight: 700;
        min-width: 1.35rem;
    }

    #specialDayList .jem-specialdays-order.is-disabled {
        cursor: default;
        opacity: .55;
    }

    #specialDayList > tbody > tr:nth-of-type(odd) > * {
        --bs-table-accent-bg: transparent;
        background-color: var(--bs-table-bg, #fff);
    }

    #specialDayList > tbody > tr:nth-of-type(even) > * {
        --bs-table-accent-bg: rgba(0, 0, 0, .03);
        background-color: var(--bs-table-striped-bg, rgba(0, 0, 0, .03));
    }
</style>

<form action="<?php echo Route::_('index.php?option=com_jem&view=specialdays'); ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">
    <div id="j-main-container" class="j-main-container">
        <fieldset id="filter-bar" class="mb-3">
            <div class="jem-admin-filter-bar jem-specialdays-admin-filter-bar">
                <div class="jem-admin-filter-search">
                    <div class="input-group">
                        <input type="text" name="filter_search" id="filter_search" class="form-control"
                               placeholder="<?php echo Text::_('COM_JEM_SEARCH'); ?>"
                               value="<?php echo $this->escape($this->state->get('filter.search')); ?>"
                               onchange="document.adminForm.submit();" />
                        <button type="submit" class="btn btn-primary">
                            <span class="icon-search" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="btn btn-primary"
                                onclick="document.getElementById('filter_search').value='';this.form.filter_state.value='';this.form.filter_day_type.value='';if(this.form.filter_year.options.length){this.form.filter_year.selectedIndex=0;}this.form.submit();">
                            <?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>
                        </button>
                    </div>
                </div>
                <div class="jem-admin-filter-item">
                    <label for="filter_year" class="visually-hidden"><?php echo Text::_('COM_JEM_SPECIAL_DAY_FILTER_YEAR'); ?></label>
                    <select name="filter_year" id="filter_year" class="form-select" onchange="this.form.submit()">
                        <?php foreach ((array) $this->years as $year) : ?>
                            <option value="<?php echo (int) $year; ?>"<?php echo (int) $this->state->get('filter.year') === (int) $year ? ' selected' : ''; ?>>
                                <?php echo (int) $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="jem-admin-filter-item">
                    <select name="filter_state" class="form-select" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?></option>
                        <?php echo HTMLHelper::_('select.options', HTMLHelper::_('jgrid.publishedOptions', array('all' => true)), 'value', 'text', $this->state->get('filter.state'), true); ?>
                    </select>
                </div>
                <div class="jem-admin-filter-item">
                    <select name="filter_day_type" class="form-select" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('COM_JEM_SPECIAL_DAY_FILTER_TYPE'); ?></option>
                        <?php foreach ($this->dayTypes as $type) : ?>
                            <?php $typeValue = !empty($type['id']) ? (string) (int) $type['id'] : (string) $type['name']; ?>
                            <option value="<?php echo $this->escape($typeValue); ?>"<?php echo (string) $this->state->get('filter.day_type') === $typeValue ? ' selected' : ''; ?>>
                                <?php echo $this->escape($type['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="jem-admin-filter-limit">
                    <?php echo $this->pagination->getLimitBox(); ?>
                </div>
            </div>
        </fieldset>

        <table class="table table-striped" id="specialDayList">
            <thead>
                <tr>
                    <th style="width:5rem" class="center">
                        <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ORDERING', 'a.ordering', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:1%" class="center">
                        <input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
                    </th>
                    <th>
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_SPECIAL_DAY_FIELD_TITLE', 'a.title', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:12%">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_SPECIAL_DAY_FIELD_TYPE', 'a.day_type_id', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:22%">
                        <?php echo Text::_('COM_JEM_SPECIAL_DAY_RULE'); ?>
                    </th>
                    <th style="width:16%" class="center jem-specialdays-location-heading">
                        <?php echo Text::_('COM_JEM_SPECIAL_DAY_LOCATION'); ?>
                    </th>
                    <th style="width:8%" class="center">
                        <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ACCESS', 'access_level', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:8%" class="center">
                        <?php echo HTMLHelper::_('grid.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:5%" class="center">
                        <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                    </th>
                </tr>
            </thead>
            <tbody data-save-order="<?php echo $saveOrder ? '1' : '0'; ?>" data-save-url="<?php echo $this->escape($saveOrderingUrl); ?>">
            <?php foreach ($this->items as $i => $item) : ?>
                <?php
                $editUrl = Route::_('index.php?option=com_jem&task=specialday.edit&id=' . (int) $item->id);
                $dayType = $item->day_type ?? '';
                $type = !empty($item->day_type_id) && isset($this->dayTypesById[(int) $item->day_type_id])
                    ? $this->dayTypesById[(int) $item->day_type_id]
                    : ($this->dayTypes[$dayType] ?? array('name' => $dayType, 'color' => '#d1d5db'));
                $position = (int) ($i + 1);
                ?>
                <tr class="row<?php echo $i % 2; ?>" draggable="<?php echo $saveOrder ? 'true' : 'false'; ?>" data-id="<?php echo (int) $item->id; ?>">
                    <td class="jem-specialdays-order<?php echo $saveOrder ? '' : ' is-disabled'; ?>" title="<?php echo $saveOrder ? Text::_('JGRID_HEADING_ORDERING') : Text::_('JORDERINGDISABLED'); ?>">
                        <span class="jem-specialdays-drag" aria-hidden="true">::</span>
                        <span class="jem-specialdays-position"><?php echo $position; ?></span>
                        <input type="hidden" name="order[]" class="jem-specialdays-order-input" value="<?php echo $position; ?>">
                    </td>
                    <td class="center">
                        <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                    </td>
                    <td>
                        <?php if ($canEdit) : ?>
                            <a href="<?php echo $editUrl; ?>"><?php echo $this->escape($item->title ?? ''); ?></a>
                        <?php else : ?>
                            <?php echo $this->escape($item->title ?? ''); ?>
                        <?php endif; ?>
                        <?php if (!empty($item->description)) : ?>
                            <br><small class="text-muted"><?php echo $this->escape($item->description); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="display:inline-block;width:1rem;height:1rem;border:1px solid #adb5bd;border-radius:3px;background:<?php echo $this->escape($type['color']); ?>;" aria-hidden="true"></span>
                        <?php echo $dayType !== '' ? $this->escape($dayType) : '<span class="text-muted">-</span>'; ?>
                    </td>
                    <td>
                        <?php echo $renderRule($item); ?>
                    </td>
                    <td class="center jem-specialdays-location-cell">
                        <?php echo $renderLocation($item); ?>
                    </td>
                    <td class="center">
                        <?php echo $this->escape($item->access_level ?? ''); ?>
                    </td>
                    <td class="center">
                        <?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'specialdays.', $canEditState); ?>
                    </td>
                    <td class="center">
                        <?php echo (int) $item->id; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($this->items)) : ?>
                <tr><td colspan="9" class="center"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <?php echo $this->pagination->getListFooter(); ?>

        <input type="hidden" name="task" value="" />
        <input type="hidden" name="boxchecked" value="0" />
        <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
        <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var table = document.getElementById('specialDayList');

    if (!table || !table.tBodies.length) {
        return;
    }

    var body = table.tBodies[0];
    var saveOrder = body.getAttribute('data-save-order') === '1';
    var saveUrl = body.getAttribute('data-save-url') || '';
    var draggedRow = null;

    var updateSpecialDaysOrder = function () {
        Array.prototype.slice.call(body.querySelectorAll('tr[data-id]')).forEach(function (row, index) {
            var position = row.querySelector('.jem-specialdays-position');
            var input = row.querySelector('.jem-specialdays-order-input');
            var value = index + 1;

            if (position) {
                position.textContent = value;
            }

            if (input) {
                input.value = value;
            }
        });
    };

    var persistSpecialDaysOrder = function () {
        if (!saveOrder || !saveUrl) {
            return;
        }

        var params = new URLSearchParams();

        Array.prototype.slice.call(body.querySelectorAll('tr[data-id]')).forEach(function (row, index) {
            params.append('cid[]', row.getAttribute('data-id'));
            params.append('order[]', index + 1);
        });

        window.fetch(saveUrl + '&' + params.toString(), {
            credentials: 'same-origin',
            method: 'GET'
        });
    };

    if (!saveOrder) {
        return;
    }

    body.addEventListener('dragstart', function (event) {
        draggedRow = event.target.closest('tr[data-id]');

        if (!draggedRow) {
            return;
        }

        draggedRow.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', draggedRow.getAttribute('data-id'));
    });

    body.addEventListener('dragover', function (event) {
        var targetRow = event.target.closest('tr[data-id]');

        if (!draggedRow || !targetRow || targetRow === draggedRow) {
            return;
        }

        event.preventDefault();

        var bounds = targetRow.getBoundingClientRect();
        var before = event.clientY < bounds.top + bounds.height / 2;
        targetRow.parentNode.insertBefore(draggedRow, before ? targetRow : targetRow.nextSibling);
        updateSpecialDaysOrder();
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
        updateSpecialDaysOrder();
        persistSpecialDaysOrder();
    });
});
</script>
