<?php
/**
 * @version 2.2.3
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined ('_JEXEC') or die;
?>

<fieldset>
	<legend><?php echo JText::_('COM_JEM_ATTACHMENTS_LEGEND'); ?></legend>

	<table class="adminform" id="el-attachments">
		<tbody>
			<?php foreach ($this->item->attachments as $file): ?>
			<tr>
				<td>
					<div>
						<div class="title"><?php echo JText::_('COM_JEM_ATTACHMENT_FILE');?></div>
						<input class="readonly" type="text" readonly="readonly" value="<?php echo $file->file; ?>" />
						<input type="hidden" name="attached-id[]" value="<?php echo $file->id; ?>" />
					</div>
					<div>
						<div class="title"><?php echo JText::_('COM_JEM_ATTACHMENT_NAME'); ?></div>
						<?php /* name is always editable, also if attachemnt upload is not allowed */ ?>
						<input type="text" name="attached-name[]" style="width: 100%" value="<?php echo $file->name; ?>" />
					</div>
					<div>
						<div class="title"><?php echo JText::_('COM_JEM_ATTACHMENT_DESCRIPTION'); ?></div>
						<?php /* description is always editable, also if attachemnt upload is not allowed */ ?>
						<input type="text" name="attached-desc[]" style="width: 100%" value="<?php echo $file->description; ?>" />
					</div>
				</td>
				<td>
					<div>
						<div class="title"><?php echo JText::_('COM_JEM_ATTACHMENT_ACCESS'); ?></div>
						<?php
							$attribs = array('class'=>'inputbox', 'size'=>'7');
							/* if attachment upload is not allowed changing access level should also not possible */
							if ($this->jemsettings->attachmentenabled == 0) :
								$attribs['disabled'] = 'disabled';
							endif;

							echo JHtml::_('select.genericlist', $this->access, 'attached-access[]', $attribs, 'value', 'text', $file->access);
						?>
					</div>
				</td>
				<td class="center">
					<?php if ($this->jemsettings->attachmentenabled != 0) : ?>
					<div>
						<div class="title"><?php echo JText::_('COM_JEM_ATTACHMENT_REMOVE'); ?></div>
						<?php echo JHtml::_('image','com_jem/publish_r.png', JText::_('COM_JEM_GLOBAL_REMOVE_ATTACHEMENT'), array('id' => 'attach-remove'.$file->id.':'.JSession::getFormToken(),'class' => 'attach-remove','title'=>JText::_('COM_JEM_GLOBAL_REMOVE_ATTACHEMENT')),true); ?>
					</div>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
			<?php if ($this->jemsettings->attachmentenabled != 0) : ?>
			<tr>
				<td width="100%">
					<div>
						<div class="title"><?php echo JText::_('COM_JEM_ATTACHMENT_FILE'); ?></div>
						<input type="file" name="attach[]" class="attach-field" />
						<?php /* see attachments.js for button's onclick function */ ?>
						<button type="button" class="clear-attach-field button3 formelm-buttons"><?php echo JText::_('JSEARCH_FILTER_CLEAR') ?></button>
					</div>
					<div>
						<div class="title"><?php echo JText::_('COM_JEM_ATTACHMENT_NAME'); ?></div>
						<input type="text" name="attach-name[]" class="attach-name" value="" />
					</div>
					<div>
						<div class="title"><?php echo JText::_('COM_JEM_ATTACHMENT_DESCRIPTION'); ?></div>
						<input type="text" name="attach-desc[]" class="attach-desc" value="" />
					</div>
				</td>
				<td>
					<div>
						<div class="title"><?php echo JText::_('COM_JEM_ATTACHMENT_ACCESS'); ?></div>
						<?php echo JHtml::_('select.genericlist', $this->access, 'attach-access[]', array('class'=>'inputbox', 'size'=>'7'), 'value', 'text', 0); ?>
					</div>
				</td>
				<td>&nbsp;</td>
			</tr>
			<?php endif; ?>
		</tbody>
	</table>
</fieldset>

