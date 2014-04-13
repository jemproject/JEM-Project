<?php
/**
 * @version 1.9.6
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined ('_JEXEC') or die;
?>
<fieldset>
		<legend><?php echo JText::_('COM_JEM_ATTACHMENTS_LEGEND'); ?></legend>
		
<table class="adminform" id="el-attachments" width="100%">

	<tbody>
		<?php foreach ($this->item->attachments as $file): ?>
		<tr>
			<td width="100%"><div><div class="title"><?php echo JText::_('COM_JEM_ATTACHMENT_FILE'); ?></div><input class="readonly" type="text" readonly="readonly" value="<?php echo $file->file; ?>"><input type="hidden" name="attached-id[]" value="<?php echo $file->id; ?>"/></div>
			<div><div class="title"><?php echo JText::_('COM_JEM_ATTACHMENT_NAME'); ?></div><input type="text" name="attached-name[]" style="width: 100%" value="<?php echo $file->name; ?>" /></div>
			<div><div class="title"><?php echo JText::_('COM_JEM_ATTACHMENT_DESCRIPTION'); ?></div><input type="text" name="attached-desc[]" style="width: 100%" value="<?php echo $file->description; ?>" /></div>
			<td><div><div class="title"><?php echo JText::_('COM_JEM_ATTACHMENT_ACCESS'); ?></div><?php echo JHtml::_('select.genericlist', $this->access, 'attached-access[]', 'class="inputbox" size="7"', 'value', 'text', $file->access); ?></td></div>
			<td><?php echo JHtml::_('image','com_jem/publish_r.png', JText::_('COM_JEM_REMOVE_ATTACHEMENT'), array('id' => 'attach-remove'.$file->id,'class' => 'attach-remove'),true); ?></td>
		</tr>
		<?php endforeach; ?>
		<tr>
			<td width="100%">
				<div><div class="title"><?php echo JText::_('COM_JEM_ATTACHMENT_FILE'); ?></div><input type="file" name="attach[]" class="attach-field"></input></div>
			
				<div><div class="title"><?php echo JText::_('COM_JEM_ATTACHMENT_NAME'); ?><input type="text" name="attach-name[]" value="" /></div>
			
				<div><div class="title"><?php echo JText::_('COM_JEM_ATTACHMENT_DESCRIPTION'); ?><input type="text" name="attach-desc[]" value="" /></div>
			</td>
			<td>
				<div><div class="title"><?php echo JText::_('COM_JEM_ATTACHMENT_ACCESS'); ?></div>
				<?php echo JHtml::_('select.genericlist', $this->access, 'attach-access[]', 'class="inputbox" size="7"', 'value', 'text', 0); ?></div>
			</td>
			<td>&nbsp;</td>
		</tr>
	</tbody>
</table>
</fieldset>