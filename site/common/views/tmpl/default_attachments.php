<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>

<?php if ($this->attachments && count($this->attachments)):?>
<div class="files">
<h2 class="description"><?php echo JText::_('COM_JEM_FILES'); ?></h2>
<table class="file">
	<tbody>
	<?php foreach ($this->attachments as $file): ?>
		<tr>
			<td>
				<span class="file-dl-icon hasTip file-name"
					title="	
					<?php 
					$overlib  = JText::_('COM_JEM_FILE').': '.$this->escape($file->file).'<BR />';
					$overlib .= JText::_('COM_JEM_FILE_NAME').': '.$this->escape($file->name).'<BR />';
					$overlib .= JText::_('COM_JEM_FILE_DESCRIPTION').': '.$this->escape($file->description);
					
					echo JText::_('COM_JEM_DOWNLOAD').'::'.$overlib;?>">
					
					<?php
						$filename	= $this->escape($file->name ? $file->name : $file->file);
						$image		= JHtml::_('image','com_jem/download_16.png', JText::_('COM_JEM_DOWNLOAD'),NULL,true)." "."<span class=file-name>".$filename."</span>";
						$attribs	= array('class'=>'file-name');
						echo JHtml::_('link','index.php?option=com_jem&task=getfile&format=raw&file='.$file->id,$image,$attribs);
					?>
				</span>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div>
<?php endif;