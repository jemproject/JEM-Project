<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

$user = JemFactory::getUser();
$userId = $user->get('id');
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn = $this->escape($this->state->get('list.direction'));
$canOrder = $user->authorise('core.edit.state', 'com_jem.category');
$saveOrder = $listOrder == 'a.lft';
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('table.columns');
?>

<form action="<?php echo Route::_('index.php?option=com_jem&view=categories'); ?>" method="post" name="adminForm"
      id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <fieldset id="filter-bar" class=" mb-3">
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" name="filter_search" id="filter_search" class="form-control"
                               aria-describedby="filter_search-desc"
                               placeholder="<?php echo Text::_('COM_JEM_SEARCH'); ?>"
                               value="<?php echo $this->escape($this->state->get('filter_search')); ?>"
                               inputmode="search" onChange="document.adminForm.submit();">

                        <button type="submit" class="filter-search-bar__button btn btn-primary" aria-label="Search">
                            <span class="filter-search-bar__button-icon icon-search" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="btn btn-primary"
                                onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="filter_level" class="inputbox form-select m-0" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('JOPTION_SELECT_MAX_LEVELS'); ?></option>
                        <?php echo HTMLHelper::_('select.options', $this->f_levels, 'value', 'text', $this->state->get('filter.level')); ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="filter_published" class="inputbox form-select m-0" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?></option>
                        <?php echo HTMLHelper::_('select.options', HTMLHelper::_('jgrid.publishedOptions'), 'value', 'text', $this->state->get('filter.published'), true); ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="filter_state" class="inputbox form-select" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?></option>
                        <?php echo HTMLHelper::_('select.options', HTMLHelper::_('jgrid.publishedOptions', array('all' => 0, 'archived' => 0, 'trash' => 0)), 'value', 'text', $this->state->get('filter_state'), true); ?>
                    </select>
                </div>
            </div>

        </fieldset>

        <div class="clr"></div>

        <table class="table table-striped" id="articleList">
            <thead>
            <tr>
                <th style="width:1%" class="center">
                    <input type="checkbox" name="checkall-toggle" value=""
                           title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)"/>
                </th>
                <th>
                    <?php echo HTMLHelper::_('grid.sort', 'JGLOBAL_TITLE', 'a.catname', $listDirn, $listOrder); ?>
                </th>
                <th style="width:5%" class="center" nowrap="nowrap">
                    <?php echo Text::_('COM_JEM_COLOR'); ?>
                </th>
                <th style="width:15%"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_GROUP', 'gr.name', $listDirn, $listOrder); ?></th>
                <th style="width:1%" class="center" nowrap="nowrap"><?php echo Text::_('COM_JEM_EVENTS'); ?></th>
                <th style="width:5%" class="center">
                    <?php echo HTMLHelper::_('grid.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?>
                </th>
                <th style="width:5px%" class="center">
                    <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ORDERING', 'a.lft', $listDirn, $listOrder); ?>
                    <?php if ($saveOrder) : ?>
                        <?php //echo HTMLHelper::_('grid.order',  $this->items, 'filesave.png', 'categories.saveorder'); ?>
                    <?php endif; ?>
                </th>
                <th style="width:10%" class="center" nowrap="nowrap">
                    <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ACCESS', 'a.access', $listDirn, $listOrder); ?>
                </th>
                <th style="width:1%" class="center nowrap">
                    <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                </th>
            </tr>
        </thead>
        <tfoot>
            <tr>
                <td colspan="20">
                    <?php //echo (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks(null, array('showLimitBox' => true)) : $this->pagination->getListFooter()); ?>
                    <div class="row align-items-center">
                        <div class="col-md-9">
                            <?php echo (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks(null) : $this->pagination->getListFooter()); ?>
                        </div>
                        <div class="col-md-3">
                            <div class="limit float-end">
                                <?php echo $this->pagination->getLimitBox(); ?>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        </tfoot>
        <tbody>
            <?php
            $originalOrders = array();
            $countItems = count($this->items);
            foreach ($this->items as $i => $item) :
            $ordering = ($listOrder == 'a.lft');
            $canCreate = $user->authorise('core.create');
            $orderkey = array_search($item->id, $this->ordering[$item->parent_id]);
            $canEdit = $user->authorise('core.edit');
            $canCheckin = $user->authorise('core.manage', 'com_checkin') || $item->checked_out == $userId || $item->checked_out == 0;
            $canEditOwn = $user->authorise('core.edit.own') && $item->created_user_id == $userId;
            $canChange = $user->authorise('core.edit.state') && $canCheckin;
            $grouplink = 'index.php?option=com_jem&amp;task=group.edit&amp;id=' . $item->groupid;

            if ($item->level > 0) {
                $repeat = $item->level - 1;
            } else {
                $repeat = 0;
            }
            ?>
            <tr class="row<?php echo $i % 2; ?>">
                <td class="center">
                    <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
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
                         id="color-desc-<?php echo $item->id; ?>"><?php echo ($item->color == '') ? 'transparent' : $item->color ?></div>
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
                    <?php echo $item->assignedevents; ?>
                </td>
                <td class="center">
                    <?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'categories.', $canChange); ?>
                </td>
                <td class="order">
                    <?php if ($canChange) : ?>
                        <?php $disabled = $saveOrder ? '' : 'disabled="disabled"'; ?>
                        <div style="display:-webkit-box">
                            <div><input type="text" style="text-align: center; margin: auto 0; min-width: 50px;" name="order[]"
                                        size="5" value="<?php echo $orderkey + 1; ?>" <?php echo $disabled ?>
                                        class="text-area-order"/></div>

                            <?php if ($saveOrder) :
                                if ($listDirn == 'asc') : ?>
                                    <div><?php if ($i) : ?>
                                            <span><?php echo $this->pagination->orderUpIcon($i, true, 'categories.orderup', 'JLIB_HTML_MOVE_UP', $ordering); ?></span>
                                        <?php else : ?>
                                            <div style='width:32px;'>&nbsp;</div>
                                        <?php endif; ?></div>
                                    <div><?php if ($countItems != $i + 1) : ?>
                                            <span><?php echo $this->pagination->orderDownIcon($i, $this->pagination->total, true, 'categories.orderdown', 'JLIB_HTML_MOVE_DOWN', $ordering); ?></span>
                                        <?php else : ?>
                                            <div style='width:32px;'>&nbsp;</div>
                                        <?php endif; ?></div>
                                <?php elseif ($listDirn == 'desc') : ?>
                                    <div><?php if ($i) : ?>
                                            <span><?php echo $this->pagination->orderUpIcon($i, true, 'categories.orderdown', 'JLIB_HTML_MOVE_UP', $ordering); ?></span>
                                        <?php else : ?>
                                            <div style='width:32px;'>&nbsp;</div>
                                        <?php endif; ?></div>
                                    <div><?php if ($countItems != $i + 1) : ?>
                                            <span><?php echo $this->pagination->orderDownIcon($i, $this->pagination->total, true, 'categories.orderup', 'JLIB_HTML_MOVE_DOWN', $ordering); ?></span>
                                        <?php else : ?>
                                            <div style='width:32px;'>&nbsp;</div>
                                        <?php endif; ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <?php echo $item->ordering; ?>
                    <?php endif; ?>
                    </td>
                    <td class="center">
                        <?php echo $this->escape($item->access_level); ?>
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
    </div>
    <?php //if (isset($this->sidebar)) : ?>
    <?php //endif; ?>

    <div>
        <input type="hidden" name="task" value=""/>
        <input type="hidden" name="boxchecked" value="0"/>
        <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>"/>
        <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>"/>
        <input type="hidden" name="original_order_values" value="<?php echo implode(',', $originalOrders); ?>"/>

        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
