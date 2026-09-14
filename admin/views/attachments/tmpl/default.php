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
use Joomla\CMS\Layout\LayoutHelper;

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));

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
        <?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

        <table class="table table-striped itemList" id="attachmentList">
            <thead>
                <tr>
                    <th style="width:1%" class="center">
                        <input type="checkbox" name="checkall-toggle" value=""
                               title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>"
                               onclick="Joomla.checkAll(this)" />
                    </th>
                    <th style="width:8%" class="center">
                        <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'linked_published', $listDirn, $listOrder); ?>
                    </th>
                    <th class="title">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_ATTACHMENT_FILE', 'a.file', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:12%">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_ATTACHMENT_OBJECT_TYPE', 'object_type', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:18%">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_ATTACHMENT_LINKED_ITEM', 'linked_title', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:10%">
                        <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ACCESS', 'access_level', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:8%" class="center">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_ATTACHMENT_FRONTEND', 'a.frontend', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:8%" class="center">
                        <?php echo Text::_('COM_JEM_ATTACHMENT_FILE_STATUS'); ?>
                    </th>
                    <th style="width:8%" class="center">
                        <?php echo Text::_('COM_JEM_ATTACHMENT_FILE_SIZE'); ?>
                    </th>
                    <th style="width:7%" class="center">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_ATTACHMENT_DOWNLOADS', 'a.downloads', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:11%" class="nowrap">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_ATTACHMENT_LAST_DOWNLOAD', 'a.last_download', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:12%">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_CREATION', 'a.created', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width:5%" class="center">
                        <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                    </th>
                    <th class="center jem-attachment-download-action">
                        <span class="visually-hidden"><?php echo Text::_('COM_JEM_ATTACHMENT_DOWNLOAD'); ?></span>
                    </th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($this->items as $i => $item) : ?>
                <?php
                $link = $linkedUrl($item);
                $title = $item->linked_title ?: $item->object;
                $editUrl = Route::_('index.php?option=com_jem&task=attachment.edit&id=' . (int) $item->id);
                $downloadUrl = Route::_('index.php?option=com_jem&task=attachments.download&id=' . (int) $item->id);
                ?>
                <tr class="row<?php echo $i % 2; ?>">
                    <td class="center">
                        <?php if ($item->canEdit) : ?>
                            <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                        <?php endif; ?>
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
                                <?php if ($item->canEdit) : ?>
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
                        <?php if ($item->canEdit && $link && $item->linked_title) : ?>
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
                    <td class="center jem-attachment-download-action">
                        <?php if ($item->file_exists) : ?>
                            <a class="btn btn-sm btn-outline-secondary hasTooltip" href="<?php echo $downloadUrl; ?>" title="<?php echo Text::_('COM_JEM_ATTACHMENT_DOWNLOAD'); ?>">
                                <span class="icon-download" aria-hidden="true"></span>
                                <span class="visually-hidden"><?php echo Text::_('COM_JEM_ATTACHMENT_DOWNLOAD'); ?></span>
                            </a>
                        <?php else : ?>
                            -
                        <?php endif; ?>
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
        <?php echo $this->filterForm->renderControlFields(); ?>
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
