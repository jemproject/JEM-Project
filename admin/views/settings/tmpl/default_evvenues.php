<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
$group = 'globalattribs';
?>

<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_VENUES'); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('event_show_locdescription',$group); ?> <?php echo $this->form->getInput('event_show_locdescription',$group); ?></li>
			<li><?php echo $this->form->getLabel('event_show_detailsadress',$group); ?> <?php echo $this->form->getInput('event_show_detailsadress',$group); ?></li>
			<li><?php echo $this->form->getLabel('event_show_detlinkvenue',$group); ?> <?php echo $this->form->getInput('event_show_detlinkvenue',$group); ?></li>
			<li><?php echo $this->form->getLabel('event_show_mapserv',$group); ?> <?php echo $this->form->getInput('event_show_mapserv',$group); ?></li>
			<li id="eventmap1" style="display:none"><?php echo $this->form->getLabel('event_tld',$group); ?> <?php echo $this->form->getInput('event_tld',$group); ?></li>
			<li id="eventmap2" style="display:none"><?php echo $this->form->getLabel('event_lg',$group); ?> <?php echo $this->form->getInput('event_lg',$group); ?></li>
		</ul>
	</fieldset>
</div>