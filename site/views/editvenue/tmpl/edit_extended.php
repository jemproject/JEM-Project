<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

require_once JPATH_SITE . '/components/com_jem/classes/imagecamera.class.php';

//$max_custom_fields = $this->settings->get('global_editvenue_maxnumcustomfields', -1); // default to All
?>

<!-- IMAGE -->
<?php if ($this->item->locimage || $this->jemsettings->imageenabled != 0 || $this->imageProfileRequired) : ?>
    <fieldset class="jem_fldst_image jem-image-upload-panel">
        <legend><?php echo Text::_('COM_JEM_IMAGE'); ?></legend>
        <?php if ($this->jemsettings->imageenabled != 0) : ?>
            <?php echo JemImageCamera::resolutionControl(
                'image_max_dimension',
                'jem-image-resolution-venue',
                'venue',
                $this->jemsettings
            ); ?>
        <?php endif; ?>
        <div class="jem-image-upload-layout">
            <div class="jem-image-upload-list">
                <div class="jem-image-upload-row">
                    <div class="jem-image-upload-label">
                        <?php echo Text::_('COM_JEM_SERVER_IMAGE'); ?>
                    </div>
                    <div class="jem-image-upload-control">
                        <?php echo $this->form->getInput('locimage'); ?>
                    </div>
                </div>
                <div class="jem-image-upload-row">
                    <div class="jem-image-upload-label">
                        <?php echo Text::_('COM_JEM_UPLOAD_NEW_IMAGE'); ?>
                    </div>
                    <div class="jem-image-upload-control">
                    <?php if ($this->item->locimage) : ?>
                        <input type="hidden" name="locimage" id="locimage" value="<?php echo $this->escape($this->item->locimage); ?>" />
                    <?php endif; ?>
                    <?php if ($this->jemsettings->imageenabled != 0) : ?>
                        <div class="jem-image-file-control">
                            <?php echo $this->form->getInput('userfile'); ?>
                        </div>
                        <input type="hidden" name="removeimage" id="removeimage" value="0" />
                    <?php elseif ($this->imageProfileRequired) : ?>
                        <span class="alert alert-warning d-block mb-0"><?php echo Text::_('COM_JEM_IMAGE_REQUIRED_FRONTEND_DISABLED'); ?></span>
                    <?php elseif (!$this->item->locimage) : ?>
                        <span class="jem-image-empty"><?php echo Text::_('COM_JEM_NO_IMAGE_SELECTED'); ?></span>
                    <?php endif; ?>
                    </div>
                </div>
                <div class="jem-image-upload-row jem-image-upload-row--alt">
                    <div class="jem-image-upload-label">
                        <?php echo $this->form->getLabel('locimage_alt'); ?>
                    </div>
                    <div class="jem-image-upload-control">
                        <?php echo $this->form->getInput('locimage_alt'); ?>
                        <?php echo $this->form->getInput('image_path'); ?>
                    </div>
                </div>
                <?php if ($this->jemsettings->imageenabled != 0) : ?>
                    <div class="jem-image-actions jem-image-actions--last">
                        <button type="button" class="button3 btn btn-secondary btn-sm jem-image-action-button jem-image-clear" data-jem-image-select="jform_locimage" data-jem-image-file="jform_userfile"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="jem-image-preview-stage<?php echo $this->item->locimage ? ' jem-image-preview-stage--has-image' : ''; ?>">
                <?php if ($this->item->locimage) : ?>
                    <div class="jem-image-current">
                        <div class="visually-hidden"><?php echo Text::_('COM_JEM_EDITVENUE_CURRENT_IMAGE'); ?></div>
                        <?php echo JemOutput::flyer($this->item, $this->limage, 'venue', 'locimage'); ?>
                    </div>
                <?php endif; ?>
                <div class="jem-image-selected-preview" hidden>
                    <div class="visually-hidden"><?php echo Text::_('COM_JEM_EDITVENUE_SELECTED_IMAGE'); ?></div>
                    <img id="jem-selected-venue-image-preview" src="" alt="<?php echo Text::_('COM_JEM_EDITVENUE_SELECTED_IMAGE'); ?>" />
                </div>
                <span class="jem-image-preview-empty"<?php echo $this->item->locimage ? ' hidden' : ''; ?>>
                    <?php echo Text::_('COM_JEM_NO_IMAGE_SELECTED'); ?>
                </span>
            </div>
        </div>
    </fieldset>
<?php endif; ?>
