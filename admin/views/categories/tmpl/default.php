<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;

$user = JemFactory::getUser();
$userId = $user->get('id');
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn = $this->escape($this->state->get('list.direction'));
$canOrder = $user->authorise('core.edit.state', 'com_jem.category');
$saveOrder = $canOrder && $listOrder == 'a.lft' && strtolower($listDirn) === 'asc';
$saveOrderingUrl = Route::_('index.php?option=com_jem&task=categories.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1', false);
$hideOrderNumbers = (int) JemHelper::globalattribs()->get('backend_show_order_numbers', 1) === 0;
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('table.columns');
$articleCreateModes = array(
    0 => Text::_('COM_JEM_ARTICLE_MODE_NONE'),
    1 => Text::_('COM_JEM_ARTICLE_MODE_AUTO'),
    2 => Text::_('COM_JEM_ARTICLE_MODE_MANUAL'),
);

$eventStateColumns = array(
    'published' => array(1, 'icon-publish', Text::_('JPUBLISHED')),
    'unpublished' => array(0, 'icon-unpublish', Text::_('JUNPUBLISHED')),
    'archived' => array(2, 'icon-archive', Text::_('JARCHIVED')),
    'trashed' => array(-2, 'icon-trash', Text::_('JTRASHED')),
);

$renderEventStateHeader = static function ($columns) {
    $html = array();

    foreach ($columns as $state) {
        [$published, $icon, $label] = $state;
        $html[] = '<span class="d-inline-block text-center me-2" style="min-width:2rem" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">'
            . '<span class="' . $icon . '" aria-hidden="true"></span>'
            . '<span class="visually-hidden">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
            . '</span>';
    }

    return implode('', $html);
};

$renderEventStateCounts = static function ($counts, $categoryId) use ($eventStateColumns) {
    $html = array();

    foreach ($eventStateColumns as $property => $state) {
        [$published, $icon, $label] = $state;
        $value = (int) ($counts->{$property} ?? 0);
        $url = Route::_(
            'index.php?option=com_jem&view=events&filter_state=' . (int) $published
            . '&filter_category_id=' . (int) $categoryId
            . '&filter_event_type_id=0&filter_search=&filter_type=0&filter_begin=&filter_end=&filter_access=0'
        );
        $html[] = '<a class="badge bg-light text-dark border me-1" style="min-width:2rem" href="' . $url . '" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">'
            . $value
            . '</a>';
    }

    return implode('', $html);
};
?>

<style>
    #categoryList .jem-categories-order {
        cursor: grab;
        text-align: center;
        user-select: none;
        white-space: nowrap;
        width: 5rem;
    }

    #categoryList tr.is-dragging {
        opacity: .55;
    }

    #categoryList .jem-categories-drag {
        color: #6c757d;
        display: inline-block;
        font-weight: 700;
        letter-spacing: 1px;
        margin-right: .35rem;
        transform: rotate(90deg);
    }

    #categoryList .jem-categories-position {
        display: inline-block;
        font-weight: 700;
        min-width: 1.35rem;
    }

    #categoryList .jem-categories-order.is-disabled {
        cursor: default;
        opacity: .55;
    }
</style>

