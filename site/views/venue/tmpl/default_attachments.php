<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>

<?php if ($this->venue->attachments && count($this->venue->attachments)):?>
<div class="el-files">
<div class="el-section-title"><?php echo JText::_( 'COM_JEM_FILES' ); ?></div>
<table>
	<tbody>
	<?php foreach ($this->venue->attachments as $file): ?>
		<tr>
			<td><span class="el-file-dl-icon hasTip"	
			          title="<?php echo JText::_('COM_JEM_DOWNLOAD').' '.$this->escape($file->file).'::'.$this->escape($file->description);?>"><?php 
			          echo JHTML::link('index.php?option=com_jem&task=getfile&format=raw&file='.$file->id, 
			                           JHTML::image('media/com_jem/images/download_16.png', JText::_('COM_JEM_DOWNLOAD'))); ?></span>
			</td>
			<td class="el-file-name"><?php echo $this->escape($file->name ? $file->name : $file->file); ?></td>
		</tr>
	</tbody>
	<?php endforeach; ?>
</table>
</div>
<?php endif; 