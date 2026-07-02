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
use Joomla\String\StringHelper;

$user        = JemFactory::getUser();
$userId        = $user->get('id');
$listOrder    = $this->escape($this->state->get('list.ordering'));
$listDirn    = $this->escape($this->state->get('list.direction'));
$canOrder    = $user->authorise('core.edit.state', 'com_jem');
$saveOrder    = $canOrder && $listOrder == 'a.ordering' && strtolower($listDirn) === 'asc';
$saveOrderingUrl = Route::_('index.php?option=com_jem&task=venues.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1', false);
$hideOrderNumbers = (int) JemHelper::globalattribs()->get('backend_show_order_numbers', 1) === 0;
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('table.columns');

$eventStateColumns = array(
    'event_published' => array(1, 'icon-publish', Text::_('JPUBLISHED')),
    'event_unpublished' => array(0, 'icon-unpublish', Text::_('JUNPUBLISHED')),
    'event_archived' => array(2, 'icon-archive', Text::_('JARCHIVED')),
    'event_trashed' => array(-2, 'icon-trash', Text::_('JTRASHED')),
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

$renderEventStateCounts = static function ($item) use ($eventStateColumns) {
    $html = array();

    foreach ($eventStateColumns as $property => $state) {
        [$published, $icon, $label] = $state;
        $value = (int) ($item->{$property} ?? 0);
        $url = Route::_(
            'index.php?option=com_jem&view=events&filter_state=' . (int) $published
            . '&filter_venue_id=' . (int) $item->id
            . '&filter_category_id=0&filter_event_type_id=0&filter_search=&filter_type=0&filter_begin=&filter_end=&filter_access=0'
        );
        $html[] = '<a class="badge bg-light text-dark border me-1" style="min-width:2rem" href="' . $url . '" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">'
            . $value
            . '</a>';
    }

    return implode('', $html);
};
?>

<style>
    #venueList .jem-venues-order {
        cursor: grab;
        text-align: center;
        user-select: none;
        white-space: nowrap;
        width: 5rem;
    }

    #venueList tr.is-dragging {
        opacity: .55;
    }

    #venueList .jem-venues-drag {
        color: #6c757d;
        display: inline-block;
        font-weight: 700;
        letter-spacing: 1px;
        margin-right: .35rem;
        transform: rotate(90deg);
    }

    #venueList .jem-venues-position {
        display: inline-block;
        font-weight: 700;
        min-width: 1.35rem;
    }

    #venueList .jem-venues-order.is-disabled {
        cursor: default;
        opacity: .55;
    }
</style>

