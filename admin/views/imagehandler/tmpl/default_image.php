<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\String\StringHelper;
?>
<?php
$imageName = (string) $this->_tmp_img->name;
$imageNameAttr = htmlspecialchars($imageName, ENT_QUOTES, 'UTF-8');
$folderAttr = htmlspecialchars((string) $this->folder, ENT_QUOTES, 'UTF-8');
$imageUrl = '../images/jem/' . rawurlencode((string) $this->folder) . '/' . rawurlencode($imageName);
$deleteUrl = 'index.php?option=com_jem&task=imagehandler.delete&tmpl=component&folder=' . $folderAttr . '&rm[]=' . rawurlencode($imageName) . '&' . Session::getFormToken() . '=1';
?>
<div class="item-image">
    <div class="imgBorder center">
        <a onclick='window.parent.SelectImage(<?php echo json_encode($imageName); ?>, <?php echo json_encode($imageName); ?>);'>
            <div class="image">
                <img src="<?php echo $imageUrl; ?>"  width="<?php echo (int) $this->_tmp_img->width_60; ?>" height="<?php echo (int) $this->_tmp_img->height_60; ?>" alt="<?php echo $imageNameAttr; ?> - <?php echo htmlspecialchars((string) $this->_tmp_img->size, ENT_QUOTES, 'UTF-8'); ?>" />
            </div>
        </a>
    </div>
    <div class="controls">
        <span class="jem-imagehandler-card-size"><?php echo htmlspecialchars((string) $this->_tmp_img->size, ENT_QUOTES, 'UTF-8'); ?></span>
        <a class="btn btn-sm btn-danger jem-imagehandler-delete delete-item" href="<?php echo htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="icon-trash" aria-hidden="true"></span>
            <?php echo Text::_('COM_JEM_DELETE_IMAGE'); ?>
        </a>
    </div>
    <div class="imageinfo">
        <?php echo $this->escape(StringHelper::substr($this->_tmp_img->name, 0, 10) . (StringHelper::strlen($this->_tmp_img->name) > 10 ? '...' : '')); ?>
    </div>
</div>
