<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>

<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_USER_CONTROL'); ?></legend>
		<ul class="adminformlist">
			<?php foreach ($this->form->getFieldset('usercontrol') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>
</div>
<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_AC_EVENTS'); ?></legend>
		<ul class="adminformlist">
			<?php foreach ($this->form->getFieldset('usercontrolacevent') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>
</div>
<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_REGISTRATION'); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('showfroregistra'); ?> <?php echo $this->form->getInput('showfroregistra'); ?> </li>

			<li id="froreg1"><?php echo $this->form->getLabel('showfrounregistra'); ?> <?php echo $this->form->getInput('showfrounregistra'); ?> </li>
		</ul>
	</fieldset>
</div>
<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_AC_VENUES'); ?></legend>
		<ul class="adminformlist">
			<?php foreach ($this->form->getFieldset('usercontrolacvenue') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>
</div>