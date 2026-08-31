<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

require_once JPATH_COMPONENT_SITE . '/classes/imagecamera.class.php';

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')->useScript('form.validate')->useScript('showon');
$isNew = empty($this->item->id);
?>
<script>
    Joomla.submitbutton = function (task) {
        var form = document.getElementById('category-form');

        if (task === 'category.cancel' || document.formvalidator.isValid(form)) {
            Joomla.submitform(task, form);
        }
    };
</script>

<div id="jem" class="jem_editcategory<?php echo $this->pageclass_sfx; ?>">
    <h1 class="componentheading">
        <?php echo $isNew
            ? Text::_('COM_JEM_EDITCATEGORY_ADD_CATEGORY')
            : Text::sprintf('COM_JEM_EDITCATEGORY_EDIT_CATEGORY', $this->escape($this->item->catname)); ?>
    </h1>

    <form action="<?php echo Route::_('index.php?option=com_jem&view=editcategory&layout=edit&a_id=' . (int) $this->item->id); ?>"
        method="post" name="adminForm" id="category-form" class="form-validate" enctype="multipart/form-data">
        <div class="jem-submit-toolbar">
            <button type="button" class="btn btn-primary" onclick="Joomla.submitbutton('category.save')">
                <?php echo Text::_('COM_JEM_SAVE'); ?>
            </button>
            <button type="button" class="btn btn-secondary" onclick="Joomla.submitbutton('category.cancel')">
                <?php echo Text::_('JCANCEL'); ?>
            </button>
        </div>

        <?php if ($this->params->get('showintrotext')) : ?>
            <div class="description no_space floattext">
                <?php echo $this->params->get('introtext'); ?>
            </div>
        <?php endif; ?>

        <?php echo HTMLHelper::_('uitab.startTabSet', 'jem-editcategory-tabs', array(
            'active' => 'editcategory-details',
            'recall' => !$isNew,
            'breakpoint' => 768,
        )); ?>
        <?php echo HTMLHelper::_('uitab.addTab', 'jem-editcategory-tabs', 'editcategory-details', Text::_('COM_JEM_DETAILS')); ?>
            <fieldset class="jem-category-editor-fieldset">
                <legend><?php echo Text::_('COM_JEM_CATEGORY_DETAILS'); ?></legend>
                <div class="jem-category-form-grid">
                    <?php foreach (array('catname', 'alias', 'parent_id', 'type_id', 'color') as $fieldName) : ?>
                        <?php if ($this->form->getField($fieldName)) : ?>
                            <div class="jem-category-form-label"><?php echo $this->form->getLabel($fieldName); ?></div>
                            <div class="jem-category-form-control"><?php echo $this->form->getInput($fieldName); ?></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div class="jem-category-description-field">
                    <?php echo $this->form->getLabel('description'); ?>
                    <?php echo $this->form->getInput('description'); ?>
                </div>
            </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'jem-editcategory-tabs', 'editcategory-image', Text::_('COM_JEM_IMAGE')); ?>
            <fieldset class="jem_fldst_image jem-image-upload-panel">
                <legend><?php echo Text::_('COM_JEM_CATEGORY_IMAGE'); ?></legend>
                <?php if ($this->jemsettings->imageenabled != 0) : ?>
                    <?php echo JemImageCamera::resolutionControl(
                        'image_max_dimension',
                        'jem-image-resolution-category',
                        'category',
                        $this->jemsettings
                    ); ?>
                <?php endif; ?>
                <div class="jem-image-upload-layout">
                    <div class="jem-image-upload-list">
                        <div class="jem-image-upload-row">
                            <div class="jem-image-upload-label"><?php echo Text::_('COM_JEM_SERVER_IMAGE'); ?></div>
                            <div class="jem-image-upload-control"><?php echo $this->form->getInput('image'); ?></div>
                        </div>
                        <div class="jem-image-upload-row">
                            <div class="jem-image-upload-label"><?php echo Text::_('COM_JEM_UPLOAD_NEW_IMAGE'); ?></div>
                            <div class="jem-image-upload-control">
                                <?php if ($this->jemsettings->imageenabled != 0) : ?>
                                    <div class="jem-image-file-control"><?php echo $this->form->getInput('userfile'); ?></div>
                                <?php elseif ($this->imageProfileRequired) : ?>
                                    <span class="alert alert-warning d-block mb-0"><?php echo Text::_('COM_JEM_IMAGE_REQUIRED_FRONTEND_DISABLED'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="jem-image-upload-row">
                            <div class="jem-image-upload-label"><?php echo $this->form->getLabel('image_as_default'); ?></div>
                            <div class="jem-image-upload-control"><?php echo $this->form->getInput('image_as_default'); ?></div>
                        </div>
                        <div class="jem-image-upload-row">
                            <div class="jem-image-upload-label"><?php echo $this->form->getLabel('event_image_default_storage'); ?></div>
                            <div class="jem-image-upload-control"><?php echo $this->form->getInput('event_image_default_storage'); ?></div>
                        </div>
                        <?php if ($this->jemsettings->imageenabled != 0) : ?>
                            <div class="jem-image-actions jem-image-actions--last">
                                <button type="button" class="button3 btn btn-secondary btn-sm jem-image-action-button jem-image-clear"
                                    data-jem-image-select="jform_image" data-jem-image-file="jform_userfile">
                                    <?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                        <input type="hidden" name="removeimage" id="removeimage" value="0">
                    </div>
                    <div class="jem-image-preview-stage<?php echo $this->item->image ? ' jem-image-preview-stage--has-image' : ''; ?>">
                        <?php if ($this->item->image) : ?>
                            <div class="jem-image-current">
                                <div class="visually-hidden"><?php echo Text::_('COM_JEM_CURRENT_IMAGE'); ?></div>
                                <?php echo JemOutput::flyer($this->item, $this->cimage, 'category', 'image'); ?>
                            </div>
                        <?php endif; ?>
                        <div class="jem-image-selected-preview" hidden>
                            <div class="visually-hidden"><?php echo Text::_('COM_JEM_SELECTED_IMAGE_PREVIEW'); ?></div>
                            <img src="" alt="<?php echo Text::_('COM_JEM_SELECTED_IMAGE_PREVIEW'); ?>">
                        </div>
                        <span class="jem-image-preview-empty"<?php echo $this->item->image ? ' hidden' : ''; ?>>
                            <?php echo Text::_('COM_JEM_NO_IMAGE_SELECTED'); ?>
                        </span>
                    </div>
                </div>
            </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'jem-editcategory-tabs', 'editcategory-publishing', Text::_('COM_JEM_FIELDSET_PUBLISHING')); ?>
            <fieldset class="jem-category-editor-fieldset">
                <legend><?php echo Text::_('COM_JEM_FIELDSET_PUBLISHING'); ?></legend>
                <div class="jem-category-form-grid">
                    <?php foreach (array('published', 'access', 'language') as $fieldName) : ?>
                        <div class="jem-category-form-label"><?php echo $this->form->getLabel($fieldName); ?></div>
                        <div class="jem-category-form-control"><?php echo $this->form->getInput($fieldName); ?></div>
                    <?php endforeach; ?>
                </div>
                <?php if (!$this->canEditState) : ?>
                    <p class="alert alert-info mb-0"><?php echo Text::_('COM_JEM_CATEGORY_STATE_MANAGED_BY_PUBLISHER'); ?></p>
                <?php endif; ?>
            </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'jem-editcategory-tabs', 'editcategory-other', Text::_('COM_JEM_EDITCATEGORY_OTHER_TAB')); ?>
            <fieldset class="jem-category-editor-fieldset">
                <legend><?php echo Text::_('JGLOBAL_FIELDSET_METADATA_OPTIONS'); ?></legend>
                <?php echo $this->form->renderField('meta_keywords'); ?>
                <?php echo $this->form->renderField('meta_description'); ?>
            </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>
        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>

        <?php echo $this->form->getInput('id'); ?>
        <?php echo $this->form->getInput('image_path'); ?>
        <input type="hidden" name="task" value="">
        <input type="hidden" name="return" value="<?php echo $this->escape($this->return_page); ?>">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>

    <?php if ($this->params->get('showfootertext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('footertext'); ?>
        </div>
    <?php endif; ?>

    <div class="copyright">
        <?php echo JemOutput::footer(); ?>
    </div>
</div>
