<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

//$max_custom_fields = $this->settings->get('global_editvenue_maxnumcustomfields', -1); // default to All
?>

<!-- IMAGE -->
<?php if ($this->item->locimage || $this->jemsettings->imageenabled != 0) : ?>
    <fieldset class="jem_fldst_image jem-image-upload-panel">
        <legend><?php echo Text::_('COM_JEM_IMAGE'); ?></legend>
        <div class="jem-image-upload-layout">
            <div class="jem-image-upload-list">
                <div class="jem-image-upload-row">
                    <div class="jem-image-upload-label">
                        <?php echo $this->form->getLabel('userfile'); ?>
                    </div>
                    <div class="jem-image-upload-control">
                    <?php if ($this->item->locimage) : ?>
                        <input type="hidden" name="locimage" id="locimage" value="<?php echo $this->escape($this->item->locimage); ?>" />
                    <?php endif; ?>
                    <?php if ($this->jemsettings->imageenabled != 0) : ?>
                        <div class="jem-image-file-control">
                            <div class="jem-image-file-input"><?php echo $this->form->getInput('userfile'); ?></div>
                        </div>
                        <div class="jem-image-actions">
                            <button type="button" class="button3 btn btn-secondary btn-sm jem-image-action-button jem-image-clear"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
                        </div>
                        <input type="hidden" name="removeimage" id="removeimage" value="0" />
                    <?php elseif (!$this->item->locimage) : ?>
                        <span class="jem-image-empty"><?php echo Text::_('COM_JEM_NO_IMAGE_SELECTED'); ?></span>
                    <?php endif; ?>
                    </div>
                </div>
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
