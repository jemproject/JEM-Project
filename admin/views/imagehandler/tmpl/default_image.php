<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>
<div class="item">
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
			<?php echo JHtml::_('image','com_jem/publish_r.png',JText::_('COM_JEM_DELETE_IMAGE'),array('title' => JText::_('COM_JEM_DELETE_IMAGE')),true); ?>
		</a>
	</div>
	<div class="imageinfo">
		<?php echo $this->escape(JString::substr($this->_tmp_img->name, 0, 10) . (JString::strlen($this->_tmp_img->name) > 10 ? '...' : '')); ?>
	</div>
</div>