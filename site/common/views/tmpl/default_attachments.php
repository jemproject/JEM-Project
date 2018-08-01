<?php
/**
 * @version 2.2.3
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>

<?php if (isset($this->attachments) && is_array($this->attachments) && (count($this->attachments) > 0)) : ?>
<div class="files">
<h2 class="description"><?php echo JText::_('COM_JEM_FILES'); ?></h2>
<table class="file">
	<tbody>
	<?php foreach ($this->attachments as $file) : ?>
		<tr>
			<td>
				<?php
				$overlib = JText::_('COM_JEM_FILE').': '.$this->escape($file->file);
				if (!empty($file->name)) {
					$overlib .= '<BR />'.JText::_('COM_JEM_FILE_NAME').': '.$this->escape($file->name);
				}
				if (!empty($file->description)) {
					$overlib .= '<BR />'.JText::_('COM_JEM_FILE_DESCRIPTION').': '.$this->escape($file->description);
				}
				?>
				<span <?php echo JEMOutput::tooltip(JText::_('COM_JEM_DOWNLOAD'), $overlib, 'file-dl-icon file-name'); ?>>
					<?php
						$filename	= $this->escape($file->name ? $file->name : $file->file);
						$image		= JHtml::_('image','com_jem/download_16.png', JText::_('COM_JEM_DOWNLOAD'),NULL,true)." "."<span class=file-name>".$filename."</span>";
						$attribs	= array('class'=>'file-name');
						echo JHtml::_('link','index.php?option=com_jem&task=getfile&format=raw&file='.$file->id.'&'.JSession::getFormToken().'=1',$image,$attribs);
					?>
				</span>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div>
<?php endif;