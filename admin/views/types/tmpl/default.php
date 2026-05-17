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

$user      = JemFactory::getUser();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$canEdit   = $user->authorise('core.edit', 'com_jem');
$canEditState = $user->authorise('core.edit.state', 'com_jem');

$entityLabels = array(
    1 => Text::_('COM_JEM_TYPE_ENTITY_EVENT'),
    2 => Text::_('COM_JEM_TYPE_ENTITY_CATEGORY'),
    3 => Text::_('COM_JEM_TYPE_ENTITY_VENUE'),
);
?>

<form action="<?php echo Route::_('index.php?option=com_jem&view=types'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">

        <!-- Filter bar -->
        <fieldset id="filter-bar" class="mb-3">
            <div class="row">
                <div class="col-md-11">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" name="filter_search" id="filter_search" class="form-control"
                                       placeholder="<?php echo Text::_('COM_JEM_SEARCH'); ?>"
                                       value="<?php echo $this->escape($this->state->get('filter_search')); ?>"
                                       onchange="document.adminForm.submit();" />
                                <button type="submit" class="btn btn-primary">
                                    <span class="icon-search" aria-hidden="true"></span>
                                </button>
                                <button type="button" class="btn btn-primary"
                                        onclick="document.getElementById('filter_search').value='';this.form.submit();">
                                    <?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select name="filter_state" class="form-select" onchange="this.form.submit()">
                                <option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?></option>
                                <?php echo HTMLHelper::_('select.options', HTMLHelper::_('jgrid.publishedOptions', array('all' => 0, 'archived' => 0, 'trash' => 0)), 'value', 'text', $this->state->get('filter_state'), true); ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="filter_entity" class="form-select" onchange="this.form.submit()">
                                <option value="0"><?php echo Text::_('COM_JEM_TYPE_FILTER_ENTITY'); ?></option>
                                <option value="1" <?php echo $this->state->get('filter_entity') == 1 ? 'selected' : ''; ?>><?php echo Text::_('COM_JEM_TYPE_ENTITY_EVENT'); ?></option>
                                <option value="2" <?php echo $this->state->get('filter_entity') == 2 ? 'selected' : ''; ?>><?php echo Text::_('COM_JEM_TYPE_ENTITY_CATEGORY'); ?></option>
                                <option value="3" <?php echo $this->state->get('filter_entity') == 3 ? 'selected' : ''; ?>><?php echo Text::_('COM_JEM_TYPE_ENTITY_VENUE'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-md-1">
                    <div class="float-end">
                        <?php echo $this->pagination->getLimitBox(); ?>
                    </div>
                </div>
            </div>
        </fieldset>

        <table class="table table-striped" id="typeList">
            <thead>
                <tr>
                    <th style="width:1%" class="center">
                        <input type="checkbox" name="checkall-toggle" value=""
                               title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>"
                               onclick="Joomla.checkAll(this)" />
                    </th>
                    <th class="title">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TYPE_FIELD_NAME', 'a.name', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:12%">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TYPE_FIELD_ENTITY', 'a.entity', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:6%" class="center">
                        <?php echo Text::_('COM_JEM_TYPE_FIELD_ICON'); ?>
                    </th>
                    <th style="width:6%" class="center">
                        <?php echo Text::_('COM_JEM_TYPE_FIELD_COLOR'); ?>
                    </th>
                    <th style="width:8%" class="center">
                        <?php echo HTMLHelper::_('grid.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:5%" class="center">
                        <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($this->items as $i => $item) : ?>
                <?php $editUrl = Route::_('index.php?option=com_jem&task=type.edit&id=' . $item->id); ?>
                <tr class="row<?php echo $i % 2; ?>">
                    <td class="center">
                        <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                    </td>
                    <td>
                        <?php if ($canEdit) : ?>
                            <a href="<?php echo $editUrl; ?>">
                                <?php echo $this->escape($item->name); ?>
                            </a>
                        <?php else : ?>
                            <?php echo $this->escape($item->name); ?>
                        <?php endif; ?>
                        <?php if ($item->description) : ?>
                            <br><small class="text-muted"><?php echo $this->escape($item->description); ?></small>
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
                    <td class="center">
                        <?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'types.', $canEditState); ?>
                    </td>
                    <td class="center">
                        <?php echo $item->id; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($this->items)) : ?>
                <tr><td colspan="7" class="center"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td></tr>
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
