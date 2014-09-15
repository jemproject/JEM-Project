<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>
<div class="width-50 fltlft">
<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_GENERAL_LAYOUT_SETTINGS'); ?></legend>
		<ul class="adminformlist">
			<?php foreach ($this->form->getFieldset('layoutgenerallayoutsetting') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>
</div>
<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_CITY_COLUMN'); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('showcity'); ?> <?php echo $this->form->getInput('showcity'); ?></li>

			<li id="city1" style="display:none"><?php echo $this->form->getLabel('citywidth'); ?> <?php echo $this->form->getInput('citywidth'); ?></li>
		</ul>
	</fieldset>
</div>
<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_ATTENDEE_COLUMN'); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('showatte'); ?> <?php echo $this->form->getInput('showatte'); ?></li>

			<li id="atte1" style="display:none"><?php echo $this->form->getLabel('attewidth'); ?> <?php echo $this->form->getInput('attewidth'); ?></li>
		</ul>
	</fieldset>
</div>
<div class="width-100">
<fieldset class="adminform">
	<legend><?php echo JText::_('COM_JEM_TITLE_COLUMN'); ?></legend>
	<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('showtitle'); ?> <?php echo $this->form->getInput('showtitle'); ?>
				</li>
				<li id="title1" style="display:none"><?php echo $this->form->getLabel('titlewidth'); ?> <?php echo $this->form->getInput('titlewidth'); ?>
				</li>
	</ul>
</fieldset>
</div>
<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_VENUE_COLUMN'); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('showlocate'); ?> <?php echo $this->form->getInput('showlocate'); ?></li>

			<li id="loc1" style="display:none"><?php echo $this->form->getLabel('locationwidth'); ?> <?php echo $this->form->getInput('locationwidth'); ?></li>

			<li id="loc2" style="display:none"><?php echo $this->form->getLabel('showlinkvenue'); ?> <?php echo $this->form->getInput('showlinkvenue'); ?></li>
		</ul>
	</fieldset>
</div>
<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_STATE_COLUMN'); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('showstate'); ?> <?php echo $this->form->getInput('showstate'); ?></li>
			<li id="state1" style="display:none"><?php echo $this->form->getLabel('statewidth'); ?> <?php echo $this->form->getInput('statewidth'); ?></li>
		</ul>
	</fieldset>
</div>
<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_CATEGORY_COLUMN'); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('showcat'); ?> <?php echo $this->form->getInput('showcat'); ?></li>

			<li id="cat1" style="display:none"><?php echo $this->form->getLabel('catfrowidth'); ?> <?php echo $this->form->getInput('catfrowidth'); ?></li>

			<li id="cat2" style="display:none"><?php echo $this->form->getLabel('catlinklist'); ?> <?php echo $this->form->getInput('catlinklist'); ?></li>
		</ul>
	</fieldset>
</div>
<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_LAYOUT_TABLE_EVENTIMAGE'); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('showeventimage'); ?> <?php echo $this->form->getInput('showeventimage'); ?></li>

			<li id="evimage1" style="display:none"><?php echo $this->form->getLabel('tableeventimagewidth'); ?> <?php echo $this->form->getInput('tableeventimagewidth'); ?></li>
		</ul>
	</fieldset>
</div>
</div>

<div class="width-50 fltrt">
<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_SETTINGS_LEGEND_CSS'); ?></legend>
		<ul class="adminformlist">
			<?php foreach ($this->form->getFieldset('stylesheet') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>
</div>
<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_SETTINGS_LEGEND_CSS_COLOR_BACKGROUND'); ?></legend>
		<ul class="adminformlist">
			<?php foreach ($this->form->getFieldset('css_color') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>
</div>
<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_SETTINGS_LEGEND_CSS_COLOR_BORDER'); ?></legend>
		<ul class="adminformlist">
			<?php foreach ($this->form->getFieldset('css_color_border') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>
</div>
<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_SETTINGS_LEGEND_CSS_COLOR_FONT'); ?></legend>
		<ul class="adminformlist">
			<?php foreach ($this->form->getFieldset('css_color_font') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>
</div>
</div><div class="clr"></div>