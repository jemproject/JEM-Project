<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

?>
<div class="width-50 fltlft">
<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_SETTINGS_LEGEND_CONFIGINFO'); ?></legend>
		<br>
		<table class="adminlist">
				<tr>
					<td><?php echo JText::_('COM_JEM_MAIN_CONFIG_VS_COMPONENT').': '; ?></td>
					<td><b><?php echo $this->config->vs_component; ?> </b></td>
				</tr>
				<tr>
					<td><?php echo JText::_('COM_JEM_MAIN_CONFIG_VS_PLG_MAILER').': '; ?></td>
					<td><b><?php echo $this->config->vs_plg_mailer; ?> </b></td>
				</tr>
				<tr>
					<td><?php echo JText::_('COM_JEM_MAIN_CONFIG_VS_MOD_JEM_CAL').': '; ?></td>
					<td><b><?php echo $this->config->vs_mod_jem_cal; ?> </b></td>
				</tr>
				<tr>
					<td><?php echo JText::_('COM_JEM_MAIN_CONFIG_VS_MOD_JEM').': '; ?></td>
					<td><b><?php echo $this->config->vs_mod_jem; ?> </b></td>
				</tr>
				<tr>
					<td><?php echo JText::_('COM_JEM_MAIN_CONFIG_VS_MOD_JEM_WIDE').': '; ?></td>
					<td><b><?php echo $this->config->vs_mod_jem_wide; ?> </b></td>
				</tr>
				<tr>
					<td><?php echo JText::_('COM_JEM_MAIN_CONFIG_VS_MOD_JEM_TEASER').': '; ?></td>
					<td><b><?php echo $this->config->vs_mod_jem_teaser; ?> </b></td>
				</tr>
				<tr>
					<td><?php echo JText::_('COM_JEM_MAIN_CONFIG_VS_PHP').': '; ?></td>
					<td><b><?php echo $this->config->vs_php; ?> </b></td>
				</tr>
				<tr>
					<td><?php echo JText::_('COM_JEM_MAIN_CONFIG_VS_PHP_MAGICQUOTES').': '; ?></td>
					<td><b><?php echo $this->config->vs_php_magicquotes; ?> </b></td>
				</tr>
				<tr>
					<td><?php echo JText::_('COM_JEM_MAIN_CONFIG_VS_GD').': '; ?></td>
					<td><b><?php echo $this->config->vs_gd; ?> </b></td>
				</tr>
			</table>
	</fieldset>
</div>
</div>

<div class="width-50 fltrt">


</div><div class="clr"></div>