<form action="<?php echo Route::_('index.php?option=com_jem&view=venues'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <fieldset id="filter-bar" class=" mb-3">
            <div class="jem-admin-filter-bar">
                <div class="jem-admin-filter-search">
                    <div class="input-group">
                        <input type="text" name="filter_search" id="filter_search" class="form-control" aria-describedby="filter_search-desc" placeholder="<?php echo Text::_('COM_JEM_SEARCH');?>" value="<?php echo $this->escape($this->state->get('filter_search')); ?>"  inputmode="search" onChange="document.adminForm.submit();" >

                        <button type="submit" class="filter-search-bar__button btn btn-primary" aria-label="Search">
                            <span class="filter-search-bar__button-icon icon-search" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('filter_search').value='';document.getElementById('filter_venue_type_id').value='0';this.form.filter_access.value='0';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
                    </div>
                </div>
                <div class="jem-admin-filter-item">
                    <?php echo $this->lists['venue_type_filter']; ?>
                </div>
                <div class="jem-admin-filter-item">
                    <select name="filter_access" class="inputbox form-select wauto-minwmax" onchange="this.form.submit()">
                        <option value="0"><?php echo Text::_('JOPTION_SELECT_ACCESS');?></option>
                        <?php echo HTMLHelper::_('select.options', HTMLHelper::_('access.assetgroups'), 'value', 'text', $this->state->get('filter.access'));?>
                    </select>
                </div>
                <div class="jem-admin-filter-item">
                    <select name="filter_state" class="inputbox form-select wauto-minwmax" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED');?></option>
                        <?php echo HTMLHelper::_('select.options', HTMLHelper::_('jgrid.publishedOptions', array('all' => true)), 'value', 'text', $this->state->get('filter_state'), true); ?>
                    </select>
                </div>
                <div class="jem-admin-filter-limit">
                    <?php echo $this->pagination->getLimitBox(); ?>
                </div>
            </div>
        </fieldset>
        <div class="clr"> </div>

        <table class="table table-striped itemList<?php echo $hideOrderNumbers ? ' jem-hide-order-numbers' : ''; ?>" id="venueList">
            <thead>
            <tr>
                <th class="center jem-list-check">
                    <input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
                </th>
                <th class="center jem-list-order-heading">
                    <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TYPE_FIELD_ORDER', 'a.ordering', $listDirn, $listOrder ); ?>
                </th>
                <th class="center nowrap jem-list-status">
                    <?php echo Text::_('JSTATUS'); ?>
                </th>
                <th class="title">
                    <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_VENUE', 'a.venue', $listDirn, $listOrder ); ?>
                </th>
                <th style="width:20%">
                    <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ALIAS', 'a.alias', $listDirn, $listOrder ); ?>
                </th>
                <th style="width:5%" class="center" nowrap="nowrap">
                    <?php echo Text::_('COM_JEM_COLOR'); ?>
                </th>
                <th>
                    <?php echo Text::_('COM_JEM_WEBSITE'); ?>
                </th>
                <th style="width:10%">
                    <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_CITY', 'a.city', $listDirn, $listOrder ); ?>
                </th>
                <th style="width:5%" class="center">
                    <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_STATE', 'a.state', $listDirn, $listOrder ); ?>
                </th>
                <th style="width:5%" class="center">
                    <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_COUNTRY', 'a.country', $listDirn, $listOrder ); ?>
                </th>
                <th style="width:15%" class="center" nowrap="nowrap">
                    <span class="visually-hidden"><?php echo Text::_('COM_JEM_EVENT_STATE_COUNTS'); ?></span>
                    <?php echo $renderEventStateHeader($eventStateColumns); ?>
                </th>
                <th style="width:5%" class="center" nowrap="nowrap">
                    <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ACCESS', 'a.access', $listDirn, $listOrder); ?>
                </th>
                <th>
                    <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_AUTHOR', 'u.name', $listDirn, $listOrder); ?>
                </th>
                <th class="center nowrap">
                    <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_DATE_CREATED', 'a.created', $listDirn, $listOrder); ?>
                </th>
                <th style="width:1%" class="center" nowrap="nowrap">
                    <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ID', 'a.id', $listDirn, $listOrder ); ?>
                </th>
            </tr>
            </thead>

            <tbody data-save-order="<?php echo $saveOrder ? '1' : '0'; ?>" data-save-url="<?php echo $this->escape($saveOrderingUrl); ?>">
            <?php
            $countItems = count($this->items);
            foreach ($this->items as $i => $item) :
                $ordering    = ($listOrder == 'a.ordering');
                $canCreate    = $user->authorise('core.create');
                $canEdit    = $user->authorise('core.edit');
                $canCheckin    = $user->authorise('core.manage', 'com_checkin') || $item->checked_out == $userId || $item->checked_out == 0;
                $canEditOwn    = $user->authorise('core.edit.own') && $item->created_by == $userId;
                $canChange    = $user->authorise('core.edit.state') && $canCheckin;
                $link         = 'index.php?option=com_jem&amp;task=venue.edit&amp;id='. $item->id;
                $published     = HTMLHelper::_('jgrid.published', $item->published, $i, 'venues.', $canChange, 'cb', $item->publish_up, $item->publish_down);
                ?>
                <tr class="row<?php echo $i % 2; ?>" draggable="<?php echo $saveOrder ? 'true' : 'false'; ?>" data-id="<?php echo (int) $item->id; ?>">
                    <td class="center">
                        <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>
                    <td class="jem-venues-order<?php echo $saveOrder ? '' : ' is-disabled'; ?>" title="<?php echo $saveOrder ? Text::_('JGRID_HEADING_ORDERING') : Text::_('JORDERINGDISABLED'); ?>">
                        <span class="jem-venues-drag" aria-hidden="true">::</span>
                        <span class="jem-venues-position"><?php echo (int) $item->ordering; ?></span>
                        <input type="hidden" name="order[]" class="jem-venues-order-input" value="<?php echo (int) $item->ordering; ?>">
                    </td>
                    <td class="center"><?php echo $published; ?></td>
                    <td style="text-align:left" class="venue">
                        <?php if ($item->checked_out) : ?>
                            <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'venues.', $canCheckin); ?>
                        <?php endif; ?>
                        <?php if ($canEdit || $canEditOwn) : ?>
                            <a href="<?php echo Route::_('index.php?option=com_jem&task=venue.edit&id='.(int) $item->id); ?>">
                                <?php echo $this->escape($item->venue); ?>
                            </a>
                        <?php else : ?>
                            <?php echo $this->escape($item->venue); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (StringHelper::strlen($item->alias) > 25) : ?>
                            <?php echo $this->escape(StringHelper::substr($item->alias, 0 , 25)).'...'; ?>
                        <?php else : ?>
                            <?php echo $this->escape($item->alias); ?>
                        <?php endif; ?>
                    </td>
                    <td class="center">
                        <div class="colorpreview<?php echo ($item->color == '') ? ' transparent-color" title="transparent"' : '" style="background-color:' . $item->color . '"' ?> aria-labelledby="
                             color-desc-<?php echo $item->id; ?>">
                        </div>
                        <div role="tooltip"
                             id="color-desc-<?php echo $item->id; ?>"><?php echo ($item->color == '') ? 'transparent' : $item->color ?>
                        </div>
                    </td>
                    <td style="text-align:left">
                        <?php if ($item->url) : ?>
                            <a href="<?php echo $this->escape($item->url); ?>" target="_blank">
                                <?php if (StringHelper::strlen($item->url) > 25) : ?>
                                    <?php echo $this->escape(StringHelper::substr($item->url, 0 , 25)).'...'; ?>
                                <?php else : ?>
                                    <?php echo $this->escape($item->url); ?>
                                <?php endif; ?>
                            </a>
                        <?php else : ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td style="text-align:left" class="city"><?php echo $item->city ? $this->escape($item->city) : '-'; ?></td>
                    <td class="center state"><?php echo $item->state ? $this->escape($item->state) : '-'; ?></td>
                    <td class="center country"><?php echo $item->country ? $this->escape($item->country) : '-'; ?></td>
                    <td class="center"><?php echo $renderEventStateCounts($item); ?></td>
                    <td class="center"> <?php echo $this->escape($item->access_level); ?></td>
                    <td>
                        <?php
                        $created = HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC5'));
                        $overlib = Text::_('COM_JEM_CREATED_AT') . ': ' . $created . '<br>';
                        $overlib .= Text::_('COM_JEM_AUTHOR') . ': ' . $item->author . '<br>';
                        $overlib .= Text::_('COM_JEM_EMAIL') . ': ' . $item->email . '<br>';
                        if ($item->author_ip != '') {
                            $overlib .= Text::_('COM_JEM_WITH_IP') . ': ' . $item->author_ip . '<br>';
                        }
                        if (!empty($item->modified)) {
                            $overlib .= '<br>' . Text::_('COM_JEM_EDITED_AT') . ': ' . HTMLHelper::_('date', $item->modified, Text::_('DATE_FORMAT_LC5')) . '<br>' . Text::_('COM_JEM_GLOBAL_MODIFIEDBY') . ': ' . $item->modified_by;
                        }
                        ?>
                        <span <?php echo JEMOutput::tooltip(Text::_('COM_JEM_EVENTS_STATS'), $overlib, 'editlinktip'); ?>>
                            <a href="<?php echo 'index.php?option=com_users&amp;task=edit&amp;hidemainmenu=1&amp;cid[]=' . (int) $item->created_by; ?>"><?php echo $this->escape($item->author); ?></a>
                        </span>
                    </td>
                    <td class="center"><?php echo $created; ?></td>
                    <td class="center">
                        <?php echo (int) $item->id; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="ms-auto mb-4 me-0">
            <?php echo  (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks(null) : $this->pagination->getListFooter()); ?>
        </div>
    </div>

    <div>
        <input type="hidden" name="task" value="" />
        <input type="hidden" name="boxchecked" value="0" />
        <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
        <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />

        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var table = document.getElementById('venueList');

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
            var position = row.querySelector('.jem-venues-position');
            var input = row.querySelector('.jem-venues-order-input');

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
