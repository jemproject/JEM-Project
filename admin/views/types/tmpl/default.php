<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

$user      = JemFactory::getUser();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$canEdit   = $user->authorise('core.edit', 'com_jem');
$canEditState = $user->authorise('core.edit.state', 'com_jem');
$activeEntityFilter = (int) $this->state->get('filter_entity');
$saveOrder = $canEditState && $activeEntityFilter > 0;
$saveOrderingUrl = Route::_('index.php?option=com_jem&task=types.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1', false);
$showDayColumns = $activeEntityFilter === 4;
$emptyColspan = $showDayColumns ? 13 : 11;

$entityLabels = array(
    1 => Text::_('COM_JEM_TYPE_ENTITY_EVENT'),
    2 => Text::_('COM_JEM_TYPE_ENTITY_CATEGORY'),
    3 => Text::_('COM_JEM_TYPE_ENTITY_VENUE'),
    4 => Text::_('COM_JEM_TYPE_ENTITY_DAY'),
);

$renderEventStateHeader = static function () {
    $states = array(
        array('icon-publish', Text::_('JPUBLISHED')),
        array('icon-unpublish', Text::_('JUNPUBLISHED')),
        array('icon-archive', Text::_('JARCHIVED')),
        array('icon-trash', Text::_('JTRASHED')),
    );

    $html = array();

    foreach ($states as $state) {
        [$icon, $label] = $state;
        $html[] = '<span class="d-inline-block text-center me-2" style="min-width:2rem" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">'
            . '<span class="' . $icon . '" aria-hidden="true"></span>'
            . '<span class="visually-hidden">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
            . '</span>';
    }

    return implode('', $html);
};

$renderEventStateCounts = static function ($counts, $typeId) {
    if ($counts === null) {
        return '<span class="text-muted">-</span>';
    }

    $states = array(
        'published' => array(1, Text::_('JPUBLISHED'), (int) $counts->published),
        'unpublished' => array(0, Text::_('JUNPUBLISHED'), (int) $counts->unpublished),
        'archived' => array(2, Text::_('JARCHIVED'), (int) $counts->archived),
        'trashed' => array(-2, Text::_('JTRASHED'), (int) $counts->trashed),
    );

    $html = array();

    foreach ($states as $state) {
        [$published, $label, $value] = $state;
        $url = Route::_(
            'index.php?option=com_jem&view=events&filter_state=' . (int) $published
            . '&filter_event_type_id=' . (int) $typeId
            . '&filter_category_id=0&filter_search=&filter_type=0&filter_begin=&filter_end=&filter_access=0'
        );
        $html[] = '<a class="badge bg-light text-dark border me-1" style="min-width:2rem" href="' . $url . '" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">'
            . $value
            . '</a>';
    }

    return implode('', $html);
};

$renderTypeRelatedCounts = static function ($item) use ($renderEventStateCounts) {
    $entity = (int) ($item->entity ?? 0);

    if ($entity === 1) {
        return $renderEventStateCounts($item->event_state_counts, $item->id);
    }

    if ($entity === 4) {
        $counts = $item->day_state_counts ?? (object) array(
            'published' => 0,
            'unpublished' => 0,
            'archived' => 0,
            'trashed' => 0,
        );
        $states = array(
            'published' => array(1, Text::_('JPUBLISHED'), (int) $counts->published),
            'unpublished' => array(0, Text::_('JUNPUBLISHED'), (int) $counts->unpublished),
            'archived' => array(2, Text::_('JARCHIVED'), (int) $counts->archived),
            'trashed' => array(-2, Text::_('JTRASHED'), (int) $counts->trashed),
        );
        $html = array();

        foreach ($states as $state) {
            [$published, $label, $value] = $state;
            $url = Route::_(
                'index.php?option=com_jem&view=specialdays&filter_state=' . (int) $published
                . '&filter_day_type=' . (int) $item->id
            );
            $html[] = '<a class="badge bg-light text-dark border me-1" style="min-width:2rem" href="' . $url . '" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">'
                . $value
                . '</a>';
        }

        return implode('', $html);
    }

    if (in_array($entity, array(2, 3), true)) {
        $counts = $item->item_state_counts ?? (object) array(
            'published' => 0,
            'unpublished' => 0,
            'archived' => 0,
            'trashed' => 0,
        );
        $states = array(
            'published' => array(1, Text::_('JPUBLISHED'), (int) $counts->published),
            'unpublished' => array(0, Text::_('JUNPUBLISHED'), (int) $counts->unpublished),
            'archived' => array(2, Text::_('JARCHIVED'), (int) $counts->archived),
            'trashed' => array(-2, Text::_('JTRASHED'), (int) $counts->trashed),
        );
        $html = array();

        foreach ($states as $state) {
            [$published, $label, $value] = $state;
            $url = $entity === 2
                ? Route::_('index.php?option=com_jem&view=categories&filter_published=' . (int) $published . '&filter_category_type_id=' . (int) $item->id)
                : Route::_('index.php?option=com_jem&view=venues&filter_state=' . (int) $published . '&filter_venue_type_id=' . (int) $item->id);
            $html[] = '<a class="badge bg-light text-dark border me-1" style="min-width:2rem" href="' . $url . '" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">'
                . $value
                . '</a>';
        }

        return implode('', $html);
    }

    return '<span class="text-muted">-</span>';
};
?>

