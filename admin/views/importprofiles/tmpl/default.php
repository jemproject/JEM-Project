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

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));

$contextLabels = array(
    'events'      => Text::_('COM_JEM_IMPORT_PROFILE_CONTEXT_EVENTS'),
    'venues'      => Text::_('COM_JEM_IMPORT_PROFILE_CONTEXT_VENUES'),
    'specialdays' => Text::_('COM_JEM_IMPORT_PROFILE_CONTEXT_SPECIALDAYS'),
);

$renderMapping = static function ($mapping) {
    $decoded = json_decode((string) $mapping, true);

    if (!is_array($decoded) || empty($decoded)) {
        return '<span class="text-muted">-</span>';
    }

    $pairs = array();
    foreach ($decoded as $source => $target) {
        if (is_array($target)) {
            $target = implode(', ', array_filter(array_map('trim', $target)));
        }

        $source = trim((string) $source);
        $target = trim((string) $target);

        if ($source === '' || $target === '') {
            continue;
        }

        $pairs[] = '<span class="badge bg-light text-dark border me-1 mb-1">'
            . htmlspecialchars($source, ENT_QUOTES, 'UTF-8')
            . ' &rarr; '
            . htmlspecialchars($target, ENT_QUOTES, 'UTF-8')
            . '</span>';
    }

    return $pairs ? implode(' ', $pairs) : '<span class="text-muted">-</span>';
};
?>

<form action="<?php echo Route::_('index.php?option=com_jem&view=importprofiles'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <fieldset id="filter-bar" class="mb-3">
            <div class="jem-admin-filter-bar">
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
                                onclick="document.getElementById('filter_search').value='';this.form.filter_context.value='';this.form.filter_format.value='';this.form.filter_access.value='0';this.form.submit();">
                            <?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>
                        </button>
                    </div>
                </div>
                <div class="jem-admin-filter-item">
                    <select name="filter_context" class="form-select" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('COM_JEM_IMPORT_PROFILE_FILTER_CONTEXT'); ?></option>
                        <?php foreach ($this->contexts as $context) : ?>
                            <option value="<?php echo $this->escape($context); ?>" <?php echo $this->state->get('filter.context') === $context ? 'selected' : ''; ?>>
                                <?php echo $contextLabels[$context] ?? $this->escape($context); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="jem-admin-filter-item">
                    <select name="filter_format" class="form-select" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('COM_JEM_IMPORT_PROFILE_FILTER_FORMAT'); ?></option>
                        <?php foreach ($this->formats as $format) : ?>
                            <option value="<?php echo $this->escape($format); ?>" <?php echo $this->state->get('filter.format') === $format ? 'selected' : ''; ?>>
                                <?php echo strtoupper($this->escape($format)); ?>
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

        <table class="table table-striped" id="importProfileList">
            <thead>
                <tr>
                    <th style="width:1%" class="center">
                        <input type="checkbox" name="checkall-toggle" value=""
                               title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>"
                               onclick="Joomla.checkAll(this)" />
                    </th>
                    <th class="title">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_IMPORT_PROFILE_NAME', 'a.title', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:10%">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_IMPORT_PROFILE_CONTEXT', 'a.context', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:8%">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_IMPORT_PROFILE_FORMAT', 'a.source_format', $listDirn, $listOrder); ?>
                    </th>
                    <th>
                        <?php echo Text::_('COM_JEM_IMPORT_PROFILE_MAPPING'); ?>
                    </th>
                    <th style="width:10%">
                        <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ACCESS', 'access_level', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:8%" class="center">
                        <?php echo HTMLHelper::_('grid.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:10%">
                        <?php echo HTMLHelper::_('grid.sort', 'JDATE', 'a.modified', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:5%" class="center">
                        <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($this->items as $i => $item) : ?>
                <tr class="row<?php echo $i % 2; ?>">
                    <td class="center">
                        <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                    </td>
                    <td>
                        <strong><?php echo $this->escape($item->title); ?></strong>
                    </td>
                    <td>
                        <?php echo $contextLabels[$item->context] ?? $this->escape($item->context); ?>
                    </td>
                    <td>
                        <?php echo strtoupper($this->escape($item->source_format)); ?>
                    </td>
                    <td>
                        <?php echo $renderMapping($item->mapping); ?>
                    </td>
                    <td>
                        <?php echo $this->escape($item->access_level); ?>
                    </td>
                    <td class="center">
                        <?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'importprofiles.', false); ?>
                    </td>
                    <td>
                        <?php echo $item->modified ? HTMLHelper::_('date', $item->modified, Text::_('DATE_FORMAT_LC4')) : HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC4')); ?>
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
