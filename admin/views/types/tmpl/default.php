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
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;

$user      = JemFactory::getUser();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$canEdit   = $user->authorise('core.edit', 'com_jem');
$canEditState = $user->authorise('core.edit.state', 'com_jem');
$activeEntityFilter = (int) $this->state->get('filter_entity');
$saveOrder = $canEditState
    && $activeEntityFilter > 0
    && $listOrder === 'a.ordering'
    && strtolower($listDirn) === 'asc';
$saveOrderingUrl = Route::_('index.php?option=com_jem&task=types.saveOrderAjax&tmpl=component', false);
$hideOrderNumbers = (int) JemHelper::globalattribs()->get('backend_show_order_numbers', 1) === 0;
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('table.columns');
$showDayColumns = $activeEntityFilter === 4;
$emptyColspan = $showDayColumns ? 15 : 13;

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

        <?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

        <table class="table table-striped itemList<?php echo $hideOrderNumbers ? ' jem-hide-order-numbers' : ''; ?>" id="typeList">
            <thead>
                <tr>
                    <th class="center jem-list-check">
                        <input type="checkbox" name="checkall-toggle" value=""
                               title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>"
                               onclick="Joomla.checkAll(this)" />
                    </th>
                    <th class="center jem-list-order-heading jem-types-order-heading<?php echo $saveOrder ? '' : ' is-disabled'; ?>">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_TYPE_FIELD_ORDER', 'a.ordering', $listDirn, $listOrder); ?>
                    </th>
                    <th class="center jem-list-status">
                        <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?>
                    </th>
                    <th class="title">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_TYPE_FIELD_NAME', 'a.name', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:18%">
                        <?php echo Text::_('JGLOBAL_DESCRIPTION'); ?>
                    </th>
                    <th style="width:12%">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_TYPE_FIELD_TYPE', 'a.entity', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:6%" class="center">
                        <?php echo Text::_('COM_JEM_TYPE_FIELD_ICON'); ?>
                    </th>
                    <th style="width:6%" class="center">
                        <?php echo Text::_('COM_JEM_TYPE_FIELD_COLOR'); ?>
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
                    <th style="width:10%">
                        <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ACCESS', 'access_level', $listDirn, $listOrder); ?>
                    </th>
                    <th>
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_AUTHOR', 'u.name', $listDirn, $listOrder); ?>
                    </th>
                    <th class="center nowrap">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_DATE_CREATED', 'a.created', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:5%" class="center">
                        <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                    </th>
                </tr>
            </thead>
            <tbody data-save-order="<?php echo $saveOrder ? '1' : '0'; ?>" data-save-url="<?php echo $this->escape($saveOrderingUrl); ?>">
            <?php foreach ($this->items as $i => $item) : ?>
                <?php $editUrl = Route::_('index.php?option=com_jem&task=type.edit&id=' . $item->id); ?>
                <tr class="row<?php echo $i % 2; ?>" draggable="<?php echo $saveOrder ? 'true' : 'false'; ?>" data-id="<?php echo (int) $item->id; ?>" data-entity="<?php echo (int) $item->entity; ?>">
                    <td class="center">
                        <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                    </td>
                    <td class="jem-types-order<?php echo $saveOrder ? '' : ' is-disabled'; ?>" title="<?php echo $saveOrder ? Text::_('JGRID_HEADING_ORDERING') : Text::_('JORDERINGDISABLED'); ?>">
                        <span class="jem-types-drag" aria-hidden="true">::</span>
                        <span class="jem-types-position"><?php echo (int) $item->ordering; ?></span>
                        <input type="hidden" name="order[]" class="jem-types-order-input" value="<?php echo (int) $item->ordering; ?>">
                    </td>
                    <td class="center">
                        <?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'types.', $canEditState); ?>
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
                    <td>
                        <?php echo $this->escape($item->access_level); ?>
                    </td>
                    <td>
                        <?php echo !empty($item->author_name) ? $this->escape($item->author_name) : '-'; ?>
                    </td>
                    <td class="center">
                        <?php echo !empty($item->created) ? HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC5')) : '-'; ?>
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
        <?php echo $this->filterForm->renderControlFields(); ?>
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
