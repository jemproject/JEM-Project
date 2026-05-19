<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>


<!-- IMAGE -->
<?php if ($this->item->locimage || $this->jemsettings->imageenabled != 0) : ?>
    <fieldset class="jem_fldst_image jem-image-upload-panel">
        <legend><?php echo Text::_('COM_JEM_EDITVENUE_IMAGE_LEGEND'); ?></legend>
        <dl class="adminformlist jem-dl jem-image-upload-list">
            <dt><?php echo $this->form->getLabel('userfile'); ?></dt>
            <dd class="jem-image-upload-control">
                <?php if ($this->item->locimage) : ?>
                    <div class="jem-image-current">
                        <div class="jem-image-panel-title"><?php echo Text::_('COM_JEM_EDITVENUE_CURRENT_IMAGE'); ?></div>
                        <?php echo JEMOutput::flyer($this->item, $this->limage, 'venue', 'locimage'); ?>
                    </div>
                    <input type="hidden" name="locimage" id="locimage" value="<?php echo $this->escape($this->item->locimage); ?>" />
                <?php endif; ?>

                <?php if ($this->jemsettings->imageenabled != 0) : ?>
                    <div class="jem-image-file-control">
                        <?php echo $this->form->getInput('userfile'); ?>
                    </div>
                    <div class="jem-image-selected-preview" hidden>
                        <div class="jem-image-panel-title"><?php echo Text::_('COM_JEM_EDITVENUE_SELECTED_IMAGE'); ?></div>
                        <img id="jem-selected-venue-image-preview" src="" alt="<?php echo Text::_('COM_JEM_EDITVENUE_SELECTED_IMAGE'); ?>" />
                    </div>
                    <div class="jem-image-actions">
                        <button type="button" class="button3 btn btn-secondary btn-sm" onclick="document.getElementById('jform_userfile').value = ''; document.getElementById('jform_userfile').dispatchEvent(new Event('change'))"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
                        <?php if ($this->item->locimage) : ?>
                            <button type="button" id="userfile-remove" class="button3 btn btn-secondary btn-sm jem-image-remove" data-id="<?php echo (int) $this->item->id; ?>" data-type="venues" title="<?php echo Text::_('COM_JEM_REMOVE_IMAGE'); ?>">
                                <?php echo Text::_('COM_JEM_REMOVE_IMAGE'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="removeimage" id="removeimage" value="0" />
                <?php elseif (!$this->item->locimage) : ?>
                    <span class="jem-image-empty"><?php echo Text::_('COM_JEM_NO_IMAGE_SELECTED'); ?></span>
                <?php endif; ?>
            </dd>
        </dl>
    </fieldset>
<?php endif; ?>
