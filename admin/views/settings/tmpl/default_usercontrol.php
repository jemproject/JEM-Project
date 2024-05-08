<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

?>
<div class="width-50 fltlft">
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
		<legend><?php echo Text::_('COM_JEM_USER_CONTROL'); ?></legend>
		<ul class="adminformlist">
			<?php foreach ($this->form->getFieldset('usercontrol') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>
</div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
		<legend><?php echo Text::_('COM_JEM_AC_EVENTS'); ?></legend>
		<ul class="adminformlist">
			<?php foreach ($this->form->getFieldset('usercontrolacevent') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>
</div>
</div><div class="width-50 fltrt">
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
		<legend><?php echo Text::_('COM_JEM_REGISTRATION'); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('showfroregistra'); ?> <?php echo $this->form->getInput('showfroregistra'); ?> </li>
			<li id="froreg1"><div class="label-form"><?php echo $this->form->renderfield('regallowinvitation'); ?></div></li>
			<li id="froreg2"><div class="label-form"><?php echo $this->form->renderfield('regallowcomments'); ?></div></li>
		</ul>
	</fieldset>
</div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
		<legend><?php echo Text::_('COM_JEM_AC_VENUES'); ?></legend>
		<ul class="adminformlist">
			<?php foreach ($this->form->getFieldset('usercontrolacvenue') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>
</div>
</div><div class="clr"></div>
