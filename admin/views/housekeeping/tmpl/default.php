<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
?>
<form name="adminForm" method="post" id="adminForm">
    <?php if (isset($this->sidebar)) : ?>
    <div id="j-sidebar-container" class="span2">
        <?php echo $this->sidebar; ?>
    </div>
    <div id="j-main-container" class="span10">
    <?php endif; ?>
        <table class="table table-striped housekeeping">
            <tbody>
                <!-- CLEAN EVENT IMG -->
                <tr>
                    <td>
                        <div class="linkicon">
                            <button type="submit" class="jem-housekeeping-action" onclick="this.form.task.value='housekeeping.cleaneventimg';">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleaneventimg.svg', Text::_('COM_JEM_HOUSEKEEPING_EVENT_IMG'), NULL, true); ?>
                            </button>
                        </div>
                    </td>
                    <td>
                    <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_EVENT_IMG'); ?></h3>
                        <?php echo Text::_('COM_JEM_HOUSEKEEPING_EVENT_IMG_DESC'); ?>
                    </td>
                </tr>
            <!-- CLEAN VENUE IMG -->
                <tr>
                    <td>
                        <div class="linkicon">
                            <button type="submit" class="jem-housekeeping-action" onclick="this.form.task.value='housekeeping.cleanvenueimg';">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleanvenueimg.svg', Text::_('COM_JEM_HOUSEKEEPING_VENUE_IMG'), NULL, true); ?>
                            </button>
                        </div>
                    </td>
                    <td>
                    <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_VENUE_IMG'); ?></h3>
                        <?php echo Text::_('COM_JEM_HOUSEKEEPING_VENUE_IMG_DESC'); ?>
                    </td>
                </tr>
            <!-- CLEAN CATEGORY IMG -->
                <tr>
                    <td>
                        <div class="linkicon">
                            <button type="submit" class="jem-housekeeping-action" onclick="this.form.task.value='housekeeping.cleancategoryimg';">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleancategoryimg.svg', Text::_('COM_JEM_HOUSEKEEPING_CATEGORY_IMG'), NULL, true); ?>
                            </button>
                        </div>
                    </td>
                    <td>
                    <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_CATEGORY_IMG'); ?></h3>
                        <?php echo Text::_('COM_JEM_HOUSEKEEPING_CATEGORY_IMG_DESC'); ?>
                    </td>
                </tr>
            <!-- RESIZE THUMBNAILS -->
                <tr>
                    <td>
                        <div class="linkicon">
                            <button type="submit" class="jem-housekeeping-action" onclick="this.form.task.value='housekeeping.resizethumbs';">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-resizethumbs.svg', Text::_('COM_JEM_HOUSEKEEPING_RESIZE_THUMBNAILS'), NULL, true); ?>
                            </button>
                        </div>
                    </td>
                    <td>
                    <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_RESIZE_THUMBNAILS'); ?></h3>
                        <?php echo Text::_('COM_JEM_HOUSEKEEPING_RESIZE_THUMBNAILS_DESC'); ?>
                    </td>
                </tr>
            <!-- CLEAN TRIGGER ARCHIVE -->
                <tr>
                    <td>
                        <div class="linkicon">
                            <button type="submit" class="jem-housekeeping-action" onclick="this.form.task.value='housekeeping.triggerarchive';">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-archive.svg', Text::_('COM_JEM_HOUSEKEEPING_TRIGGER_AUTOARCHIVE'), NULL, true); ?>
                            </button>
                        </div>
                    </td>
                    <td>
                    <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_TRIGGER_AUTOARCHIVE'); ?></h3>
                        <?php echo Text::_('COM_JEM_HOUSEKEEPING_TRIGGER_AUTOARCHIVE_DESC'); ?>
                    </td>
                </tr>
            <!-- TRUNCATE CATEGORY/EVENT REFERENCES -->
                <tr>
                    <td>
                        <div class="linkicon">
                            <button type="submit" class="jem-housekeeping-action" onclick="this.form.task.value='housekeeping.cleanupCatsEventRelations';">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleancatseventrels.svg', Text::_('COM_JEM_HOUSEKEEPING_CATSEVENT_RELS'), NULL, true); ?>
                            </button>
                        </div>
                    </td>
                    <td>
                    <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_CLEANUP_CATSEVENT_RELS'); ?></h3>
                        <?php echo Text::_('COM_JEM_HOUSEKEEPING_CLEANUP_CATSEVENT_RELS_DESC'); ?><br>
                        <?php echo Text::sprintf('COM_JEM_HOUSEKEEPING_TOTAL_CATSEVENT_RELS', $this->totalcats) ?>
                    </td>
                </tr>
            <!-- CLEAN UNUSED ATTACHMENT FILES -->
                <tr>
                    <td>
                        <div class="linkicon">
                            <button type="submit" class="jem-housekeeping-action"
                               onclick="if (!confirm(<?php echo htmlspecialchars(json_encode(Text::_('COM_JEM_HOUSEKEEPING_UNUSED_ATTACHMENT_FILES_CONFIRM')), ENT_QUOTES, 'UTF-8'); ?>)) return false; this.form.task.value='housekeeping.cleanupUnusedAttachmentFiles'; return true;">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleanattachmentfiles.svg', Text::_('COM_JEM_HOUSEKEEPING_UNUSED_ATTACHMENT_FILES'), NULL, true); ?>
                            </button>
                        </div>
                    </td>
                    <td>
                    <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_UNUSED_ATTACHMENT_FILES'); ?></h3>
                        <?php echo Text::_('COM_JEM_HOUSEKEEPING_UNUSED_ATTACHMENT_FILES_DESC'); ?>
                    </td>
                </tr>
            <!-- TRUNCATE ALL DATA -->
                <tr>
                    <td>
                        <div class="linkicon">
                            <button type="submit" class="jem-housekeeping-action" onclick="return jemConfirmTruncateAllData(this.form);">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-truncatealldata.svg', Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA'), NULL, true); ?>
                            </button>
                        </div>
                    </td>
                    <td>
                    <h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA'); ?></h3>
                        <?php echo Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA_DESC'); ?>
                        <fieldset class="options-form jem-housekeeping-file-options">
                            <legend><?php echo Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA_FILES'); ?></legend>
                            <div class="jem-housekeeping-file-option">
                                <span class="jem-housekeeping-file-question"><?php echo Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA_IMAGES_OPTION'); ?></span>
                                <span class="jem-housekeeping-file-choices">
                                    <label for="jem-delete-images-no">
                                        <input type="radio" name="deleteimages" id="jem-delete-images-no" value="0" checked>
                                        <?php echo Text::_('JNO'); ?>
                                    </label>
                                    <label for="jem-delete-images-yes">
                                        <input type="radio" name="deleteimages" id="jem-delete-images-yes" value="1">
                                        <?php echo Text::_('JYES'); ?>
                                    </label>
                                </span>
                            </div>
                            <div class="jem-housekeeping-file-option">
                                <span class="jem-housekeeping-file-question"><?php echo Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA_ATTACHMENTS_OPTION'); ?></span>
                                <span class="jem-housekeeping-file-choices">
                                    <label for="jem-delete-attachments-no">
                                        <input type="radio" name="deleteattachments" id="jem-delete-attachments-no" value="0" checked>
                                        <?php echo Text::_('JNO'); ?>
                                    </label>
                                    <label for="jem-delete-attachments-yes">
                                        <input type="radio" name="deleteattachments" id="jem-delete-attachments-yes" value="1">
                                        <?php echo Text::_('JYES'); ?>
                                    </label>
                                </span>
                            </div>
                        </fieldset>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php if (isset($this->sidebar)) : ?>
            </div>
        <?php endif; ?>
        <input type="hidden" name="task" value="">
        <input type="hidden" name="truncate_nonce" value="<?php echo htmlspecialchars($this->truncateNonce, ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo HTMLHelper::_('form.token'); ?>
</form>
<script>
    function jemConfirmTruncateAllData(form) {
        if (!confirm(<?php echo json_encode(Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA_CONFIRM')); ?>)) {
            return false;
        }

        form.task.value = 'housekeeping.truncateAllData';
        return true;
    }
</script>
