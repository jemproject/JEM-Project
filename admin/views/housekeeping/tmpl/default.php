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
use Joomla\CMS\Session\Session;
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
                            <a href="index.php?option=com_jem&amp;task=housekeeping.cleaneventimg&amp;<?php echo Session::getFormToken(); ?>=1">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleaneventimg.svg', Text::_('COM_JEM_HOUSEKEEPING_EVENT_IMG'), NULL, true); ?>
                            </a>
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
                            <a href="index.php?option=com_jem&amp;task=housekeeping.cleanvenueimg&amp;<?php echo Session::getFormToken(); ?>=1">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleanvenueimg.svg', Text::_('COM_JEM_HOUSEKEEPING_VENUE_IMG'), NULL, true); ?>
                            </a>
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
                            <a href="index.php?option=com_jem&amp;task=housekeeping.cleancategoryimg&amp;<?php echo Session::getFormToken(); ?>=1">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleancategoryimg.svg', Text::_('COM_JEM_HOUSEKEEPING_CATEGORY_IMG'), NULL, true); ?>
                            </a>
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
                            <a href="index.php?option=com_jem&amp;task=housekeeping.resizethumbs&amp;<?php echo Session::getFormToken(); ?>=1">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-resizethumbs.svg', Text::_('COM_JEM_HOUSEKEEPING_RESIZE_THUMBNAILS'), NULL, true); ?>
                            </a>
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
                            <a href="index.php?option=com_jem&amp;task=housekeeping.triggerarchive&amp;<?php echo Session::getFormToken(); ?>=1">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-archive.svg', Text::_('COM_JEM_HOUSEKEEPING_TRIGGER_AUTOARCHIVE'), NULL, true); ?>
                            </a>
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
                            <a href="index.php?option=com_jem&amp;task=housekeeping.cleanupCatsEventRelations&amp;<?php echo Session::getFormToken(); ?>=1">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleancatseventrels.svg', Text::_('COM_JEM_HOUSEKEEPING_CATSEVENT_RELS'), NULL, true); ?>
                            </a>
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
                            <a href="index.php?option=com_jem&amp;task=housekeeping.cleanupUnusedAttachmentFiles&amp;<?php echo Session::getFormToken(); ?>=1"
                               onclick="return confirm(<?php echo htmlspecialchars(json_encode(Text::_('COM_JEM_HOUSEKEEPING_UNUSED_ATTACHMENT_FILES_CONFIRM')), ENT_QUOTES, 'UTF-8'); ?>);">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleanattachmentfiles.svg', Text::_('COM_JEM_HOUSEKEEPING_UNUSED_ATTACHMENT_FILES'), NULL, true); ?>
                            </a>
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
                            <a href="index.php?option=com_jem&amp;task=housekeeping.truncateAllData&amp;<?php echo Session::getFormToken(); ?>=1" onclick="return jemConfirmTruncateAllData(this);">
                                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-truncatealldata.svg', Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA'), NULL, true); ?>
                            </a>
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
                                        <input type="radio" name="jem_delete_images" id="jem-delete-images-no" value="0" checked>
                                        <?php echo Text::_('JNO'); ?>
                                    </label>
                                    <label for="jem-delete-images-yes">
                                        <input type="radio" name="jem_delete_images" id="jem-delete-images-yes" value="1">
                                        <?php echo Text::_('JYES'); ?>
                                    </label>
                                </span>
                            </div>
                            <div class="jem-housekeeping-file-option">
                                <span class="jem-housekeeping-file-question"><?php echo Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA_ATTACHMENTS_OPTION'); ?></span>
                                <span class="jem-housekeeping-file-choices">
                                    <label for="jem-delete-attachments-no">
                                        <input type="radio" name="jem_delete_attachments" id="jem-delete-attachments-no" value="0" checked>
                                        <?php echo Text::_('JNO'); ?>
                                    </label>
                                    <label for="jem-delete-attachments-yes">
                                        <input type="radio" name="jem_delete_attachments" id="jem-delete-attachments-yes" value="1">
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
</form>
<script>
    function jemConfirmTruncateAllData(link) {
        if (!confirm(<?php echo json_encode(Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA_CONFIRM')); ?>)) {
            return false;
        }

        if (document.querySelector('input[name="jem_delete_images"]:checked').value === '1') {
            link.href += '&deleteimages=1';
        }

        if (document.querySelector('input[name="jem_delete_attachments"]:checked').value === '1') {
            link.href += '&deleteattachments=1';
        }

        return true;
    }
</script>
