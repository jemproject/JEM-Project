<?php
/**
 * @version 4.0.1-dev1
 * @package JEM
 * @copyright (C) 2013-2023 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<div class="width-100" style="padding: 10px 1vw;">
    <fieldset class="options-form">
		<legend><?php echo Text::_( 'COM_JEM_DISPLAY_SETTINGS' ); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('showdetails'); ?> <?php echo $this->form->getInput('showdetails'); ?></li>

			<li><?php echo $this->form->getLabel('formatShortDate'); ?> 
				<span <?php echo JEMOutput::tooltip(Text::_('COM_JEM_PHP_DATETIME_FORMAT_MANUAL'), Text::_('COM_JEM_PHP_DATE_MANUAL_DESC'), 'error'); ?>>
					<a href="https://www.php.net/manual/datetime.format.php#refsect1-datetime.format-parameters" target="_blank"><?php echo $this->WarningIcon(); ?></a>
				</span><?php echo $this->form->getInput('formatShortDate'); ?>
			</li>

			<li><?php echo $this->form->getLabel('formatdate'); ?>
				<span <?php echo JEMOutput::tooltip(Text::_('COM_JEM_PHP_DATETIME_FORMAT_MANUAL'), Text::_('COM_JEM_PHP_DATETIME_FORMAT_MANUAL_DESC'), 'error'); ?>>
					<a href="https://www.php.net/manual/datetime.format.php#refsect1-datetime.format-parameters" target="_blank"><?php echo $this->WarningIcon(); ?></a>
				</span><?php echo $this->form->getInput('formatdate'); ?>
			</li>

			<li><?php echo $this->form->getLabel('formattime'); ?> 
				<span <?php echo JEMOutput::tooltip(Text::_('COM_JEM_PHP_DATETIME_FORMAT_MANUAL'), Text::_('COM_JEM_PHP_DATETIME_FORMAT_MANUAL_DESC'), 'error'); ?>>
					<a href="https://www.php.net/manual/datetime.format.php#refsect1-datetime.format-parameters" target="_blank"><?php echo $this->WarningIcon(); ?></a>
				</span><?php echo $this->form->getInput('formattime'); ?>
			</li>

			<li><?php echo $this->form->getLabel('timename'); ?> <?php echo $this->form->getInput('timename'); ?></li>

			<li><?php echo $this->form->getLabel('formathour'); ?> 
				<span <?php echo JEMOutput::tooltip(Text::_('COM_JEM_PHP_DATETIME_FORMAT_MANUAL'), Text::_('COM_JEM_PHP_DATETIME_FORMAT_MANUAL_DESC'), 'error'); ?>>
					<a href="https://www.php.net/manual/datetime.format.php#refsect1-datetime.format-parameters" target="_blank"><?php echo $this->WarningIcon(); ?></a>
				</span><?php echo $this->form->getInput('formathour'); ?>
			</li>

			<li><?php echo $this->form->getLabel('storeip'); ?> <?php echo $this->form->getInput('storeip'); ?></li>
		</ul>
	</fieldset>
</div>
