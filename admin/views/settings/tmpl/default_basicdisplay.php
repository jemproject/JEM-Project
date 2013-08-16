<?php
/**
 * @package		Joomla.Administrator
 * @subpackage	com_config
 * @copyright	Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die;
?>
<div class="width-100">
<fieldset class="adminform">
	<legend><?php echo JText::_( 'COM_JEM_DISPLAY_SETTINGS' ); ?></legend>
	<ul class="adminformlist">

			<li><?php echo $this->form->getLabel('showdetails'); ?> <?php echo $this->form->getInput('showdetails'); ?>
			</li>


			<li><?php echo $this->form->getLabel('formatShortDate'); ?> <?php echo $this->form->getInput('formatShortDate'); ?>
			<span class="error hasTip" title="<?php echo JText::_('COM_JEM_PHP_DATE_MANUAL');?>::<?php echo JText::_('COM_JEM_PHP_DATE_MANUAL_DESC'); ?>">
				<a href="http://php.net/manual/en/function.date.php" target="_blank"><?php echo $this->WarningIcon(); ?></a>
			</span>
			</li>

			<li><?php echo $this->form->getLabel('formatdate'); ?> <?php echo $this->form->getInput('formatdate'); ?>
			<span class="error hasTip" title="<?php echo JText::_('COM_JEM_PHP_DATE_MANUAL');?>::<?php echo JText::_('COM_JEM_PHP_DATE_MANUAL_DESC'); ?>">
				<a href="http://php.net/manual/en/function.date.php" target="_blank"><?php echo $this->WarningIcon(); ?></a>
			</span>
			</li>

			<li><?php echo $this->form->getLabel('formattime'); ?> <?php echo $this->form->getInput('formattime'); ?>
			<span class="error hasTip" title="<?php echo JText::_('COM_JEM_TIME_STRFTIME');?>::<?php echo JText::_('COM_JEM_TIME_STRFTIME_DESC'); ?>">
				<a href="http://www.php.net/strftime" target="_blank"><?php echo $this->WarningIcon(); ?></a>
			</span>
			</li>

			<li><?php echo $this->form->getLabel('timename'); ?> <?php echo $this->form->getInput('timename'); ?>
			</li>

			<li><?php echo $this->form->getLabel('storeip'); ?> <?php echo $this->form->getInput('storeip'); ?>
			</li>


	</ul>
</fieldset>
</div>




