<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

$update = $this->updatedata ?? null;

// No update data at all -> treat as connection problem
if (!$update) {
    $update = new stdClass();
    $update->failed = 1;
} else {
    // Connection worked if 'failed' not set
    $update->failed = $update->failed ?? 0;
}

// Ensure properties exist
$update->current          = $update->current ?? null;
$update->versiondetail    = $update->versiondetail ?? '';
$update->installedversion = $update->installedversion ?? '';
$update->date             = $update->date ?? '';
$update->changes          = is_array($update->changes ?? null) ? $update->changes : [];
$update->notes            = is_array($update->notes ?? null) ? $update->notes : [];
$update->info             = $update->info ?? '';
$update->download         = $update->download ?? '';

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
?>

<form action="<?php echo Route::_('index.php?option=com_jem&view=updatecheck'); ?>" method="post" name="adminForm" id="adminForm">
    <?php if (isset($this->sidebar)) : ?>
    <div id="j-sidebar-container" class="span2">
        <?php echo $this->sidebar; ?>
    </div>
    <div id="j-main-container" class="span10">
        <?php endif; ?>

        <?php if ($update->failed == 0 && $update->current !== null) : ?>
            <div class="update-info">
                <?php
                if ($this->updatedata->current == 0 ) {
                    echo HTMLHelper::_('image', 'com_jem/icon-48-latest-version.svg', NULL, NULL, true);
                } elseif( $this->updatedata->current == -1 ) {
                    echo HTMLHelper::_('image', 'com_jem/icon-48-update.svg', NULL, NULL, true);
                } else {
                    echo HTMLHelper::_('image', 'com_jem/icon-48-unknown-version.svg', NULL, NULL, true);
                }
                ?>
                <?php
                $current = $this->updatedata->current ?? null;
                if ($current === 0) {
                    echo '<p style="color:green;font-weight: bold;">'.Text::_('COM_JEM_UPDATECHECK_LATEST_VERSION').'</p>';
                } elseif( $this->updatedata->current == -1 ) {
                    echo '<p style="color:red;font-weight: bold;">'.Text::_('COM_JEM_UPDATECHECK_OLD_VERSION').'</p>';
                } else {
                    echo '<p style="color:orange;font-weight: bold;">'.Text::_('COM_JEM_UPDATECHECK_NEWER_VERSION').'</p>';
                }
                ?>
            </div>

            <div class="update-details">
                <div class="detail-item">
                    <strong><?php echo Text::_('COM_JEM_UPDATECHECK_VERSION').':'; ?></strong>
                    <span><?php echo $this->updatedata->versiondetail; ?></span>
                </div>
                <div class="detail-item">
                    <strong><?php echo Text::_('COM_JEM_UPDATECHECK_INSTALLED_VERSION').':'; ?></strong>
                    <span><?php echo $this->updatedata->installedversion; ?></span>
                </div>
                <div class="detail-item">
                    <strong><?php echo Text::_('COM_JEM_UPDATECHECK_RELEASE_DATE').':'; ?></strong>
                    <span><?php echo $this->updatedata->date; ?></span>
                </div>
                <div class="detail-item">
                    <strong><?php echo Text::_('COM_JEM_UPDATECHECK_CHANGES').':'; ?></strong>
                    <span>
                    <ul>
                    <?php
                        if (!empty($this->updatedata->changes) && is_array($this->updatedata->changes)) {
                            foreach ($this->updatedata->changes as $change) {
                                echo '<li>'.$change.'</li>';
                            }
                        } ?>
                    </ul>
                    <a href="<?php echo $this->updatedata->info; ?>" target="_blank"><?php echo Text::_('COM_JEM_UPDATECHECK_CHANGELOG'); ?></a></span>
                </div>
                <div class="detail-item">
                    <strong><?php echo Text::_('COM_JEM_UPDATECHECK_INFORMATION').':'; ?></strong>
                    <span>Visit the JEM Website: <a href="https://www.joomlaeventmanager.net/" target="_blank">www.joomlaeventmanager.net</a></span>
                </div>
                <div class="detail-item">
                    <strong><?php echo Text::_('COM_JEM_UPDATECHECK_FILES').':'; ?></strong>
                    <span><a href="<?php echo $this->updatedata->download; ?>" target="_blank"><?php echo Text::_('COM_JEM_UPDATECHECK_DOWNLOAD'); ?></a><br>
                <?php if ($this->updatedata->current == -1 ) : ?>
                    <a href="/administrator/index.php?option=com_installer&view=update&filter[search]=JEM" target="_blank"><?php echo Text::_('COM_JEM_UPDATECHECK_UPDATE'); ?></a>
                <?php endif; ?>
               </span>
                </div>
                <div class="detail-item">
                    <strong><?php echo Text::_('COM_JEM_UPDATECHECK_NOTES').':'; ?></strong>
                    <span>
                    <ul><?php
                        foreach ($this->updatedata->notes as $note) {
                            echo '<li>'.$note.'</li>';
                        } ?>
                    </ul>
                </div>
            </div>
            
            <?php elseif ($update->failed == 0 && $update->current === null) : ?>
                <div class="alert alert-warning">
                    <?php echo Text::_('COM_JEM_UPDATECHECK_NO_COMPATIBLE_VERSION'); ?>
                </div>
            <?php else : ?>

            <table class="updatecheck">
                <tr>
                    <td>
                        <?php
                        echo HTMLHelper::_('image', 'com_jem/icon-48-update.svg', NULL, NULL, true);
                        ?>
                    </td>
                    <td>
                        <?php
                        echo '<span class="text-danger fw-bold">' . htmlspecialchars(Text::_('COM_JEM_UPDATECHECK_CONNECTION_FAILED')) . '</span>';
                        ?>
                    </td>
                </tr>
            </table>

        <?php endif; ?>

        <br>
        <?php if (isset($this->sidebar)) : ?>
    </div>
        <?php endif; ?>

    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