<form action="<?php echo Route::_('index.php?option=com_jem&view=categories'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <fieldset id="filter-bar" class="mb-3">
            <div class="jem-admin-filter-bar">
                <div class="jem-admin-filter-search">
                    <div class="input-group">
                        <input type="text" name="filter_search" id="filter_search" class="form-control" aria-describedby="filter_search-desc" placeholder="<?php echo Text::_('COM_JEM_SEARCH'); ?>" value="<?php echo $this->escape($this->state->get('filter_search')); ?>" inputmode="search" onChange="document.adminForm.submit();">

                        <button type="submit" class="filter-search-bar__button btn btn-primary" aria-label="Search">
                            <span class="filter-search-bar__button-icon icon-search" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('filter_search').value='';document.getElementById('filter_category_type_id').value='0';this.form.filter_access.value='0';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
                    </div>
                </div>
                <div class="jem-admin-filter-item">
                    <select name="filter_level" class="inputbox form-select wauto-minwmax m-0" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('JOPTION_SELECT_MAX_LEVELS'); ?></option>
                        <?php echo HTMLHelper::_('select.options', $this->f_levels, 'value', 'text', $this->state->get('filter.level')); ?>
                    </select>
                </div>
                <div class="jem-admin-filter-item">
                    <?php echo $this->lists['category_type_filter']; ?>
                </div>
                <div class="jem-admin-filter-item">
                    <select name="filter_access" class="inputbox form-select wauto-minwmax m-0" onchange="this.form.submit()">
                        <option value="0"><?php echo Text::_('JOPTION_SELECT_ACCESS'); ?></option>
                        <?php echo HTMLHelper::_('select.options', HTMLHelper::_('access.assetgroups'), 'value', 'text', $this->state->get('filter.access')); ?>
                    </select>
                </div>
                <div class="jem-admin-filter-item">
                    <select name="filter_published" class="inputbox form-select wauto-minwmax m-0" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?></option>
                        <?php echo HTMLHelper::_('select.options', HTMLHelper::_('jgrid.publishedOptions'), 'value', 'text', $this->state->get('filter.published'), true); ?>
                    </select>
                </div>
                <div class="jem-admin-filter-limit">
                    <?php echo $this->pagination->getLimitBox(); ?>
                </div>
            </div>
        </fieldset>

        <div class="clr"></div>

        <table class="table table-striped itemList<?php echo $hideOrderNumbers ? ' jem-hide-order-numbers' : ''; ?>" id="categoryList">
            <thead>
            <tr>
                <th class="center jem-list-check">
                    <input type="checkbox" name="checkall-toggle" value=""
                           title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)"/>
                </th>
                <th class="center jem-list-order-heading">
                    <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TYPE_FIELD_ORDER', 'a.lft', $listDirn, $listOrder); ?>
                </th>
                <th class="center jem-list-status">
                    <?php echo HTMLHelper::_('grid.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?>
                </th>
                <th>
                    <?php echo HTMLHelper::_('grid.sort', 'JGLOBAL_TITLE', 'a.catname', $listDirn, $listOrder); ?>
                </th>
                <th style="width:5%" class="center" nowrap="nowrap">
                    <?php echo Text::_('COM_JEM_COLOR'); ?>
                </th>
                <th style="width:15%"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_GROUP', 'gr.name', $listDirn, $listOrder); ?></th>
                <th style="width:12%" class="center">
                    <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_CATEGORY_FIELD_ARTICLE_CATEGORY_LABEL', 'a.article_category_id', $listDirn, $listOrder); ?>
                </th>
                <th style="width:10%" class="center">
                    <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_CATEGORY_FIELD_ARTICLE_MODE_LABEL', 'a.article_create_mode', $listDirn, $listOrder); ?>
                </th>
                <th style="width:15%" class="center" nowrap="nowrap">
                    <span class="visually-hidden"><?php echo Text::_('COM_JEM_EVENT_STATE_COUNTS'); ?></span>
                    <?php echo $renderEventStateHeader($eventStateColumns); ?>
                </th>
                <th style="width:10%" class="center" nowrap="nowrap">
                    <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ACCESS', 'a.access', $listDirn, $listOrder); ?>
                </th>
                <th>
                    <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_AUTHOR', 'ua.name', $listDirn, $listOrder); ?>
                </th>
                <th class="center nowrap">
                    <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_DATE_CREATED', 'a.created_time', $listDirn, $listOrder); ?>
                </th>
                <th style="width:1%" class="center nowrap">
                    <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                </th>
            </tr>
            </thead>

            <tbody data-save-order="<?php echo $saveOrder ? '1' : '0'; ?>" data-save-url="<?php echo $this->escape($saveOrderingUrl); ?>">
            <?php
            $originalOrders = array();
            $countItems = count($this->items);

            foreach ($this->items as $i => $item) :
                $ordering   = ($listOrder == 'a.lft');
                $canCreate  = $user->authorise('core.create');
                $orderkey   = array_search($item->id, $this->ordering[$item->parent_id]);
                $canEdit    = $user->authorise('core.edit');
                $canCheckin = $user->authorise('core.manage', 'com_checkin') || $item->checked_out == $userId || $item->checked_out == 0;
                $canEditOwn = $user->authorise('core.edit.own') && $item->created_user_id == $userId;
                $canChange  = $user->authorise('core.edit.state') && $canCheckin;
                $grouplink  = 'index.php?option=com_jem&amp;task=group.edit&amp;id=' . $item->groupid;

                if ($item->level > 0) {
                    $repeat = $item->level - 1;
                } else {
                    $repeat = 0;
                }
                ?>
                <tr class="row<?php echo $i % 2; ?>" draggable="<?php echo $saveOrder ? 'true' : 'false'; ?>" data-id="<?php echo (int) $item->id; ?>">
                    <td class="center">
                        <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                    </td>
                    <td class="jem-categories-order<?php echo $saveOrder ? '' : ' is-disabled'; ?>" title="<?php echo $saveOrder ? Text::_('JGRID_HEADING_ORDERING') : Text::_('JORDERINGDISABLED'); ?>">
                        <span class="jem-categories-drag" aria-hidden="true">::</span>
                        <span class="jem-categories-position"><?php echo (int) ($orderkey + 1); ?></span>
                        <input type="hidden" name="order[]" class="jem-categories-order-input" value="<?php echo (int) ($orderkey + 1); ?>">
                    </td>
                    <td class="center">
                        <?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'categories.', $canChange); ?>
                    </td>
                    <td>
                        <?php echo str_repeat('<span class="gi">|&mdash;</span>', $repeat) ?>
                        <?php if ($item->checked_out) : ?>
                            <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'categories.', $canCheckin); ?>
                        <?php endif; ?>
                        <?php if ($canEdit || $canEditOwn) : ?>
                            <a href="<?php echo Route::_('index.php?option=com_jem&task=category.edit&id=' . $item->id); ?>">
                                <?php echo $this->escape($item->catname); ?></a>
                        <?php else : ?>
                            <?php echo $this->escape($item->catname); ?>
                        <?php endif; ?>
                        <p class="smallsub" title="<?php echo $this->escape($item->path); ?>">
                            <?php echo str_repeat('<span class="gtr">|&mdash;</span>', $repeat) ?>
                            <?php if (empty($item->note)) : ?>
                                <?php echo Text::sprintf('JGLOBAL_LIST_ALIAS', $this->escape($item->alias)); ?>
                            <?php else : ?>
                                <?php echo Text::sprintf('JGLOBAL_LIST_ALIAS_NOTE', $this->escape($item->alias), $this->escape($item->note)); ?>
                            <?php endif; ?></p>
                    </td>
                    <td class="center">
                        <div class="colorpreview<?php echo ($item->color == '') ? ' transparent-color" title="transparent"' : '" style="background-color:' . $item->color . '"' ?> aria-labelledby="
                             color-desc-<?php echo $item->id; ?>">
                        </div>
                        <div role="tooltip"
                             id="color-desc-<?php echo $item->id; ?>"><?php echo ($item->color == '') ? 'transparent' : $item->color ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($item->catgroup) : ?>
                            <span <?php echo JEMOutput::tooltip(Text::_('COM_JEM_GROUP_EDIT'), $item->catgroup, 'editlinktip'); ?>>
                                <a href="<?php echo $grouplink; ?>">
                                    <?php echo $this->escape($item->catgroup); ?>
                                </a></span>
                        <?php elseif ($item->groupid) : ?>
                            <?php echo Text::sprintf('COM_JEM_CATEGORY_UNKNOWN_GROUP', $item->groupid); ?>
                        <?php else : ?>
                            <?php echo '-'; ?>
                        <?php endif; ?>
                    </td>
                    <td class="center">
                        <?php if (!empty($item->article_category_id) && !empty($item->article_category_title)) : ?>
                            <?php echo $this->escape($item->article_category_title); ?>
                        <?php elseif (!empty($item->article_category_id)) : ?>
                            <?php echo Text::sprintf('COM_JEM_CATEGORY_UNKNOWN_CATEGORY', (int) $item->article_category_id); ?>
                        <?php else : ?>
                            <?php echo '-'; ?>
                        <?php endif; ?>
                    </td>
                    <td class="center">
                        <?php echo $this->escape($articleCreateModes[(int) $item->article_create_mode] ?? $articleCreateModes[0]); ?>
                    </td>
                    <td class="center">
                        <?php echo $renderEventStateCounts($item->event_state_counts, $item->id); ?>
                    </td>
                    <td class="center">
                        <?php echo $this->escape($item->access_level); ?>
                    </td>
                    <td>
                        <?php echo !empty($item->author_name) ? $this->escape($item->author_name) : '-'; ?>
                    </td>
                    <td class="center">
                        <?php echo !empty($item->created_time) ? HTMLHelper::_('date', $item->created_time, Text::_('DATE_FORMAT_LC5')) : '-'; ?>
                    </td>
                    <td class="center">
                        <span title="<?php echo sprintf('%d-%d', $item->lft, $item->rgt); ?>">
                            <?php echo (int)$item->id; ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="ms-auto mb-4 me-0">
            <?php echo (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks(null) : $this->pagination->getListFooter()); ?>
        </div>
    </div>

    <div>
        <input type="hidden" name="task" value=""/>
        <input type="hidden" name="boxchecked" value="0"/>
        <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>"/>
        <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>"/>
        <input type="hidden" name="original_order_values" value="<?php echo implode(',', $originalOrders); ?>"/>

        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var table = document.getElementById('categoryList');

    if (!table || !table.tBodies.length) {
        return;
    }

    var body = table.tBodies[0];
    var saveOrder = body.getAttribute('data-save-order') === '1';
    var saveUrl = body.getAttribute('data-save-url') || '';
    var draggedRow = null;

    var rows = function () {
        return Array.prototype.slice.call(body.querySelectorAll('tr[data-id]'));
    };

    var updateOrder = function () {
        rows().forEach(function (row, index) {
            var value = index + 1;
            var position = row.querySelector('.jem-categories-position');
            var input = row.querySelector('.jem-categories-order-input');

            if (position) {
                position.textContent = value;
            }

            if (input) {
                input.value = value;
            }
        });
    };

    var persistOrder = function () {
        if (!saveOrder || !saveUrl) {
            return;
        }

        var params = new URLSearchParams();

        rows().forEach(function (row, index) {
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
        updateOrder();
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
        updateOrder();
        persistOrder();
    });
});
</script>
