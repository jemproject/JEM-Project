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

HTMLHelper::_('behavior.formvalidator');

$typeLabels = array(
    'event'    => Text::_('COM_JEM_ATTACHMENT_OBJECT_EVENT'),
    'venue'    => Text::_('COM_JEM_ATTACHMENT_OBJECT_VENUE'),
    'category' => Text::_('COM_JEM_ATTACHMENT_OBJECT_CATEGORY'),
);

$fileInfo = $this->fileInfo ?: (object) array(
    'extension' => 'file',
    'type' => 'generic',
    'path_safe' => false,
    'exists' => false,
    'size' => null,
    'modified' => null,
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
?>

<form action="<?php echo Route::_('index.php?option=com_jem&layout=edit&id=' . (int) $this->item->id); ?>"
      method="post" name="adminForm" id="adminForm" class="form-validate">

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h3 mb-3">
                        <?php echo empty($this->item->id) ? Text::_('COM_JEM_ATTACHMENT_ADD') : Text::_('COM_JEM_ATTACHMENT_EDIT'); ?>
                    </h2>

                    <div class="mb-3">
                        <?php echo $this->form->renderField('file'); ?>
                    </div>

                    <div class="mb-3">
                        <?php echo $this->form->renderField('object'); ?>
                    </div>

                    <div class="mb-3">
                        <?php echo $this->form->renderField('name'); ?>
                    </div>

                    <div class="mb-3">
                        <?php echo $this->form->renderField('description'); ?>
                    </div>

                    <div class="mb-3">
                        <?php echo $this->form->renderField('icon'); ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <?php echo $this->form->renderField('frontend'); ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?php echo $this->form->renderField('access'); ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?php echo $this->form->renderField('ordering'); ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?php echo $this->form->renderField('created'); ?>
                        </div>
                        <div class="col-md-6 mb-0">
                            <?php echo $this->form->renderField('created_by'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-3 jem-attachment-file-panel">
                <div class="card-body">
                    <div class="jem-attachment-file-summary">
                        <span class="jem-attachment-extension-icon jem-attachment-extension-icon-<?php echo $this->escape($fileInfo->type); ?>" aria-hidden="true">
                            <?php echo $this->escape(strtoupper(substr($fileInfo->extension, 0, 4))); ?>
                        </span>
                        <div class="jem-attachment-file-summary-text">
                            <strong class="jem-attachment-file-name"><?php echo $this->escape($this->item->file); ?></strong>
                            <small class="text-muted"><?php echo Text::_('COM_JEM_ATTACHMENT_FILE_METADATA'); ?></small>
                        </div>
                    </div>

                    <dl class="jem-attachment-file-details">
                        <dt><?php echo Text::_('COM_JEM_ATTACHMENT_FILE_EXTENSION'); ?></dt>
                        <dd><?php echo $this->escape(strtoupper($fileInfo->extension)); ?></dd>

                        <dt><?php echo Text::_('COM_JEM_ATTACHMENT_FILE_SIZE'); ?></dt>
                        <dd><?php echo $formatBytes($fileInfo->size); ?></dd>

                        <dt><?php echo Text::_('COM_JEM_ATTACHMENT_FILE_MODIFIED'); ?></dt>
                        <dd>
                            <?php echo $fileInfo->modified ? HTMLHelper::_('date', date('Y-m-d H:i:s', $fileInfo->modified), Text::_('DATE_FORMAT_LC5')) : '-'; ?>
                        </dd>

                        <dt><?php echo Text::_('COM_JEM_ATTACHMENT_FILE_STATUS'); ?></dt>
                        <dd>
                            <?php if (!$fileInfo->path_safe) : ?>
                                <span class="badge bg-danger"><?php echo Text::_('COM_JEM_ATTACHMENT_FILE_UNSAFE'); ?></span>
                            <?php elseif ($fileInfo->exists) : ?>
                                <span class="badge bg-success"><?php echo Text::_('COM_JEM_ATTACHMENT_FILE_EXISTS'); ?></span>
                            <?php else : ?>
                                <span class="badge bg-warning text-dark"><?php echo Text::_('COM_JEM_ATTACHMENT_FILE_MISSING'); ?></span>
                            <?php endif; ?>
                        </dd>
                    </dl>
                </div>
            </div>

            <?php if ($this->linkedItem) : ?>
                <div class="card mb-3 jem-attachment-linked-item-panel">
                    <div class="card-body">
                        <h3 class="h5 mb-3"><?php echo Text::_('COM_JEM_ATTACHMENT_LINKED_ITEM'); ?></h3>
                        <div class="mb-3">
                            <strong>
                                <?php echo $this->escape($typeLabels[$this->linkedItem->type] ?? $this->linkedItem->type); ?>
                                #<?php echo (int) $this->linkedItem->id; ?>
                            </strong>
                            <br>
                            <?php echo $this->escape($this->linkedItem->title); ?>
                        </div>
                        <?php if (!empty($this->linkedItem->edit_link)) : ?>
                            <a class="btn btn-primary jem-attachment-linked-item-button w-100" href="<?php echo Route::_($this->linkedItem->edit_link); ?>">
                                <span class="icon-edit" aria-hidden="true"></span>
                                <?php echo Text::_('JACTION_EDIT') . ' ' . Text::_('COM_JEM_ATTACHMENT_LINKED_ITEM'); ?>
                            </a>
                        <?php else : ?>
                            <span class="badge bg-warning text-dark"><?php echo Text::_('COM_JEM_ATTACHMENT_ORPHANED'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php echo $this->form->getInput('id'); ?>
    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
