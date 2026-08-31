<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\Filesystem\File;

// Include the component HTML helpers.
HTMLHelper::addIncludePath(JPATH_COMPONENT.'/helpers/html');

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
            ->useScript('inlinehelp')
            ->useScript('form.validate');

$typeField = $this->form->getField('type_id');
?>

<script>
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

<form action="<?php echo Route::_('index.php?option=com_jem&layout=edit&id='.(int) $this->item->id); ?>" method="post" name="adminForm" id="item-form" class="form-validate" enctype="multipart/form-data">
    <div class="row">
        <div class="col-md-7">
            <?php echo HTMLHelper::_('uitab.startTabSet', 'categoryTab', ['active' => 'details', 'recall' => !empty($this->item->id), 'breakpoint' => 768]); ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'categoryTab', 'details', Text::_('COM_JEM_DETAILS')); ?>
            <fieldset class="adminform">
                <ul class="adminformlist">
                    <li><div class="label-form"><?php echo $this->form->renderfield('catname'); ?></div></li>
                    <li><div class="label-form"><?php echo $this->form->renderfield('alias'); ?></div></li>
                    <li><div class="label-form"><?php echo $this->form->renderfield('extension'); ?></div></li>
                    <li><div class="label-form"><?php echo $this->form->renderfield('parent_id'); ?></div></li>
                    <li><div class="label-form"><?php echo $this->form->renderfield('published'); ?></div></li>
                    <li><div class="label-form"><?php echo $this->form->renderfield('access'); ?></div></li>
                    <li><div class="label-form"><?php echo $this->form->renderfield('color'); ?></div></li>
                    <?php if ($typeField) : ?>
                        <li><div class="label-form"><?php echo $this->form->renderfield('type_id'); ?></div></li>
                    <?php else : ?>
                        <?php echo $this->form->getInput('type_id'); ?>
                    <?php endif; ?>
                    <li><div class="label-form"><?php echo $this->form->renderfield('article_category_id'); ?></div></li>
                    <li><div class="label-form"><?php echo $this->form->renderfield('article_create_mode'); ?></div></li>
                    <li><div class="label-form"><?php echo $this->form->renderfield('id'); ?></div></li>
                </ul>
                <div class="clr"></div>
                <?php echo $this->form->getLabel('description'); ?>
                <div class="clr"></div>
                <?php echo $this->form->getInput('description'); ?>
            </fieldset>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'categoryTab', 'image', Text::_('COM_JEM_IMAGE')); ?>
            <div class="jem-admin-image-tab">
                <?php
                $categoryImage = trim((string) ($this->item->image ?? ''));
                $safeCategoryImage = File::makeSafe(basename($categoryImage));
                $categoryImagePath = $categoryImage !== '' && $safeCategoryImage === $categoryImage
                    ? 'images/jem/categories/' . $safeCategoryImage
                    : '';
                echo LayoutHelper::render(
                    'image.editor',
                    array(
                        'form' => $this->form,
                        'settings' => JemHelper::config(),
                        'profile' => JemImageProfilePolicy::CATEGORY,
                        'selectField' => 'image',
                        'fileField' => 'userfile',
                        'removeField' => 'removeimage',
                        'resolutionName' => 'image_max_dimension',
                        'resolutionId' => 'jem-image-resolution-category',
                        'title' => Text::_('COM_JEM_IMAGE_PROFILE_CATEGORY'),
                        'currentImagePath' => $categoryImagePath,
                        'currentImageAlt' => $this->item->catname ?? Text::_('COM_JEM_IMAGE_PROFILE_CATEGORY'),
                        'extraRows' => array(
                            array(
                                'label' => $this->form->getLabel('image_as_default'),
                                'input' => $this->form->getInput('image_as_default'),
                            ),
                            array(
                                'label' => $this->form->getLabel('event_image_default_storage'),
                                'input' => $this->form->getInput('event_image_default_storage'),
                            ),
                        ),
                    ),
                    JPATH_ADMINISTRATOR . '/components/com_jem/layouts'
                );
                ?>
            </div>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
            <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
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
