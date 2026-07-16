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

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$canEdit   = JemFactory::getUser()->authorise('core.edit', 'com_jem');

$objectTypeLabels = array(
    'event'    => Text::_('COM_JEM_ATTACHMENT_OBJECT_EVENT'),
    'venue'    => Text::_('COM_JEM_ATTACHMENT_OBJECT_VENUE'),
    'category' => Text::_('COM_JEM_ATTACHMENT_OBJECT_CATEGORY'),
    'other'    => Text::_('COM_JEM_ATTACHMENT_OBJECT_OTHER'),
);

$formatBytes = function ($bytes) {
    if ($bytes === null) {
        return '-';
    }

    $bytes = (int) $bytes;
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    if ($bytes < 1048576) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return number_format($bytes / 1048576, 1) . ' MB';
};

$linkedUrl = function ($item) {
    switch ($item->object_type) {
        case 'event':
            return 'index.php?option=com_jem&task=event.edit&id=' . (int) $item->object_id;
        case 'venue':
            return 'index.php?option=com_jem&task=venue.edit&id=' . (int) $item->object_id;
        case 'category':
            return 'index.php?option=com_jem&task=category.edit&id=' . (int) $item->object_id;
    }

    return '';
};

$fileType = function ($filename) {
    $extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));

    if (in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'))) {
        return 'image';
    }

    if (in_array($extension, array('doc', 'docx', 'odt', 'rtf'))) {
        return 'document';
    }

    if (in_array($extension, array('xls', 'xlsx', 'ods', 'csv'))) {
        return 'spreadsheet';
    }

    if (in_array($extension, array('zip', 'rar', '7z', 'tar', 'gz'))) {
        return 'archive';
    }

    if ($extension === 'pdf') {
        return 'pdf';
    }

    if ($extension === 'txt') {
        return 'text';
    }

    return 'generic';
};

$fileExtension = function ($filename) {
    $extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
    $extension = $extension !== '' ? preg_replace('/[^a-z0-9]/', '', $extension) : 'file';

    return strtoupper(substr($extension, 0, 4));
};
?>