<style>
    #typeList .jem-types-order {
        cursor: grab;
        text-align: center;
        user-select: none;
        white-space: nowrap;
        width: 5rem;
    }

    #typeList tr.is-dragging {
        opacity: .55;
    }

    #typeList .jem-types-drag {
        color: #6c757d;
        display: inline-block;
        font-weight: 700;
        letter-spacing: 1px;
        margin-right: .35rem;
        transform: rotate(90deg);
    }

    #typeList .jem-types-position {
        display: inline-block;
        font-weight: 700;
        min-width: 1.35rem;
    }

    #typeList .jem-types-order.is-disabled {
        cursor: default;
        opacity: .55;
    }

    #typeList .jem-types-order-heading.is-disabled,
    #typeList .jem-types-order.is-disabled {
        background-color: color-mix(in srgb, var(--body-bg, #fff) 86%, var(--body-color, #1f2933) 7%);
        color: color-mix(in srgb, var(--body-color, #1f2933) 45%, var(--body-bg, #fff) 55%);
    }

    #typeList .jem-types-order-heading.is-disabled a {
        color: inherit;
    }

    .jem-types-admin-filter-bar .jem-admin-filter-search,
    .jem-types-admin-filter-bar .jem-admin-filter-item {
        flex: 0 0 auto;
    }

    .jem-types-admin-filter-bar .jem-admin-filter-search {
        width: min(100%, 28rem);
    }

    #typeList .jem-types-description {
        color: #6c757d;
        display: -webkit-box;
        font-size: .92em;
        line-height: 1.35;
        max-width: 22rem;
        overflow: hidden;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    #typeList .jem-types-name {
        white-space: nowrap;
    }
</style>

<form action="<?php echo Route::_('index.php?option=com_jem&view=types'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">

        <!-- Filter bar -->
        <fieldset id="filter-bar" class="mb-3">
            <div class="jem-admin-filter-bar jem-types-admin-filter-bar">
                <div class="jem-admin-filter-search">
                    <div class="input-group">
                        <input type="text" name="filter_search" id="filter_search" class="form-control"
                               placeholder="<?php echo Text::_('COM_JEM_SEARCH'); ?>"
                               value="<?php echo $this->escape($this->state->get('filter_search')); ?>"
                               onchange="document.adminForm.submit();" />
                        <button type="submit" class="btn btn-primary">
                            <span class="icon-search" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="btn btn-primary"
                                onclick="document.getElementById('filter_search').value='';this.form.filter_access.value='0';this.form.submit();">
                            <?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>
                        </button>
                    </div>
                </div>
                <div class="jem-admin-filter-item">
                    <select name="filter_state" class="form-select" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?></option>
                        <?php echo HTMLHelper::_('select.options', HTMLHelper::_('jgrid.publishedOptions', array('all' => true)), 'value', 'text', $this->state->get('filter_state'), true);?>
                    </select>
                </div>
                <div class="jem-admin-filter-item">
                    <select name="filter_entity" class="form-select" onchange="this.form.submit()">
                        <option value="0"><?php echo Text::_('COM_JEM_TYPE_FILTER_ENTITY'); ?></option>
                        <option value="1" <?php echo $this->state->get('filter_entity') == 1 ? 'selected' : ''; ?>><?php echo Text::_('COM_JEM_TYPE_ENTITY_EVENT'); ?></option>
                        <option value="2" <?php echo $this->state->get('filter_entity') == 2 ? 'selected' : ''; ?>><?php echo Text::_('COM_JEM_TYPE_ENTITY_CATEGORY'); ?></option>
                        <option value="3" <?php echo $this->state->get('filter_entity') == 3 ? 'selected' : ''; ?>><?php echo Text::_('COM_JEM_TYPE_ENTITY_VENUE'); ?></option>
                        <option value="4" <?php echo $this->state->get('filter_entity') == 4 ? 'selected' : ''; ?>><?php echo Text::_('COM_JEM_TYPE_ENTITY_DAY'); ?></option>
                    </select>
                </div>
                <div class="jem-admin-filter-item">
                    <select name="filter_access" class="form-select" onchange="this.form.submit()">
                        <option value="0"><?php echo Text::_('JOPTION_SELECT_ACCESS'); ?></option>
                        <?php echo HTMLHelper::_('select.options', HTMLHelper::_('access.assetgroups'), 'value', 'text', $this->state->get('filter.access')); ?>
                    </select>
                </div>
                <div class="jem-admin-filter-limit">
                    <?php echo $this->pagination->getLimitBox(); ?>
                </div>
            </div>
        </fieldset>

        <table class="table table-striped" id="typeList">
            <thead>
                <tr>
                    <th style="width:5rem" class="center jem-types-order-heading<?php echo $saveOrder ? '' : ' is-disabled'; ?>">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TYPE_FIELD_ORDER', 'a.ordering', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:1%" class="center">
                        <input type="checkbox" name="checkall-toggle" value=""
                               title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>"
                               onclick="Joomla.checkAll(this)" />
                    </th>
                    <th class="title">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TYPE_FIELD_NAME', 'a.name', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:18%">
                        <?php echo Text::_('JGLOBAL_DESCRIPTION'); ?>
                    </th>
                    <th style="width:12%">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TYPE_FIELD_TYPE', 'a.entity', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:6%" class="center">
                        <?php echo Text::_('COM_JEM_TYPE_FIELD_ICON'); ?>
                    </th>
                    <th style="width:6%" class="center">
                        <?php echo Text::_('COM_JEM_TYPE_FIELD_COLOR'); ?>
                    </th>
                    <th style="width:10%">
                        <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ACCESS', 'access_level', $listDirn, $listOrder); ?>
                    </th>
                    <?php if ($showDayColumns) : ?>
                        <th style="width:9%" class="center">
                            <?php echo Text::_('COM_JEM_TYPE_FIELD_SHOW_DATES_DEFAULT'); ?>
                        </th>
                        <th style="width:9%" class="center">
                            <?php echo Text::_('COM_JEM_TYPE_FIELD_BLOCK_EVENTS'); ?>
                        </th>
                    <?php endif; ?>
                    <th style="width:15%" class="center">
                        <span class="visually-hidden"><?php echo Text::_('COM_JEM_EVENT_STATE_COUNTS'); ?></span>
                        <?php echo $renderEventStateHeader(); ?>
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
                <?php $editUrl = Route::_('index.php?option=com_jem&task=type.edit&id=' . $item->id); ?>
                <tr class="row<?php echo $i % 2; ?>" draggable="<?php echo $saveOrder ? 'true' : 'false'; ?>" data-id="<?php echo (int) $item->id; ?>" data-entity="<?php echo (int) $item->entity; ?>">
                    <td class="jem-types-order<?php echo $saveOrder ? '' : ' is-disabled'; ?>" title="<?php echo $saveOrder ? Text::_('JGRID_HEADING_ORDERING') : Text::_('JORDERINGDISABLED'); ?>">
                        <span class="jem-types-drag" aria-hidden="true">::</span>
                        <span class="jem-types-position"><?php echo (int) $item->ordering; ?></span>
                        <input type="hidden" name="order[]" class="jem-types-order-input" value="<?php echo (int) $item->ordering; ?>">
                    </td>
                    <td class="center">
                        <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                    </td>
                    <td class="jem-types-name">
                        <?php if ($canEdit) : ?>
                            <a href="<?php echo $editUrl; ?>">
                                <?php echo $this->escape($item->name); ?>
                            </a>
                        <?php else : ?>
                            <?php echo $this->escape($item->name); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($item->description) : ?>
                            <span class="jem-types-description" title="<?php echo $this->escape($item->description); ?>">
                                <?php echo $this->escape($item->description); ?>
                            </span>
                        <?php else : ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo isset($entityLabels[$item->entity]) ? $entityLabels[$item->entity] : $item->entity; ?>
                    </td>
                    <td class="center">
                        <?php if ($item->icon) : ?>
                            <span class="<?php echo $this->escape($item->icon); ?>" title="<?php echo $this->escape($item->icon); ?>"></span>
                        <?php endif; ?>
                    </td>
                    <td class="center">
                        <?php if ($item->color && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $item->color)) : ?>
                            <span style="display:inline-block;width:24px;height:24px;border-radius:4px;background:<?php echo $this->escape($item->color); ?>;border:1px solid #ccc;" title="<?php echo $this->escape($item->color); ?>"></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $this->escape($item->access_level); ?>
                    </td>
                    <?php if ($showDayColumns) : ?>
                        <td class="center">
                            <?php echo (int) ($item->attribs_data['show_dates_default'] ?? 1) === 0 ? Text::_('JNO') : Text::_('JYES'); ?>
                        </td>
                        <td class="center">
                            <?php echo !empty($item->attribs_data['block_events']) ? Text::_('JYES') : Text::_('JNO'); ?>
                        </td>
                    <?php endif; ?>
                    <td class="center">
                        <?php echo $renderTypeRelatedCounts($item); ?>
                    </td>
                    <td class="center">
                        <?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'types.', $canEditState); ?>
                    </td>
                    <td class="center">
                        <?php echo $item->id; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($this->items)) : ?>
                <tr><td colspan="<?php echo (int) $emptyColspan; ?>" class="center"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td></tr>
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
    var table = document.getElementById('typeList');

    if (!table || !table.tBodies.length) {
        return;
    }

    var body = table.tBodies[0];
    var saveOrder = body.getAttribute('data-save-order') === '1';
    var saveUrl = body.getAttribute('data-save-url') || '';
    var draggedRow = null;

    var getRowsByEntity = function (entity) {
        return Array.prototype.slice.call(body.querySelectorAll('tr[data-id][data-entity="' + entity + '"]'));
    };

    var updateTypeOrder = function (entity) {
        getRowsByEntity(entity).forEach(function (row, index) {
            var position = row.querySelector('.jem-types-position');
            var input = row.querySelector('.jem-types-order-input');
            var value = index + 1;

            if (position) {
                position.textContent = value;
            }

            if (input) {
                input.value = value;
            }
        });
    };

    var persistTypeOrder = function (entity) {
        if (!saveOrder || !saveUrl) {
            return;
        }

        var params = new URLSearchParams();

        getRowsByEntity(entity).forEach(function (row, index) {
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

        if (targetRow.getAttribute('data-entity') !== draggedRow.getAttribute('data-entity')) {
            return;
        }

        event.preventDefault();

        var bounds = targetRow.getBoundingClientRect();
        var before = event.clientY < bounds.top + bounds.height / 2;
        targetRow.parentNode.insertBefore(draggedRow, before ? targetRow : targetRow.nextSibling);
        updateTypeOrder(draggedRow.getAttribute('data-entity'));
    });

    body.addEventListener('drop', function (event) {
        event.preventDefault();
    });

    body.addEventListener('dragend', function () {
        if (!draggedRow) {
            return;
        }

        var entity = draggedRow.getAttribute('data-entity');
        draggedRow.classList.remove('is-dragging');
        draggedRow = null;
        updateTypeOrder(entity);
        persistTypeOrder(entity);
    });
});
</script>
