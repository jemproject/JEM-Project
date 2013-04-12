<?php
/**
 * @version $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

defined('_JEXEC') or die;
?>
		<div class="item">
				<div align="center" class="imgBorder">
					<a onclick="window.parent.elSelectImage('<?php echo $this->_tmp_img->name; ?>', '<?php echo $this->_tmp_img->name; ?>');">
						<div class="image">
							<img src="../images/jem/<?php echo $this->folder; ?>/<?php echo $this->_tmp_img->name; ?>"  width="<?php echo $this->_tmp_img->width_60; ?>" height="<?php echo $this->_tmp_img->height_60; ?>" alt="<?php echo $this->_tmp_img->name; ?> - <?php echo $this->_tmp_img->size; ?>" />
						</div>
					</a>
				</div>
			<div class="controls">
				<?php echo $this->_tmp_img->size; ?> -
				<a class="delete-item" href="index.php?option=com_jem&amp;task=delete&amp;controller=imagehandler&amp;tmpl=component&amp;folder=<?php echo $this->folder; ?>&amp;rm[]=<?php echo $this->_tmp_img->name; ?>">
					<img src="images/publish_x.png" width="16" height="16" border="0" alt="<?php echo JText::_( 'COM_JEM_DELETE_IMAGE' ); ?>" />
				</a>
			</div>
			<div class="imageinfo">
				<?php echo $this->escape( substr( $this->_tmp_img->name, 0, 10 ) . ( strlen( $this->_tmp_img->name ) > 10 ? '...' : '')); ?>
			</div>
		</div>