<form action="<?php echo Route::_('index.php?option=com_jem&view=attachments'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
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
                            <select name="filter_type" class="form-select" onchange="this.form.submit()">
                                <option value=""><?php echo Text::_('COM_JEM_ATTACHMENT_FILTER_OBJECT_TYPE'); ?></option>
                                <option value="event" <?php echo $this->state->get('filter_type') === 'event' ? 'selected' : ''; ?>><?php echo Text::_('COM_JEM_ATTACHMENT_OBJECT_EVENT'); ?></option>
                                <option value="venue" <?php echo $this->state->get('filter_type') === 'venue' ? 'selected' : ''; ?>><?php echo Text::_('COM_JEM_ATTACHMENT_OBJECT_VENUE'); ?></option>
                                <option value="category" <?php echo $this->state->get('filter_type') === 'category' ? 'selected' : ''; ?>><?php echo Text::_('COM_JEM_ATTACHMENT_OBJECT_CATEGORY'); ?></option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="filter_access" class="form-select" onchange="this.form.submit()">
                                <option value=""><?php echo Text::_('JOPTION_SELECT_ACCESS'); ?></option>
                                <?php echo HTMLHelper::_('select.options', HTMLHelper::_('access.assetgroups'), 'value', 'text', $this->state->get('filter.access')); ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="filter_frontend" class="form-select" onchange="this.form.submit()">
                                <option value=""><?php echo Text::_('COM_JEM_ATTACHMENT_FILTER_FRONTEND'); ?></option>
                                <option value="1" <?php echo $this->state->get('filter_frontend') === '1' ? 'selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
                                <option value="0" <?php echo $this->state->get('filter_frontend') === '0' ? 'selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
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

        <table class="table table-striped itemList" id="attachmentList">
            <thead>
                <tr>
                    <th style="width:1%" class="center">
                        <input type="checkbox" name="checkall-toggle" value=""
                               title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>"
                               onclick="Joomla.checkAll(this)" />
                    </th>
                    <th style="width:8%" class="center">
                        <?php echo HTMLHelper::_('grid.sort', 'JSTATUS', 'linked_published', $listDirn, $listOrder); ?>
                    </th>
                    <th class="title">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ATTACHMENT_FILE', 'a.file', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:12%">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ATTACHMENT_OBJECT_TYPE', 'object_type', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:18%">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ATTACHMENT_LINKED_ITEM', 'linked_title', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:10%">
                        <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ACCESS', 'access_level', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:8%" class="center">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ATTACHMENT_FRONTEND', 'a.frontend', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:8%" class="center">
                        <?php echo Text::_('COM_JEM_ATTACHMENT_FILE_STATUS'); ?>
                    </th>
                    <th style="width:8%" class="center">
                        <?php echo Text::_('COM_JEM_ATTACHMENT_FILE_SIZE'); ?>
                    </th>
                    <th style="width:5%" class="center">
                        <?php echo Text::_('COM_JEM_ATTACHMENT_DOWNLOAD'); ?>
                    </th>
                    <th style="width:7%" class="center">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ATTACHMENT_DOWNLOADS', 'a.downloads', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:11%" class="nowrap">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ATTACHMENT_LAST_DOWNLOAD', 'a.last_download', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:12%">
                        <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_CREATION', 'a.created', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:5%" class="center">
                        <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($this->items as $i => $item) : ?>
                <?php
                $link = $linkedUrl($item);
                $title = $item->linked_title ?: $item->object;
                $editUrl = Route::_('index.php?option=com_jem&task=attachment.edit&id=' . (int) $item->id);
                $downloadUrl = Route::_('index.php?option=com_jem&task=attachments.download&id=' . (int) $item->id . '&' . Session::getFormToken() . '=1');
                ?>
                <tr class="row<?php echo $i % 2; ?>">
                    <td class="center">
                        <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                    </td>
                    <td class="center">
                        <?php
                        if ($item->linked_published === null) {
                            echo '<span class="badge bg-warning text-dark">' . Text::_('COM_JEM_ATTACHMENT_ORPHANED') . '</span>';
                        } else {
                            echo HTMLHelper::_('jgrid.published', (int) $item->linked_published, $i, 'attachments.', false);
                        }
                        ?>
                    </td>
                    <td>
                        <div class="jem-attachment-file-cell">
                            <span class="jem-attachment-extension-icon jem-attachment-extension-icon-small jem-attachment-extension-icon-<?php echo $this->escape($fileType($item->file)); ?>" aria-hidden="true">
                                <?php echo $this->escape($fileExtension($item->file)); ?>
                            </span>
                            <div class="jem-attachment-file-meta">
                                <?php if ($canEdit) : ?>
                                    <a class="jem-attachment-file-name" href="<?php echo $editUrl; ?>"><strong><?php echo $this->escape($item->file); ?></strong></a>
                                <?php else : ?>
                                    <strong class="jem-attachment-file-name"><?php echo $this->escape($item->file); ?></strong>
                                <?php endif; ?>
                                <?php if ($item->name) : ?>
                                    <br><small class="text-muted"><?php echo $this->escape($item->name); ?></small>
                                <?php endif; ?>
                                <?php if ($item->description) : ?>
                                    <br><small class="text-muted"><?php echo $this->escape($item->description); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php echo $this->escape($objectTypeLabels[$item->object_type] ?? $item->object_type); ?>
                    </td>
                    <td>
                        <?php if ($link && $item->linked_title) : ?>
                            <a href="<?php echo Route::_($link); ?>"><?php echo $this->escape($title); ?></a>
                        <?php else : ?>
                            <?php echo $this->escape($title); ?>
                        <?php endif; ?>
                        <br><small class="text-muted"><?php echo $this->escape($item->object); ?></small>
                    </td>
                    <td>
                        <?php echo $this->escape($item->access_level); ?>
                    </td>
                    <td class="center">
                        <?php echo ((int) $item->frontend === 1) ? Text::_('JYES') : Text::_('JNO'); ?>
                    </td>
                    <td class="center">
                        <?php if (!$item->file_path_safe) : ?>
                            <span class="badge bg-danger"><?php echo Text::_('COM_JEM_ATTACHMENT_FILE_UNSAFE'); ?></span>
                        <?php elseif ($item->file_exists) : ?>
                            <span class="badge bg-success"><?php echo Text::_('COM_JEM_ATTACHMENT_FILE_EXISTS'); ?></span>
                        <?php else : ?>
                            <span class="badge bg-warning text-dark"><?php echo Text::_('COM_JEM_ATTACHMENT_FILE_MISSING'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="center">
                        <?php echo $formatBytes($item->file_size); ?>
                    </td>
                    <td class="center">
                        <?php if ($item->file_exists) : ?>
                            <a class="btn btn-sm btn-outline-secondary hasTooltip" href="<?php echo $downloadUrl; ?>" title="<?php echo Text::_('COM_JEM_ATTACHMENT_DOWNLOAD'); ?>">
                                <span class="icon-download" aria-hidden="true"></span>
                                <span class="visually-hidden"><?php echo Text::_('COM_JEM_ATTACHMENT_DOWNLOAD'); ?></span>
                            </a>
                        <?php else : ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="center">
                        <?php echo (int) $item->downloads; ?>
                    </td>
                    <td class="nowrap">
                        <?php echo $item->last_download ? HTMLHelper::_('date', $item->last_download, Text::_('DATE_FORMAT_LC5')) : '-'; ?>
                    </td>
                    <td>
                        <?php echo $item->created ? HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC5')) : '-'; ?>
                        <?php if ($item->created_by_name) : ?>
                            <br><small class="text-muted"><?php echo $this->escape($item->created_by_name); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="center">
                        <?php echo (int) $item->id; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($this->items)) : ?>
                <tr><td colspan="14" class="center"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td></tr>
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
