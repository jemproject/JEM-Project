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
	<legend><?php echo JText::_( 'COM_JEM_REGISTRATION' ); ?></legend>
	<ul class="adminformlist">
			<?php
			foreach ($this->form->getFieldset('evregistration') as $field):
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
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_COM_SOL' ); ?>::<?php echo JText::_('COM_JEM_COM_SOL_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_COM_SOL' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$mode = 0;
							if ($this->jemsettings->comunsolution == 1) {
								$mode = 1;
							} // if
							?>
							<select name="comunsolution" size="1" class="inputbox" onChange="changeintegrateMode()">
								<option value="0"<?php if ($this->jemsettings->comunsolution == 0) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_JEM_DONT_USE_COM_SOL' ); ?></option>
								<option value="1"<?php if ($this->jemsettings->comunsolution == 1) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_JEM_COMBUILDER' ); ?></option>
							</select>
						</td>
					</tr>
					<tr id="integrate"<?php if (!$mode) echo ' style="display:none"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_TYPE_COM_INTEGRATION' ); ?>::<?php echo JText::_('COM_JEM_TYPE_COM_INTEGRATION_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_TYPE_COM_INTEGRATION' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$comoption = array();
							$comoption[] = JHTML::_('select.option', '0', JText::_( 'COM_JEM_LINK_PROFILE' ) );
							$comoption[] = JHTML::_('select.option', '1', JText::_( 'COM_JEM_LINK_AVATAR' ) );
							$comoptions = JHTML::_('select.genericlist', $comoption, 'comunoption', 'size="1" class="inputbox"', 'value', 'text', $this->jemsettings->comunoption );
							echo $comoptions;
							?>
						</td>
					</tr>
				</tbody>
			</table>
		</fieldset>

