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
			<li><?php echo $this->form->getLabel('regname'); ?> <?php echo $this->form->getInput('regname'); ?></li>

			<li><?php echo $this->form->getLabel('comunsolution'); ?> <?php echo $this->form->getInput('comunsolution'); ?></li>

			<li id="comm1" style="display:none"><?php echo $this->form->getLabel('comunoption'); ?> <?php echo $this->form->getInput('comunoption'); ?></li>
		</ul>
	</fieldset>
</div>