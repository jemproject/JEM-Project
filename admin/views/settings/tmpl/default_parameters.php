<?php
/**
 * @version 2.2.0
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

$group = 'globalattribs';
defined('_JEXEC') or die;
?>
<div class="width-50 fltlft">
	<div class="width-100">
		<fieldset class="adminform">
			<legend><?php echo JText::_('COM_JEM_GLOBAL_PARAMETERS'); ?></legend>
			<ul class="adminformlist">
				<?php foreach ($this->form->getFieldset('globalparam') as $field): ?>
					<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
				<?php endforeach; ?>
			</ul>
		</fieldset>
	</div>
	<div class="width-100">
		<br /> <?php /* simply to get Editevent and Editvenue blocks a bit more vertically aligned ;-) */ ?>
		<fieldset class="adminform">
			<legend><?php echo JText::_('COM_JEM_SETTINGS_LEGEND_VIEW_EDITEVENT'); ?></legend>
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('global_show_ownedvenuesonly',$group); ?> <?php echo $this->form->getInput('global_show_ownedvenuesonly',$group); ?></li>
				<li><?php echo $this->form->getLabel('global_editevent_maxnumcustomfields',$group); ?> <?php echo $this->form->getInput('global_editevent_maxnumcustomfields',$group); ?></li>
			</ul>
		</fieldset>
	</div>
</div>
<div class="width-50 fltrt">
	<div class="width-100">
		<fieldset class="adminform">
			<legend><?php echo JText::_('COM_JEM_GLOBAL_PARAMETERS_ADVANCED'); ?></legend>
			<ul class="adminformlist">
				<?php foreach ($this->form->getFieldset('globalparam2') as $field): ?>
					<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
				<?php endforeach; ?>
			</ul>
		</fieldset>
	</div>
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
	<div class="width-100">
		<fieldset class="adminform">
			<legend><?php echo JText::_('COM_JEM_SETTINGS_LEGEND_VIEW_EDITVENUE'); ?></legend>
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('global_editvenue_maxnumcustomfields',$group); ?> <?php echo $this->form->getInput('global_editvenue_maxnumcustomfields',$group); ?></li>
			</ul>
		</fieldset>
	</div>
</div>
<div class="clr"></div>