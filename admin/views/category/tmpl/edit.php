<?php
/**
 * @version     2.0.0
 * @package     JEM
 * @copyright   Copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright   Copyright (C) 2005-2009 Christoph Lukes
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * @todo make custom colorfield so it can be used within xml
 */
defined('_JEXEC') or die;

// Include the component HTML helpers.
JHtml::addIncludePath(JPATH_COMPONENT.'/helpers/html');

// Load the tooltip behavior.
JHtml::_('behavior.tooltip');
JHtml::_('behavior.formvalidation');
JHtml::_('behavior.keepalive');
?>

<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		if (task == 'category.cancel' || document.formvalidator.isValid(document.id('item-form'))) {
			<?php
			echo $this->form->getField('description')->save();
			?>
			Joomla.submitform(task, document.getElementById('item-form'));
		} else {
			alert('<?php echo $this->escape(JText::_('JGLOBAL_VALIDATION_FORM_FAILED'));?>');
		}
	}
</script>

<form action="<?php echo JRoute::_('index.php?option=com_jem&layout=edit&id='.(int) $this->item->id); ?>" method="post" name="adminForm" id="item-form" class="form-validate">
	<div class="width-60 fltlft">
		<fieldset class="adminform">
			<legend><?php echo JText::_('COM_JEM_CATEGORY_FIELDSET_DETAILS');?></legend>
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('catname'); ?>
				<?php echo $this->form->getInput('catname'); ?></li>

				<li><?php echo $this->form->getLabel('alias'); ?>
				<?php echo $this->form->getInput('alias'); ?></li>

				<li><?php echo $this->form->getLabel('extension'); ?>
				<?php echo $this->form->getInput('extension'); ?></li>

				<li><?php echo $this->form->getLabel('parent_id'); ?>
				<?php echo $this->form->getInput('parent_id'); ?></li>

				<li><?php echo $this->form->getLabel('published'); ?>
				<?php echo $this->form->getInput('published'); ?></li>

				<li><?php echo $this->form->getLabel('access'); ?>
				<?php echo $this->form->getInput('access'); ?></li>
				
				<li><?php echo $this->form->getLabel('color'); ?>
				<?php echo $this->form->getInput('color'); ?></li>
				
				<li><?php echo $this->form->getLabel('id'); ?>
				<?php echo $this->form->getInput('id'); ?></li>
			</ul>
			<div class="clr"></div>
			<?php echo $this->form->getLabel('description'); ?>
			<div class="clr"></div>
			<?php echo $this->form->getInput('description'); ?>
		</fieldset>
	</div>

	<div class="width-40 fltrt">
		<?php echo JHtml::_('sliders.start', 'categories-sliders-'.$this->item->id, array('useCookie'=>1)); ?>
			<?php echo $this->loadTemplate('options'); ?>
			<div class="clr"></div>

			<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_CATEGORY_FIELDSET_EMAIL'), 'confemail'); ?>
			<fieldset class="panelform">
				<ul class="adminformlist">
					<li>
						<?php echo $this->form->getLabel('email'); ?>
						<?php echo $this->form->getInput('email'); ?>
					</li>
				</ul>
			</fieldset>

			<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_GROUP'), 'group'); ?>
			<fieldset class="panelform">
				<ul class="adminformlist">
					<li><label for="groups"> <?php echo JText::_('COM_JEM_GROUP').':'; ?></label>
					<?php echo $this->Lists['groups']; ?></li>
				</ul>
			</fieldset>

		<!-- START OF PANEL IMAGE -->
		<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_IMAGE'), 'category-image'); ?>

		<fieldset class="panelform">
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('image'); ?> <?php echo $this->form->getInput('image'); ?>
				</li>
			</ul>
		</fieldset>


		<?php echo JHtml::_('sliders.panel', JText::_('JGLOBAL_FIELDSET_METADATA_OPTIONS'), 'meta-options'); ?>
		<fieldset class="panelform">
			<?php echo $this->loadTemplate('metadata'); ?>
		</fieldset>

		<?php  $fieldSets = $this->form->getFieldsets('attribs'); ?>
		<?php foreach ($fieldSets as $name => $fieldSet) : ?>
			<?php $label = !empty($fieldSet->label) ? $fieldSet->label : 'COM_JEM_'.$name.'_FIELDSET_LABEL'; ?>
			<?php if ($name != 'editorConfig' && $name != 'basic-limited') : ?>
				<?php echo JHtml::_('sliders.panel', JText::_($label), $name.'-options'); ?>
				<?php if (isset($fieldSet->description) && trim($fieldSet->description)) : ?>
					<p class="tip"><?php echo $this->escape(JText::_($fieldSet->description));?></p>
				<?php endif; ?>
				<fieldset class="panelform">
					<ul class="adminformlist">
					<?php foreach ($this->form->getFieldset($name) as $field) : ?>
						<li><?php echo $field->label; ?>
						<?php echo $field->input; ?></li>
					<?php endforeach; ?>
					</ul>
				</fieldset>
			<?php endif ?>
		<?php endforeach; ?>
	<?php echo JHtml::_('sliders.end'); ?>
	</div>
	<div class="clr"></div>
	<div>
		<input type="hidden" name="task" value="" />
		<?php echo JHtml::_('form.token'); ?>
	</div>
</form>