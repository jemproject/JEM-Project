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
<?php
$imageName = (string) $this->_tmp_img->name;
$imageNameAttr = htmlspecialchars($imageName, ENT_QUOTES, 'UTF-8');
$folderAttr = htmlspecialchars((string) $this->folder, ENT_QUOTES, 'UTF-8');
$imageUrl = '../images/jem/' . rawurlencode((string) $this->folder) . '/' . rawurlencode($imageName);
$deleteUrl = 'index.php?option=com_jem&task=imagehandler.delete&tmpl=component&folder=' . $folderAttr . '&rm[]=' . rawurlencode($imageName) . '&' . Session::getFormToken() . '=1';
$modified = !empty($this->_tmp_img->modified) ? HTMLHelper::_('date', $this->_tmp_img->modified, Text::_('DATE_FORMAT_LC4')) : '-';
?>
<div class="item-image">
    <div class="imgBorder center">
        <a onclick='window.parent.SelectImage(<?php echo json_encode($imageName); ?>, <?php echo json_encode($imageName); ?>);'>
            <div class="image">
                <img src="<?php echo $imageUrl; ?>" alt="<?php echo $imageNameAttr; ?> - <?php echo htmlspecialchars((string) $this->_tmp_img->size, ENT_QUOTES, 'UTF-8'); ?>" />
            </div>
        </a>
    </div>
    <div class="imageinfo">
        <?php echo $this->escape($this->_tmp_img->name); ?>
    </div>
    <div class="controls">
        <span class="jem-imagehandler-card-size"><?php echo htmlspecialchars((string) $this->_tmp_img->size, ENT_QUOTES, 'UTF-8'); ?></span>
        <span class="jem-imagehandler-card-size"><?php echo (int) $this->_tmp_img->width; ?> x <?php echo (int) $this->_tmp_img->height; ?> px</span>
        <span class="jem-imagehandler-card-date"><?php echo htmlspecialchars($modified, ENT_QUOTES, 'UTF-8'); ?></span>
        <a class="btn btn-sm btn-danger jem-imagehandler-delete delete-item" href="<?php echo htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="icon-times" aria-hidden="true"></span>
            <span class="jem-imagehandler-delete-label"><?php echo Text::_('COM_JEM_DELETE_IMAGE'); ?></span>
        </a>
    </div>
</div>
