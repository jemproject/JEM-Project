<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

//$max_custom_fields = $this->settings->get('global_editvenue_maxnumcustomfields', -1); // default to All
?>

	<!-- IMAGE -->
	<?php if ($this->item->locimage || $this->jemsettings->imageenabled != 0) : ?>
	<fieldset class="jem_fldst_image">
		<legend><?php echo Text::_('COM_JEM_IMAGE'); ?></legend>
		<?php
		if ($this->item->locimage) :
			echo JemOutput::flyer($this->item, $this->limage, 'venue', 'locimage');
			?><input type="hidden" name="locimage" id="locimage" value="<?php echo $this->item->locimage; ?>" /><?php
		endif;
		?>
		<?php if ($this->jemsettings->imageenabled != 0) : ?>
		<ul class="adminformlist">
			<li>
				<?php /* We get field with id 'jform_userfile' and name 'jform[userfile]' */ ?>
				<?php echo $this->form->getLabel('userfile'); ?> <?php echo $this->form->getInput('userfile'); ?>
				<button type="button" class="button3 btn-sm btn-secondary" onclick="document.getElementById('jform_userfile').value = ''"><?php echo Text::_('JSEARCH_FILTER_CLEAR') ?></button>
				<?php
				if ($this->item->locimage) :
					echo HTMLHelper::image('media/com_jem/images/publish_r.png', null, array('id' => 'userfile-remove', 'data-id' => $this->item->id, 'data-type' => 'venues', 'title' => Text::_('COM_JEM_REMOVE_IMAGE')));
				endif;
				?>
			</li>
		</ul>
		<input type="hidden" name="removeimage" id="removeimage" value="0" />
		<?php endif; ?>
	</fieldset>
	<?php endif; ?>
	
	<!-- URL -->	
			<fieldset>
				<legend><?php echo Text::_('COM_JEM_EDITVENUE_URL_LEGEND'); ?></legend>
				<ul class="adminformlist">
					<li><?php echo $this->form->getLabel('url'); ?><?php echo $this->form->getInput('url'); ?></li>
				</ul>
			</fieldset>	
	

