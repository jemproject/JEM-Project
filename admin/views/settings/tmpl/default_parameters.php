<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

$group = 'globalattribs';
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
	
	<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_VENUES'); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('global_show_locdescription',$group); ?> <?php echo $this->form->getInput('global_show_locdescription',$group); ?></li>
			<li><?php echo $this->form->getLabel('global_show_detailsadress',$group); ?> <?php echo $this->form->getInput('global_show_detailsadress',$group); ?></li>
			<li><?php echo $this->form->getLabel('global_show_detlinkvenue',$group); ?> <?php echo $this->form->getInput('global_show_detlinkvenue',$group); ?></li>
			<li><?php echo $this->form->getLabel('global_show_mapserv',$group); ?> <?php echo $this->form->getInput('global_show_mapserv',$group); ?></li>
			<li id="globalmap1" style="display:none"><?php echo $this->form->getLabel('global_tld',$group); ?> <?php echo $this->form->getInput('global_tld',$group); ?></li>
			<li id="globalmap2" style="display:none"><?php echo $this->form->getLabel('global_lg',$group); ?> <?php echo $this->form->getInput('global_lg',$group); ?></li>
		</ul>
	</fieldset>
</div>
</div>