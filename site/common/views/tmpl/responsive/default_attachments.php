<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

if (isset($this->attachments) && is_array($this->attachments) && (count($this->attachments) > 0)) : ?>
  <hr class="jem-hr" style="display: none;" />
	<div class="jem-files">
		<?php if (count($this->attachments) > 1) : ?>
			<h2 class="jem-files"><?php echo Text::_('COM_JEM_FILES') ; ?></h2>
		<?php else : ?>
			<h2 class="jem-files"><?php echo Text::_('COM_JEM_FILE') ; ?></h2>
		<?php endif; ?>
		<dl class="jem-dl">
			<?php foreach ($this->attachments as $index=>$file) : ?>
        <dt class="jem-files" data-placement="bottom" data-original-title="<?php echo Text::_('COM_JEM_FILE'); ?>"><?php echo Text::_('COM_JEM_FILE').' '.($index+1); ?>:</dt>
				<dd class="jem-files">
					<?php
					$overlib = Text::_('COM_JEM_FILE').': '.$this->escape($file->file);
					if (!empty($file->name)) {
						$overlib .= '<br />'.Text::_('COM_JEM_FILE_NAME').': '.$this->escape($file->name);
					}
					if (!empty($file->description)) {
						$overlib .= '<br />'.Text::_('COM_JEM_FILE_DESCRIPTION').': '.$this->escape($file->description);
					}
					?>
					<span <?php echo JEMOutput::tooltip(Text::_('COM_JEM_DOWNLOAD'), $overlib, 'jem-files'); ?>>
					<?php
						$filename	= $this->escape($file->name ? $file->name : $file->file);
						$image		= $filename.'&nbsp;<i class="fa fa-download"></i>';
						$attribs	= array('class'=>'jem-files');
						echo HTMLHelper::_('link','index.php?option=com_jem&task=getfile&format=raw&file='.$file->id.'&'.JSession::getFormToken().'=1',$image, $attribs);
					?>
					</span>
				</dd>
			<?php endforeach; ?>
		</dl>
	</div>
<?php endif; ?>	
