<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
?>

<fieldset>
	<legend><?php echo Text::_('COM_JEM_ATTACHMENTS_LEGEND'); ?></legend>

	<table class="adminform">
		<tbody>
			<?php foreach ($this->item->attachments as $file) : ?>
			<tr>
				<td style="width: 100%;">
					<dl class="jem-dl">
						<dt><?php echo Text::_('COM_JEM_ATTACHMENT_FILE'); ?></dt>
						<dd><input class="form-control readonly valid form-control-success w-75" type="text" readonly="readonly" value="<?php echo $file->file; ?>" /></dd>
						<dd><input type="hidden" name="attached-id[]" value="<?php echo $file->id; ?>" /></dd>

						<dt><?php echo Text::_('COM_JEM_ATTACHMENT_NAME'); ?></dt>
						<?php /* name is always editable, also if attachemnt upload is not allowed */ ?>
						<dd><input type="text" name="attached-name[]" value="<?php echo $file->name; ?>" /></dd>

						<dt><?php echo Text::_('COM_JEM_ATTACHMENT_DESCRIPTION'); ?></dt>
						<?php /* description is always editable, also if attachemnt upload is not allowed */ ?>
						<dd><input type="text" name="attached-desc[]" value="<?php echo $file->description; ?>" /></dd>

						<?php if ($this->jemsettings->attachmentenabled != 0) : ?>
						<?php //This button just deletes the dl because two times more getParent() in attachment.js is required
								?>
						<dt><?php echo Text::_('COM_JEM_ATTACHMENT_REMOVE') . ' ' . $file->name; ?></dt>
						<dd><?php echo JemOutput::removebutton(Text::_('COM_JEM_GLOBAL_REMOVE_ATTACHEMENT'), array('id' => 'attach-remove' . $file->id . ':' . JSession::getFormToken(), 'class' => 'attach-remove btn', 'title' => Text::_('COM_JEM_GLOBAL_REMOVE_ATTACHEMENT'))); ?></dd>
						<?php endif; ?>
                    </dl>
				</td>
				<td>
					<div>
						<div><?php echo Text::_('COM_JEM_ATTACHMENT_ACCESS'); ?></div>
						<?php
							$attribs = array('class' => 'inputbox', 'size' => '7');
							/* if attachment upload is not allowed changing access level should also not possible */
							if ($this->jemsettings->attachmentenabled == 0) :
								$attribs['disabled'] = 'disabled';
							endif;

							echo HTMLHelper::_('select.genericlist', $this->access, 'attached-access[]', $attribs, 'value', 'text', $file->access);
							?>
					</div>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p>&nbsp;</p>

	<legend><?php echo Text::_('COM_JEM_ADD_USER_REGISTRATIONS') . ' ' . Text::_('COM_JEM_ATTACHMENTS_LEGEND'); ?></legend>
	<table class="adminform" id="el-attachments">
		<tbody>
			<?php if ($this->jemsettings->attachmentenabled != 0) : ?>
			<tr>
				<td style="width: 100%;">
					<dl class="jem-dl">
						<dt><?php echo Text::_('COM_JEM_ATTACHMENT_FILE'); ?></dt>
						<dd><input type="file" name="attach[]" class="attach-field" /> <input type="reset" value="<?php echo Text::_('JSEARCH_FILTER_CLEAR') ?>" class="btn btn-primary"></dd>
						<dt><?php echo Text::_('COM_JEM_ATTACHMENT_NAME'); ?></dt>
						<dd><input type="text" name="attach-name[]" class="attach-name" value="" /></dd>
						<dt><?php echo Text::_('COM_JEM_ATTACHMENT_DESCRIPTION'); ?></dt>
						<dd><input type="text" name="attach-desc[]" class="attach-desc" value="" /></dd>
					</dl>
				</td>
				<td>
					<div>
						<div><?php echo Text::_('COM_JEM_ATTACHMENT_ACCESS'); ?></div>
						<?php echo HTMLHelper::_('select.genericlist', $this->access, 'attach-access[]', array('class' => 'inputbox', 'size' => '7'), 'value', 'text', 1); ?>
					</div>
				</td>
			</tr>
			<?php endif; ?>
		</tbody>
	</table>
</fieldset>
