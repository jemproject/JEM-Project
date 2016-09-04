<?php
/**
 * @version 2.1.7
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined ('_JEXEC') or die;
?>
<fieldset class="jem_fldst_attachments">
<legend><?php echo JText::_('COM_JEM_EVENT_ATTACHMENTS_TAB'); ?></legend>
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
		<?php foreach ($this->row->attachments as $file): ?>
		<tr>
			<td><?php echo wordwrap($file->file, 30, "<br>", true); ?><input style="width:200px" type="hidden" name="attached-id[]" value="<?php echo $file->id; ?>"/></td>
			<td><input type="text" name="attached-name[]" value="<?php echo $file->name; ?>" style="width:100px" /></td>
			<td><input type="text" name="attached-desc[]" value="<?php echo $file->description; ?>" style="width:100px" /></td>
			<td><?php echo JHtml::_('select.genericlist', $this->access, 'attached-access[]', array('class'=>'inputbox','style'=>'width:100px;','size'=>'3'), 'value', 'text', $file->access); ?></td>
			<td><?php echo JHtml::_('image','com_jem/publish_r.png', JText::_('COM_JEM_GLOBAL_REMOVE_ATTACHEMENT'), array('id' => 'attach-remove'.$file->id.':'.JSession::getFormToken(),'class' => 'attach-remove','title'=>JText::_('COM_JEM_GLOBAL_REMOVE_ATTACHEMENT')),true); ?></td>
		</tr>
		<?php endforeach; ?>
		<tr>
			<td>
				<input type="file" name="attach[]" class="attach-field" size="10" style="width:200px"></input>
			</td>
			<td>
				<input type="text" name="attach-name[]" value="" />
			</td>
			<td>
				<input type="text" name="attach-desc[]" value="" />
			</td>
			<td>
				<?php echo JHtml::_('select.genericlist', $this->access, 'attach-access[]', array('class'=>'inputbox','style'=>'width:100px;','size'=>'3'), 'value', 'text', 0); ?>
			</td>
			<td>&nbsp;</td>
		</tr>
	</tbody>
</table>
</fieldset>