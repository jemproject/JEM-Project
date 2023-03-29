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
		<legend><?php echo JText::_('COM_JEM_VENUES'); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('event_show_locdescription',$group); ?> <?php echo $this->form->getInput('event_show_locdescription',$group); ?></li>
			<li><?php echo $this->form->getLabel('event_show_detailsadress',$group); ?> <?php echo $this->form->getInput('event_show_detailsadress',$group); ?></li>
			<li><?php echo $this->form->getLabel('event_show_detlinkvenue',$group); ?> <?php echo $this->form->getInput('event_show_detlinkvenue',$group); ?></li>
			<li><?php echo $this->form->getLabel('event_show_mapserv',$group); ?> <?php echo $this->form->getInput('event_show_mapserv',$group); ?></li>
			<li><?php echo $this->form->getLabel('event_tld',$group); ?> <?php echo $this->form->getInput('event_tld',$group); ?></li>
			<li><?php echo $this->form->getLabel('event_lg',$group); ?> <?php echo $this->form->getInput('event_lg',$group); ?></li>
		</ul>
	</fieldset>
</div>
