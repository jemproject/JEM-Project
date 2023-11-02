<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<div class="item-image">
	<div class="imgBorder center">
		<a onclick="window.parent.SelectImage('<?php echo $this->_tmp_img->name; ?>', '<?php echo $this->_tmp_img->name; ?>');">
			<div class="image">
				<img src="../images/jem/<?php echo $this->folder; ?>/<?php echo $this->_tmp_img->name; ?>"  width="<?php echo $this->_tmp_img->width_60; ?>" height="<?php echo $this->_tmp_img->height_60; ?>" alt="<?php echo $this->_tmp_img->name; ?> - <?php echo $this->_tmp_img->size; ?>" />
			</div>
		</a>
	</div>
	<div class="controls">
		<?php echo $this->_tmp_img->size; ?> -
		<a class="delete-item" href="index.php?option=com_jem&amp;task=imagehandler.delete&amp;tmpl=component&amp;folder=<?php echo $this->folder; ?>&amp;rm[]=<?php echo $this->_tmp_img->name; ?>&amp;<?php echo JSession::getFormToken(); ?>=1">
			<?php echo JHtml::_('image','/media/com_jem/images/publish_r.png',Text::_('COM_JEM_DELETE_IMAGE'),array('title' => Text::_('COM_JEM_DELETE_IMAGE')),true); ?>
		</a>
	</div>
	<div class="imageinfo">
		<?php echo $this->escape(\Joomla\String\StringHelper::substr($this->_tmp_img->name, 0, 10) . (\Joomla\String\StringHelper::strlen($this->_tmp_img->name) > 10 ? '...' : '')); ?>
	</div>
</div>
