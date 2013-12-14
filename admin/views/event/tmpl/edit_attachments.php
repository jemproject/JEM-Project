<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */


defined ('_JEXEC') or die;
?>

<table class="adminform" id="el-attachments">
	<thead>
		<tr>
			<th style="width:25%"><?php echo JText::_('COM_JEM_ATTACHMENT_FILE'); ?></th>
			<th style="width:15%"><?php echo JText::_('COM_JEM_ATTACHMENT_NAME'); ?></th>
			<th style="width:40%"><?php echo JText::_('COM_JEM_ATTACHMENT_DESCRIPTION'); ?></th>
			<th style="width:20px"><?php echo JText::_('COM_JEM_ATTACHMENT_ACCESS'); ?></th>
			<th style="width:5px">&nbsp;</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($this->item->attachments as $file): ?>
		<tr>
			<td><input class="readonly" type="text" readonly="readonly" value="<?php echo $file->file; ?>"></input><input type="hidden" name="attached-id[]" value="<?php echo $file->id; ?>"/></td>
			<td><input type="text" name="attached-name[]" style="width: 100%" value="<?php echo $file->name; ?>" /></td>
			<td><input type="text" name="attached-desc[]" style="width: 100%" value="<?php echo $file->description; ?>" /></td>
			<td><?php echo JHtml::_('select.genericlist', $this->access, 'attached-access[]', 'class="inputbox" size="3"', 'value', 'text', $file->access); ?></td>
			<td><?php echo JHtml::_('image','com_jem/publish_x.png', JText::_('COM_JEM_REMOVE_ATTACHEMENT'), array('id' => 'attach-remove'.$file->id,'class' => 'attach-remove'),true); ?></td>
		</tr>
		<?php endforeach; ?>
		<tr>
			<td>
				<input type="file" name="attach[]" class="attach-field"></input>
			</td>
			<td>
				<input type="text" name="attach-name[]" value="" style="width: 100%" />
			</td>
			<td>
				<input type="text" name="attach-desc[]" value="" style="width: 100%" />
			</td>
			<td>
				<?php echo JHtml::_('select.genericlist', $this->access, 'attach-access[]', 'class="inputbox" size="3"', 'value', 'text', 0); ?>
			</td>
			<td>&nbsp;</td>
		</tr>
	</tbody>
</table>

