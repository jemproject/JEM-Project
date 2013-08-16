<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>


		<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_EVENT_HANDLING' ); ?></legend>
				<table class="admintable">
				<tbody>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_OLD_EVENTS' ); ?>::<?php echo JText::_('COM_JEM_OLD_EVENTS_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_OLD_EVENTS' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$mode = 0;
							if ($this->data->oldevent >= 1) {
								$mode = 1;
							} // if
							?>
							<select name="oldevent" size="1" class="inputbox" onChange="changeoldMode()">
								<option value="0"<?php if ($this->data->oldevent == 0) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_JEM_DO_NOTHING' ); ?></option>
								<option value="1"<?php if ($this->data->oldevent == 1) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_JEM_DELETE_OLD_EVENTS' ); ?></option>
								<option value="2"<?php if ($this->data->oldevent == 2) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_JEM_ARCHIVE_OLD_EVENTS' ); ?></option>
							</select>&nbsp;
							<span class="error hasTip" title="<?php echo JText::_( 'COM_JEM_WARNING' ); ?>::<?php echo JText::_( 'COM_JEM_OLD_EVENTS_WARN' ); ?>">
								<?php echo $this->WarningIcon(); ?>
							</span>
						</td>
					</tr>
					<tr id="old"<?php if (!$mode) echo ' style="display:none"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_NUMBER_DELETE_DAYS' ); ?>::<?php echo JText::_('COM_JEM_NUMBER_DELETE_DAYS_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_NUMBER_DELETE_DAYS' ); ?>
							</span>
						</td>
						<td valign="top">
							<input type="text" name="minus" value="<?php echo $this->data->minus; ?>" size="3" maxlength="2" />
						</td>
					</tr>
				</tbody>
			</table>
		</fieldset>



