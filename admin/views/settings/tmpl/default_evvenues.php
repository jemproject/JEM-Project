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


		<div class="width-100">
<fieldset class="adminform">
	<legend><?php echo JText::_( 'COM_JEM_EVENTS' ); ?></legend>
	<ul class="adminformlist">
			<?php
			foreach ($this->form->getFieldset('evvenues') as $field):
			?>
					<li><?php echo $field->label; ?>
					<?php echo $field->input; ?></li>
			<?php
			endforeach;
			?>
	</ul>
</fieldset>
</div>







			<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_VENUES' ); ?></legend>
				<table class="admintable">
				<tbody>


					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DISPLAY_LINK_TO_MAP' ); ?>::<?php echo JText::_('COM_JEM_DISPLAY_LINK_TO_MAP_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_DISPLAY_LINK_TO_MAP' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$mode = 0;
							if ($this->jemsettings->showmapserv == 1) {
								$mode = 1;
							} elseif ($this->jemsettings->showmapserv == 2) {
								$mode = 2;
							}
							?>
							<select name="showmapserv" size="1" class="inputbox" onChange="changemapMode()">
								<option value="0"<?php if ($this->jemsettings->showmapserv == 0) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_JEM_NO_MAP_SERVICE' ); ?></option>
								<option value="1"<?php if ($this->jemsettings->showmapserv == 1) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_JEM_GOOGLE_MAP_LINK' ); ?></option>
								<option value="2"<?php if ($this->jemsettings->showmapserv == 2) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_JEM_GOOGLE_MAP_DISP' ); ?></option>
							</select>
						</td>
					</tr>
					<tr id="tld"<?php if ($mode == 2|| $mode == 1)  echo ' style="display:"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_GOOGLE_MAP_TLD' ); ?>::<?php echo JText::_('COM_JEM_GOOGLE_MAP_TLD_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_GOOGLE_MAP_TLD' ); ?>
							</span>
						</td>
							<td valign="top">
							<input type="text" name="tld" value="<?php echo $this->jemsettings->tld; ?>" size="3" maxlength="3" />
						</td>
					</tr>
					<tr id="lg"<?php if ($mode == 2 || $mode == 1) echo ' style="display:"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_GOOGLE_MAP_LG' ); ?>::<?php echo JText::_('COM_JEM_GOOGLE_MAP_LG_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_GOOGLE_MAP_LG' ); ?>
							</span>
						</td>
							<td valign="top">
							<input type="text" name="lg" value="<?php echo $this->jemsettings->lg; ?>" size="3" maxlength="3" />
						</td>
					</tr>
				</tbody>
			</table>
		</fieldset>

