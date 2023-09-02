<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 * @todo make custom colorfield so it can be used within xml
 */
 
defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// Include the component HTML helpers.
HTMLHelper::addIncludePath(JPATH_COMPONENT.'/helpers/html');

// Load the tooltip behavior.
// HTMLHelper::_('behavior.tooltip');
// HTMLHelper::_('behavior.formvalidation');
// HTMLHelper::_('behavior.keepalive');
$wa = $this->document->getWebAssetManager();
		$wa->useStyle('jem.colorpicker')
			->useScript('keepalive')
			->useScript('inlinehelp')
			->useScript('form.validate');
?>

<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		if (task == 'category.cancel' || document.formvalidator.isValid(document.getElementById('item-form'))) {
			<?php
			//echo $this->form->getField('description')->save();
			?>
			Joomla.submitform(task, document.getElementById('item-form'));
		} else {
			alert('<?php echo $this->escape(Text::_('JGLOBAL_VALIDATION_FORM_FAILED'));?>');
		}
	}
</script>

<form action="<?php echo Route::_('index.php?option=com_jem&layout=edit&id='.(int) $this->item->id); ?>" method="post" name="adminForm" id="item-form" class="form-validate">
	<div class="row">
		<div class="col-md-7">
			<fieldset class="adminform">
				<legend><?php echo Text::_('COM_JEM_CATEGORY_FIELDSET_DETAILS');?></legend>
				<ul class="adminformlist">
					<li><div class="label-form"><?php echo $this->form->renderfield('catname'); ?></div></li>
					<li><div class="label-form"><?php echo $this->form->renderfield('alias'); ?></div></li>
					<li><div class="label-form"><?php echo $this->form->renderfield('extension'); ?></div></li>
					<li><div class="label-form"><?php echo $this->form->renderfield('parent_id'); ?></div></li>
					<li><div class="label-form"><?php echo $this->form->renderfield('published'); ?></div></li>
					<li><div class="label-form"><?php echo $this->form->renderfield('access'); ?></div></li>
					<li><div class="label-form"><?php echo $this->form->renderfield('color'); ?></div></li>
					<li><div class="label-form"><?php echo $this->form->renderfield('id'); ?></div></li>
				</ul>
				<div class="clr"></div>
				<?php echo $this->form->getLabel('description'); ?>
				<div class="clr"></div>
				<?php echo $this->form->getInput('description'); ?>
			</fieldset>
		</div>

		<div class="col-md-5">
			<?php //echo HTMLHelper::_('sliders.start', 'categories-sliders-'.$this->item->id, array('useCookie'=>1)); ?>
			<div class="accordion" id="accordionCategoriesForm">
				<div class="accordion-item">
					<h2 class="accordion-header" id="publishing-details-header">
					<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#publishing-details" aria-expanded="true" aria-controls="publishing-details">
						<?php echo Text::_('COM_JEM_FIELDSET_PUBLISHING'); ?>
					</button>
					</h2>
					<div id="publishing-details" class="accordion-collapse collapse show" aria-labelledby="publishing-details-header" data-bs-parent="#accordionCategoriesForm">
						<div class="accordion-body">
							<?php echo $this->loadTemplate('options'); ?>
						</div>
					</div>
				</div>
				<div class="accordion-item">
					<h2 class="accordion-header" id="confemail-header">
					<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#confemail" aria-expanded="true" aria-controls="confemail">
						<?php echo Text::_('COM_JEM_CATEGORY_FIELDSET_EMAIL'); ?>
					</button>
					</h2>
					<div id="confemail" class="accordion-collapse collapse" aria-labelledby="confemail-header" data-bs-parent="#accordionCategoriesForm">
						<div class="accordion-body">
							<fieldset class="panelform">
								<ul class="adminformlist">
									<li>
										<div class="label-form"><?php echo $this->form->renderfield('email'); ?></div>
									</li>
								</ul>
							</fieldset>
						</div>

                        <div class="accordion-body">
                            <fieldset class="panelform">
                                <ul class="adminformlist">
                                    <li>
                                       <div class="label-form"><?php echo $this->form->renderfield('emailacljl'); ?></div>
                                    </li>
                                </ul>
                            </fieldset>
                        </div>
                    </div>
				</div>
				<div class="accordion-item">
					<h2 class="accordion-header" id="group-header">
					<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#group" aria-expanded="true" aria-controls="group">
						<?php echo Text::_('COM_JEM_GROUP'); ?>
					</button>
					</h2>
					<div id="group" class="accordion-collapse collapse" aria-labelledby="group-header" data-bs-parent="#accordionCategoriesForm">
						<div class="accordion-body">
							<ul class="adminformlist">
								<li><label for="groups"> <?php echo Text::_('COM_JEM_GROUP').':'; ?></label>
								<?php echo $this->Lists['groups']; ?></li>
							</ul>
						</div>
					</div>
				</div>
				<!-- START OF PANEL IMAGE -->
				<div class="accordion-item">
					<h2 class="accordion-header" id="category-image-header">
					<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#category-image" aria-expanded="true" aria-controls="category-image">
						<?php echo Text::_('COM_JEM_IMAGE'); ?>
					</button>
					</h2>
					<div id="category-image" class="accordion-collapse collapse" aria-labelledby="category-image-header" data-bs-parent="#accordionCategoriesForm">
						<div class="accordion-body">
							<fieldset class="panelform">
								<ul class="adminformlist">
									<li><div class="label-form"><?php echo $this->form->renderfield('image'); ?></div>
									</li>
								</ul>
							</fieldset>
						</div>
					</div>
				</div>
				<div class="accordion-item">
					<h2 class="accordion-header" id="meta-options-header">
					<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#meta-options" aria-expanded="true" aria-controls="meta-options">
						<?php echo Text::_('JGLOBAL_FIELDSET_METADATA_OPTIONS'); ?>
					</button>
					</h2>
					<div id="meta-options" class="accordion-collapse collapse" aria-labelledby="meta-options-header" data-bs-parent="#accordionCategoriesForm">
						<div class="accordion-body">
							<fieldset class="panelform">
								<?php echo $this->loadTemplate('metadata'); ?>
							</fieldset>
						</div>
					</div>
				</div>
			</div>



			<?php  $fieldSets = $this->form->getFieldsets('attribs'); ?>
			<?php foreach ($fieldSets as $name => $fieldSet) : ?>
				<?php $label = !empty($fieldSet->label) ? $fieldSet->label : 'COM_JEM_'.$name.'_FIELDSET_LABEL'; ?>
				<?php if ($name != 'editorConfig' && $name != 'basic-limited') : ?>
					<?php echo HTMLHelper::_('sliders.panel', Text::_($label), $name.'-options'); ?>
					<?php if (isset($fieldSet->description) && trim($fieldSet->description)) : ?>
						<p class="tip"><?php echo $this->escape(Text::_($fieldSet->description));?></p>
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
			<?php //echo HTMLHelper::_('sliders.end'); ?>
		</div>
	</div>
	<div class="clr"></div>
	<div>
		<input type="hidden" name="task" value="" />
		<?php echo HTMLHelper::_('form.token'); ?>
	</div>
</form>
