<?php
/**
 * @version 2.3.15
 * @package JEM
 * @copyright (C) 2013-2023 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;
$group = 'globalattribs';
?>

<div class="width-100" style="padding: 10px 1vw;">
    <fieldset class="options-form">
		<legend><?php echo JText::_('COM_JEM_REGISTRATION'); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('event_show_attendeenames',$group); ?> <?php echo $this->form->getInput('event_show_attendeenames',$group); ?></li>
			<li><?php echo $this->form->getLabel('event_show_more_attendeedetails',$group); ?> <?php echo $this->form->getInput('event_show_more_attendeedetails',$group); ?></li>
			<li><?php echo $this->form->getLabel('event_comunsolution',$group); ?> <?php echo $this->form->getInput('event_comunsolution',$group); ?></li>
			<li><?php echo $this->form->getLabel('event_comunoption',$group); ?> <?php echo $this->form->getInput('event_comunoption',$group); ?></li>
		</ul>
	</fieldset>
</div>
