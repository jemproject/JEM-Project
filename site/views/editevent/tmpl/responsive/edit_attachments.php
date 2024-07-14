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

<fieldset>
	<legend><?php echo Text::_('COM_JEM_ATTACHMENTS_LEGEND'); ?></legend>

	<table class="adminform">
		<tbody>
			<?php foreach ($this->item->attachments as $file) : ?>
			<tr>
				<td style="width: 100%;">
					<dl class="jem-dl">
						<dt><?php echo Text::_('COM_JEM_ATTACHMENT_FILE'); ?></dt>
						<dd><input class="readonly" type="text" readonly="readonly" value="<?php echo $file->file; ?>" /></dd>
						<dd><input type="hidden" name="attached-id[]" value="<?php echo $file->id; ?>" /></dd>

						<dt><?php echo Text::_('COM_JEM_ATTACHMENT_NAME'); ?></dt>
						<?php /* name is always editable, also if attachemnt upload is not allowed */ ?>
						<dd><input type="text" name="attached-name[]" value="<?php echo $file->name; ?>" /></dd>

						<dt><?php echo Text::_('COM_JEM_ATTACHMENT_DESCRIPTION'); ?></dt>
						<?php /* description is always editable, also if attachemnt upload is not allowed */ ?>
						<dd><input type="text" name="attached-desc[]" value="<?php echo $file->description; ?>" /></dd>
					</dl>
					<?php if ($this->jemsettings->attachmentenabled != 0) : ?>
					<?php //This button just deletes the dl because two times more getParent() in attachment.js is required
							?>
					<?php echo Text::_('COM_JEM_GLOBAL_REMOVE_ATTACHEMENT') . ' ' . $file->name; ?>
					<?php echo JemOutput::removebutton(Text::_('COM_JEM_GLOBAL_REMOVE_ATTACHEMENT'), array('id' => 'attach-remove' . $file->id . ':' . JSession::getFormToken(), 'class' => 'attach-remove', 'title' => Text::_('COM_JEM_GLOBAL_REMOVE_ATTACHEMENT'))); ?>

					<?php endif; ?>
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

							echo JHtml::_('select.genericlist', $this->access, 'attached-access[]', $attribs, 'value', 'text', $file->access);
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
						<dd><input type="file" name="attach[]" class="attach-field" /></dd>
						<?php /* see attachments.js for button's onclick function */ ?>
						<dt> </dt>
						<dd><button type="button" class="clear-attach-field button3 formelm-buttons btn"><?php echo Text::_('JSEARCH_FILTER_CLEAR') ?></button></dd>
						<dt><?php echo Text::_('COM_JEM_ATTACHMENT_NAME'); ?></dt>
						<dd><input type="text" name="attach-name[]" class="attach-name" value="" /></dd>
						<dt><?php echo Text::_('COM_JEM_ATTACHMENT_DESCRIPTION'); ?></dt>
						<dd><input type="text" name="attach-desc[]" class="attach-desc" value="" /></dd>
					</dl>
				</td>
				<td>
					<div>
						<div><?php echo Text::_('COM_JEM_ATTACHMENT_ACCESS'); ?></div>
						<?php echo JHtml::_('select.genericlist', $this->access, 'attach-access[]', array('class' => 'inputbox', 'size' => '7'), 'value', 'text', 0); ?>
					</div>
				</td>
			</tr>
			<?php endif; ?>
		</tbody>
	</table>
</fieldset>
