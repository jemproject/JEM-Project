<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$user = JemFactory::getUser();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn = $this->escape($this->state->get('list.direction'));
$taxTypes = array('standard', 'reduced', 'zero', 'exempt', 'outside_scope');
?>
<form action="<?php echo Route::_('index.php?option=com_jem&view=taxrates'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <fieldset id="filter-bar" class="mb-3"><div class="d-flex flex-wrap gap-2">
            <div class="input-group" style="max-width:28rem">
                <input type="text" name="filter_search" class="form-control" value="<?php echo $this->escape($this->state->get('filter.search')); ?>" placeholder="<?php echo Text::_('COM_JEM_SEARCH'); ?>">
                <button class="btn btn-primary" type="submit"><span class="icon-search" aria-hidden="true"></span></button>
            </div>
            <select name="filter_state" class="form-select" style="width:auto" onchange="this.form.submit()">
                <option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?></option>
                <?php echo HTMLHelper::_('select.options', HTMLHelper::_('jgrid.publishedOptions'), 'value', 'text', $this->state->get('filter.state'), true); ?>
            </select>
            <select name="filter_tax_type" class="form-select" style="width:auto" onchange="this.form.submit()">
                <option value=""><?php echo Text::_('COM_JEM_TAX_RATE_FILTER_TYPE'); ?></option>
                <?php foreach ($taxTypes as $type) : ?><option value="<?php echo $type; ?>"<?php echo $this->state->get('filter.tax_type') === $type ? ' selected' : ''; ?>><?php echo Text::_('COM_JEM_TAX_TYPE_' . strtoupper($type)); ?></option><?php endforeach; ?>
            </select>
        </div></fieldset>

        <table class="table table-striped" id="taxrateList">
            <thead><tr>
                <th class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></th>
                <th><?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_TAX_RATE_FIELD_CODE', 'a.code', $listDirn, $listOrder); ?></th>
                <th><?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.name', $listDirn, $listOrder); ?></th>
                <th><?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_TAX_RATE_FIELD_TYPE', 'a.tax_type', $listDirn, $listOrder); ?></th>
                <th><?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_TAX_RATE_FIELD_RATE', 'a.rate', $listDirn, $listOrder); ?></th>
                <th><?php echo Text::_('COM_JEM_TAX_RATE_VALIDITY'); ?></th>
                <th class="w-1 text-center"><?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?></th>
                <th class="w-1"><?php echo Text::_('JGRID_HEADING_ID'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($this->items as $i => $item) :
                $canEdit = $user->authorise('core.edit', 'com_jem');
                $checkedOut = !empty($item->checked_out) && (int) $item->checked_out !== (int) $user->id;
            ?>
                <tr>
                    <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>
                    <td><code><?php echo $this->escape($item->code); ?></code></td>
                    <td><?php if ($canEdit && !$checkedOut) : ?><a href="<?php echo Route::_('index.php?option=com_jem&task=taxrate.edit&id=' . (int) $item->id); ?>"><?php echo $this->escape($item->name); ?></a><?php else : ?><?php echo $this->escape($item->name); ?><?php endif; ?></td>
                    <td><?php echo Text::_('COM_JEM_TAX_TYPE_' . strtoupper($item->tax_type)); ?></td>
                    <td><?php echo $this->escape((string) $item->rate); ?>%</td>
                    <td><?php echo $this->escape($item->valid_from ?: Text::_('COM_JEM_TAX_RATE_OPEN')); ?> &ndash; <?php echo $this->escape($item->valid_until ?: Text::_('COM_JEM_TAX_RATE_OPEN')); ?></td>
                    <td class="text-center"><?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'taxrates.', $user->authorise('core.edit.state', 'com_jem')); ?></td>
                    <td><?php echo (int) $item->id; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php echo $this->pagination->getListFooter(); ?>
    </div>
    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>">
    <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
