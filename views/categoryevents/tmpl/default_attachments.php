<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2008 Christoph Lukes
 * @license GNU/GPL, see LICENCE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

defined( '_JEXEC' ) or die;
?>

<?php if ($this->category->attachments && count($this->category->attachments)):?>
<div class="el-files">
<div class="el-section-title"><?php echo JText::_( 'COM_EVENTLIST_FILES' ); ?></div>
<table>
	<tbody>
	<?php foreach ($this->category->attachments as $file): ?>
		<tr>
			<td><span class="el-file-dl-icon hasTip"	
			          title="<?php echo JText::_('COM_EVENTLIST_DOWNLOAD').' '.$this->escape($file->file).'::'.$this->escape($file->description);?>"><?php 
			          echo JHTML::link('index.php?option=com_eventlist&task=getfile&format=raw&file='.$file->id, 
			                           JHTML::image('components/com_eventlist/assets/images/download_16.png', JText::_('COM_EVENTLIST_DOWNLOAD'))); ?></span>
			</td>
			<td class="el-file-name"><?php echo $this->escape($file->name ? $file->name : $file->file); ?></td>
		</tr>
	</tbody>
	<?php endforeach; ?>
</table>
</div>
<?php endif; 