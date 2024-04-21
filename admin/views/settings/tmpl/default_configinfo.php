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

?>
<div class="width-100" style="padding: 10px 1vw;">
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
			<legend><?php echo Text::_('COM_JEM_SETTINGS_LEGEND_CONFIGINFO'); ?></legend>
			<br>
			<table class="adminlist table">
				<?php
				$known_extensions = array('pkg_jem'           => 'COM_JEM_MAIN_CONFIG_VS_PACKAGE'
				                         ,'com_jem'           => 'COM_JEM_MAIN_CONFIG_VS_COMPONENT'
				                         ,'mod_jem'           => 'COM_JEM_MAIN_CONFIG_VS_MOD_JEM'
				                         ,'mod_jem_cal'       => 'COM_JEM_MAIN_CONFIG_VS_MOD_JEM_CAL'
				                         ,'mod_jem_calajax'   => 'COM_JEM_MAIN_CONFIG_VS_MOD_JEM_CALAJAX'
				                         ,'mod_jem_banner'    => 'COM_JEM_MAIN_CONFIG_VS_MOD_JEM_BANNER'
				                         ,'mod_jem_jubilee'   => 'COM_JEM_MAIN_CONFIG_VS_MOD_JEM_JUBILEE'
				                         ,'mod_jem_teaser'    => 'COM_JEM_MAIN_CONFIG_VS_MOD_JEM_TEASER'
				                         ,'mod_jem_wide'      => 'COM_JEM_MAIN_CONFIG_VS_MOD_JEM_WIDE'
				                         ,'plg_content_jem'   => 'COM_JEM_MAIN_CONFIG_VS_PLG_CONTENT'
				                         ,'plg_content_jemlistevents' => 'COM_JEM_MAIN_CONFIG_VS_PLG_CONTENT_LISTEVENTS'
				                         ,'plg_finder_jem'    => 'COM_JEM_MAIN_CONFIG_VS_PLG_FINDER'
				                         ,'plg_search_jem'    => 'COM_JEM_MAIN_CONFIG_VS_PLG_SEARCH'
				                         ,'plg_jem_comments'  => 'COM_JEM_MAIN_CONFIG_VS_PLG_COMMENTS'
				                         ,'plg_jem_mailer'    => 'COM_JEM_MAIN_CONFIG_VS_PLG_MAILER'
				                         ,'plg_jem_demo'      => 'COM_JEM_MAIN_CONFIG_VS_PLG_DEMO'
				                         ,'plg_quickicon_jem' => 'COM_JEM_MAIN_CONFIG_VS_PLG_QUICKICON'
				                         ,'Quick Icon - JEM'  => 'COM_JEM_MAIN_CONFIG_VS_PLG_QUICKICON'
				                         ,'AcyMailing Tag : insert events from JEM 2.1+'
				                                              => 'COM_JEM_MAIN_CONFIG_VS_PLG_ACYMAILING_TAGJEM'
				                         );
                ?>
                <tr>
					<th><u><?php echo Text::_('COM_JEM_NAME'); ?></u></th>
                    <th><u><?php echo Text::_('COM_JEM_MAIN_CONFIG_VS'); ?></u></th>
                    <th><u><?php echo Text::_('COM_JEM_DATE'); ?></u></th>
                    <th><u><?php echo Text::_('COM_JEM_STATE'); ?></u></th>
                </tr>
                <?php
				foreach ($known_extensions as $name => $label) {
					if (!empty($this->config->$name)) { ?>
					<tr>
						<td><?php echo Text::_($label).': '; ?></td>
						<td><b><?php echo $this->config->$name->version; ?></b></td>
						<td><?php echo $this->config->$name->creationDate; ?></td>
						<td><?php echo empty($this->config->$name->enabled) ? Text::_('COM_JEM_DISABLED') : ''; ?></td>
					</tr>
					<?php
					}
				}
				?>
					<tr>
						<td><?php echo Text::_('COM_JEM_MAIN_CONFIG_VS_PHP').': '; ?></td>
						<td colspan="3"><b><?php echo $this->config->vs_php; ?> </b></td>
					</tr>
					<?php if (!empty($this->config->vs_php_magicquotes)) : ?>
					<tr>
						<td><?php echo Text::_('COM_JEM_MAIN_CONFIG_VS_PHP_MAGICQUOTES').': '; ?></td>
						<td colspan="3"><b><?php echo $this->config->vs_php_magicquotes; ?> </b></td>
					</tr>
					<?php endif; ?>
					<tr>
						<td><?php echo Text::_('COM_JEM_MAIN_CONFIG_VS_GD').': '; ?></td>
						<td colspan="3"><b><?php echo $this->config->vs_gd; ?> </b></td>
					</tr>
				</table>
		</fieldset>
	</div>
</div>

<div class="width-50 fltrt">

</div>

<div class="clr"></div>
