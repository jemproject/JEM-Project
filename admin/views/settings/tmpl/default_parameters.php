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
		<legend><?php echo JText::_('COM_JEM_GLOBAL_PARAMETERS'); ?></legend>
		<ul class="adminformlist">
			<?php foreach ($this->form->getFieldset('globalparam') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>

	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_GLOBAL_PARAMETERS'); ?></legend>
		<ul class="adminformlist">
			<?php foreach ($this->form->getFieldset('globalparam2') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>
</div>