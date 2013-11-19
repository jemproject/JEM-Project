<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>

<?php if ($this->attachments && count($this->attachments)):?>
<div class="el-files">
<h2 class="description"><?php echo JText::_('COM_JEM_FILES'); ?></h2>
<table class="event-file">
	<tbody>
	<?php foreach ($this->attachments as $file): ?>
		<tr>
			<td>
				<span class="el-file-dl-icon hasTip"
					title="<?php echo JText::_('COM_JEM_DOWNLOAD').' '.$this->escape($file->file).'::'.$this->escape($file->description);?>">
					<?php
						echo JHtml::_('link','index.php?option=com_jem&task=getfile&format=raw&file='.$file->id,JHtml::_('image','com_jem/download_16.png', JText::_('COM_JEM_DOWNLOAD'),NULL,true));
					?>
				</span>
			</td>
			<td class="el-file-name"><?php echo $this->escape($file->name ? $file->name : $file->file); ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div>
<?php endif;