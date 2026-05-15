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

$jemsettings = JemHelper::config();
$itemParams = null;
if (!empty($this->attachmentParams) && is_object($this->attachmentParams) && method_exists($this->attachmentParams, 'get')) {
    $itemParams = $this->attachmentParams;
} elseif (!empty($this->item->params) && is_object($this->item->params) && method_exists($this->item->params, 'get')) {
    $itemParams = $this->item->params;
} elseif (!empty($this->venue->params) && is_object($this->venue->params) && method_exists($this->venue->params, 'get')) {
    $itemParams = $this->venue->params;
}
$attachmentLayoutOverride = $itemParams ? (string) $itemParams->get('attachments_layout', '') : '';
$attachmentLayout = in_array($attachmentLayoutOverride, array('row', 'column'), true)
    ? $attachmentLayoutOverride
    : (isset($jemsettings->attachments_layout) && in_array($jemsettings->attachments_layout, array('row', 'column'), true)
    ? $jemsettings->attachments_layout
    : 'column');
$attachmentIconSize = isset($jemsettings->attachments_icon_size)
    ? (string) $jemsettings->attachments_icon_size
    : (!isset($jemsettings->attachments_show_icon) || (int) $jemsettings->attachments_show_icon === 1 ? 'normal' : 'none');
$attachmentIconSize = in_array($attachmentIconSize, array('none', 'normal', 'medium', 'large'), true) ? $attachmentIconSize : 'normal';
$showAttachmentIcon = $attachmentIconSize !== 'none';
?>

<?php if (isset($this->attachments) && is_array($this->attachments) && (count($this->attachments) > 0)) : ?>
    <div class="files">
        <h2 class="description"><?php echo Text::_('COM_JEM_FILES'); ?></h2>
        <div class="jem-attachments-list jem-attachments-layout-<?php echo $this->escape($attachmentLayout); ?> jem-attachments-icons-<?php echo $this->escape($attachmentIconSize); ?>">
            <?php foreach ($this->attachments as $file) : ?>
                <?php
                $fileIcon = $showAttachmentIcon
                    ? '<span class="jem-attachment-file-icon jem-attachment-file-icon-' . $this->escape($attachmentIconSize) . ' ' . $this->escape(JemAttachment::getIconClass($file->file)) . '" aria-hidden="true"></span>'
                    : '';
                ?>
                <div class="jem-attachment-row">
                    <div class="jem-attachment-icon-cell"><?php echo $fileIcon; ?></div>
                    <div class="jem-attachment-content">
                    <?php
                    $overlib = Text::_('COM_JEM_FILE').': '.$this->escape($file->file);
                    if (!empty($file->name)) {
                        $overlib .= '<br>'.Text::_('COM_JEM_FILE_NAME').': '.$this->escape($file->name);
                    }
                    if (!empty($file->description)) {
                        $overlib .= '<br>'.Text::_('COM_JEM_FILE_DESCRIPTION').': '.$this->escape($file->description);
                    }
                    ?>
                        <span <?php echo JEMOutput::tooltip(Text::_('COM_JEM_DOWNLOAD'), $overlib, 'file-dl-icon file-name'); ?>>
                    <?php
                    $filename = $this->escape($file->name ? $file->name : $file->file);
                    $linkText = '<span class="file-name">' . $filename . '</span> <span class="fa fa-download" aria-hidden="true"></span>';
                    $attribs = array('class'=>'file-name');
                    echo HTMLHelper::_('link','index.php?option=com_jem&task=getfile&format=raw&file='.$file->id.'&'.Session::getFormToken().'=1',$linkText,$attribs);
                    ?>
                </span>
                        <?php if (!empty($file->description)) : ?>
                            <div class="jem-attachment-description"><?php echo nl2br($this->escape($file->description)); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif;
