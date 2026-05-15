<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;

$jemsettings      = JemHelper::config();
$attachmentTypes  = array_filter(array_map('trim', explode(',', (string) $jemsettings->attachments_types)));
$attachmentAccept = implode(',', array_map(static function ($extension) {
    return '.' . ltrim(strtolower($extension), '.');
}, $attachmentTypes));
$publishedOptions = array(
    HTMLHelper::_('select.option', 1, Text::_('JPUBLISHED')),
    HTMLHelper::_('select.option', 0, Text::_('JUNPUBLISHED')),
);
$attachments = is_array($this->item->attachments ?? null) ? $this->item->attachments : array();
?>

<div class="jem-attachments-tab">
<?php if (isset($this->form)) : ?>
    <div class="jem-attachments-global-options">
        <?php echo $this->form->renderField('attachments_layout', 'attribs'); ?>
    </div>
<?php endif; ?>
<div class="btn-toolbar jem-attachments-toolbar">
    <div class="btn-group">
        <button type="button" class="btn btn-sm button btn-success attachment-add" aria-label="<?php echo Text::_('JTOOLBAR_NEW'); ?>">
            <span class="icon-plus icon-white" aria-hidden="true"></span>
        </button>
    </div>
</div>
<table class="adminform" id="el-attachments">
    <tbody>
        <?php foreach ($attachments as $i => $file): ?>
        <tr class="jem-attachment-row jem-attachment-existing-row">
            <td>
                <div>
                    <div class="title"><?php echo Text::_('COM_JEM_ATTACHMENT_FILE');?></div>
                    <input type="text" readonly="readonly" value="<?php echo $this->escape($file->file); ?>" class="form-control readonly valid form-control-success w-75">
                    <input type="hidden" name="attached-id[]" value="<?php echo (int) $file->id; ?>"/>
                    <input type="hidden" name="attached-order[]" class="attachment-order" value="<?php echo (int) $i; ?>"/>
                </div>
                <div>
                    <div class="title"><?php echo Text::_('COM_JEM_ATTACHMENT_NAME'); ?></div>
                    <input type="text" name="attached-name[]" class="form-control valid form-control-success w-75" value="<?php echo $this->escape($file->name); ?>" />
                </div>
                <div>
                    <div class="title"><?php echo Text::_('COM_JEM_ATTACHMENT_DESCRIPTION'); ?></div>
                    <input type="text" name="attached-desc[]" class="form-control valid form-control-success w-75" value="<?php echo $this->escape($file->description); ?>" />
                </div>
                <div>
                    <div class="title"><?php echo Text::_('JSTATUS'); ?></div>
                    <?php echo HTMLHelper::_('select.genericlist', $publishedOptions, 'attached-frontend[]', array('class'=>'form-select inputbox attachment-published'), 'value', 'text', (int) $file->frontend); ?>
                </div>
            </td>
            <td>
                <div>
                    <div class="title"><?php echo Text::_('COM_JEM_ATTACHMENT_ACCESS'); ?></div>
                    <?php echo HTMLHelper::_('select.genericlist', $this->access, 'attached-access[]', array('class'=>'inputbox form-control','size'=>'7'), 'value', 'text', $file->access); ?>
                </div>
            </td>
            <td class="center jem-attachment-actions">
                <button type="button" class="btn btn-sm btn-primary attachment-move-up" aria-label="<?php echo Text::_('JLIB_HTML_MOVE_UP'); ?>"><span class="icon-chevron-up" aria-hidden="true"></span></button>
                <button type="button" class="btn btn-sm btn-primary attachment-move-down" aria-label="<?php echo Text::_('JLIB_HTML_MOVE_DOWN'); ?>"><span class="icon-chevron-down" aria-hidden="true"></span></button>
                <button type="button" id="attach-remove<?php echo (int) $file->id; ?>:<?php echo Session::getFormToken(); ?>" class="btn btn-sm btn-danger attach-remove" title="<?php echo Text::_('COM_JEM_REMOVE_ATTACHEMENT'); ?>" aria-label="<?php echo Text::_('COM_JEM_REMOVE_ATTACHEMENT'); ?>"><span class="icon-minus icon-white" aria-hidden="true"></span></button>
            </td>
        </tr>
        <?php endforeach; ?>
        <tr class="jem-attachment-row jem-attachment-upload-row jem-attachment-template-row d-none" aria-hidden="true" hidden>
            <td style="width: 100%;">
                <div style="display: inline-block; text-wrap: none;">
                    <div class="title"><?php echo Text::_('COM_JEM_ATTACHMENT_FILE'); ?></div>
                    <input type="file" name="attach[]" class="attach-field" accept="<?php echo $this->escape($attachmentAccept); ?>" disabled="disabled">
                    <input type="hidden" name="attach-order[]" class="attachment-order" value="<?php echo count($attachments); ?>" disabled="disabled">
                </div>
                <div>
                    <div class="title"><?php echo Text::_('COM_JEM_ATTACHMENT_NAME'); ?></div>
                    <input type="text" name="attach-name[]" value="" class="form-control valid form-control-success w-75 attach-name" disabled="disabled" />
                </div>
                <div>
                    <div class="title"><?php echo Text::_('COM_JEM_ATTACHMENT_DESCRIPTION'); ?></div>
                    <input type="text" name="attach-desc[]" value="" class="form-control valid form-control-success w-75 attach-desc" disabled="disabled" />
                </div>
                <div class="jem-attachment-status-row">
                    <div class="title"><?php echo Text::_('JSTATUS'); ?></div>
                    <?php echo HTMLHelper::_('select.genericlist', $publishedOptions, 'attach-frontend[]', array('class'=>'form-select inputbox attachment-published', 'disabled'=>'disabled'), 'value', 'text', 1); ?>
                    <button type="button" class="btn btn-primary clear-attach-field"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
                </div>
            </td>
            <td>
                <div>
                    <div class="title"><?php echo Text::_('COM_JEM_ATTACHMENT_ACCESS'); ?></div>
                    <?php echo HTMLHelper::_('select.genericlist', $this->access, 'attach-access[]', array('class'=>'inputbox form-control','size'=>'7', 'disabled'=>'disabled'), 'value', 'text', 1); ?>
                </div>
            </td>
            <td class="center jem-attachment-actions">
                <button type="button" class="btn btn-sm btn-primary attachment-move-up" aria-label="<?php echo Text::_('JLIB_HTML_MOVE_UP'); ?>"><span class="icon-chevron-up" aria-hidden="true"></span></button>
                <button type="button" class="btn btn-sm btn-primary attachment-move-down" aria-label="<?php echo Text::_('JLIB_HTML_MOVE_DOWN'); ?>"><span class="icon-chevron-down" aria-hidden="true"></span></button>
                <button type="button" class="btn btn-sm btn-danger attachment-remove-row" aria-label="<?php echo Text::_('JACTION_DELETE'); ?>"><span class="icon-minus icon-white" aria-hidden="true"></span></button>
            </td>
        </tr>
    </tbody>
</table>
</div>

