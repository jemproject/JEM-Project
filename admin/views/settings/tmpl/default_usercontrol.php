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
	<legend><?php echo JText::_( 'COM_JEM_USER_CONTROL' ); ?></legend>
	<ul class="adminformlist">
			<?php
			foreach ($this->form->getFieldset('usercontrol') as $field):
			?>
					<li><?php echo $field->label; ?>
					<?php echo $field->input; ?></li>
			<?php
			endforeach;
			?>
	</ul>
</fieldset>
</div>


<div class="width-100">
<fieldset class="adminform">
	<legend><?php echo JText::_( 'COM_JEM_AC_EVENTS' ); ?></legend>
	<ul class="adminformlist">
			<?php
			foreach ($this->form->getFieldset('usercontrolacevent') as $field):
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
			<legend><?php echo JText::_( 'COM_JEM_REGISTRATION' ); ?></legend>
				<table class="admintable">
				<tbody>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SUBMIT_REGISTER' ); ?>::<?php echo JText::_('COM_JEM_SUBMIT_REGISTER_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_SUBMIT_REGISTER' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$mode = 0;
							if ($this->jemsettings->showfroregistra == 1) {
								$mode = 1;
							} // if
							if ($this->jemsettings->showfroregistra == 2) {
								$mode = 2;
							} // if
							?>
							<select name="showfroregistra" size="1" class="inputbox" onChange="changeregMode()">
								<option value="0"<?php if ($this->jemsettings->showfroregistra == 0) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'JNO' ); ?></option>
								<option value="1"<?php if ($this->jemsettings->showfroregistra == 1) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'JYES' ); ?></option>
								<option value="2"<?php if ($this->jemsettings->showfroregistra == 2) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_JEM_OPTIONAL' ); ?></option>
							</select>
						</td>
					</tr>
					<tr id="froreg"<?php if (!$mode) echo ' style="display:none"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SUBMIT_UNREGISTER' ); ?>::<?php echo JText::_('COM_JEM_SUBMIT_UNREGISTER_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_SUBMIT_UNREGISTER' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$showfrounreg = array();
							$showfrounreg[] = JHTML::_('select.option', '0', JText::_( 'JNO' ) );
							$showfrounreg[] = JHTML::_('select.option', '1', JText::_( 'JYES' ) );
							$showfrounreg[] = JHTML::_('select.option', '2', JText::_( 'COM_JEM_OPTIONAL' ) );
							$showfrounregist = JHTML::_('select.genericlist', $showfrounreg, 'showfrounregistra', 'size="1" class="inputbox"', 'value', 'text', $this->jemsettings->showfrounregistra );
							echo $showfrounregist;
							?>
						</td>
					</tr>
				</tbody>
			</table>
		</fieldset>



		<div class="width-100">
<fieldset class="adminform">
	<legend><?php echo JText::_( 'COM_JEM_AC_VENUES' ); ?></legend>
	<ul class="adminformlist">
			<?php
			foreach ($this->form->getFieldset('usercontrolacvenue') as $field):
			?>
					<li><?php echo $field->label; ?>
					<?php echo $field->input; ?></li>
			<?php
			endforeach;
			?>
	</ul>
</fieldset>
</div>


