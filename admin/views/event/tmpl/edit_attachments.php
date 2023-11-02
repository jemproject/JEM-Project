<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */


defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>

<table class="adminform" id="el-attachments">
	<tbody>
		<?php foreach ($this->item->attachments as $file): ?>
		<tr>
			<td>
				<div>
					<div class="title"><?php echo Text::_('COM_JEM_ATTACHMENT_FILE');?></div>
					<input class="form-control readonly valid form-control-success w-75" type="text" readonly="readonly" value="<?php echo $file->file; ?>"></input>
					<input type="hidden" name="attached-id[]" value="<?php echo $file->id; ?>"/>
				</div>
				<div>
					<div class="title"><?php echo Text::_('COM_JEM_ATTACHMENT_NAME'); ?></div>
					<input type="text" name="attached-name[]" class="form-control valid form-control-success w-75" value="<?php echo $file->name; ?>" />
				</div>
				<div>
					<div class="title"><?php echo Text::_('COM_JEM_ATTACHMENT_DESCRIPTION'); ?></div>
					<input type="text" name="attached-desc[]" class="form-control valid form-control-success w-75" value="<?php echo $file->description; ?>" />
				</div>
			</td>
			<td>
				<div>
					<div class="title"><?php echo Text::_('COM_JEM_ATTACHMENT_ACCESS'); ?></div>
					<?php echo JHtml::_('select.genericlist', $this->access, 'attached-access[]', array('class'=>'inputbox form-control','size'=>'7'), 'value', 'text', $file->access); ?>
				</div>
			</td>
			<td class="center">
				<div>
					<div class="title"><?php echo Text::_('COM_JEM_ATTACHMENT_REMOVE'); ?></div>
					<?php echo JHtml::_('image','com_jem/publish_r.png', Text::_('COM_JEM_REMOVE_ATTACHEMENT'), array('id' => 'attach-remove'.$file->id.':'.JSession::getFormToken(), 'class' => 'attach-remove', 'title'=>Text::_('COM_JEM_REMOVE_ATTACHEMENT')), true); ?>
				</div>
			</td>
		</tr>
		<?php endforeach; ?>
		<tr>
			<td width="100%">
				<div style="display: inline-block; text-wrap: none;">
					<div class="title"><?php echo Text::_('COM_JEM_ATTACHMENT_FILE'); ?></div>
					<input type="file" name="attach[]" class="attach-field"></input>
					<input type="reset" value="<?php echo Text::_('JSEARCH_FILTER_CLEAR') ?>" class="btn btn-primary">
				</div>
				<div>
					<div class="title"><?php echo Text::_('COM_JEM_ATTACHMENT_NAME'); ?></div>
					<input type="text" name="attach-name[]" value="" class="form-control valid form-control-success w-75" />
				</div>
				<div>
					<div class="title"><?php echo Text::_('COM_JEM_ATTACHMENT_DESCRIPTION'); ?></div>
					<input type="text" name="attach-desc[]" value="" class="form-control valid form-control-success w-75" />
				</div>
			</td>
			<td>
				<div>
					<div class="title"><?php echo Text::_('COM_JEM_ATTACHMENT_ACCESS'); ?></div>
					<?php echo JHtml::_('select.genericlist', $this->access, 'attach-access[]', array('class'=>'inputbox form-control','size'=>'7'), 'value', 'text', 1); ?>
				</div>
			</td>
			<td>&nbsp;</td>
		</tr>
	</tbody>
</table>
