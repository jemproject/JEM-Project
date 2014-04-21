<?php
/**
 * @version 1.9.6
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;
?>

	<!-- CUSTOM FIELDS -->
	<fieldset class="">
		<legend><?php echo JText::_('COM_JEM_EDITVENUE_CUSTOMFIELDS'); ?></legend>
		<ul class="adminformlist">
			<?php foreach($this->form->getFieldset('custom') as $field): ?>
			<li><?php echo $field->label; ?><?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>

	<!-- IMAGE -->
	<fieldset class="jem_fldst_image">
		<legend><?php echo JText::_('COM_JEM_IMAGE'); ?></legend>
		<?php
		if ($this->item->locimage) :
			echo JEMOutput::flyer($this->item, $this->limage, 'venue', 'locimage');
		endif;
		?>
		<ul class="adminformlist">
			<li>
				<label for="userfile"><?php echo JText::_('COM_JEM_IMAGE'); ?></label>
				<input class="inputbox <?php echo $this->jemsettings->imageenabled == 2 ? 'required' : ''; ?>" name="userfile" id="userfile" type="file" />
				<small class="editlinktip hasTip" title="<?php echo JText::_('COM_JEM_NOTES'); ?>::<?php echo JText::_('COM_JEM_MAX_IMAGE_FILE_SIZE').' '.$this->jemsettings->sizelimit.' kb'; ?>">
					<?php echo $this->infoimage; ?>
				</small>
				<?php echo JHtml::image('media/com_jem/images/publish_r.png', null, array('id' => 'userfile-remove', 'data-id' => $this->item->id, 'data-type' => 'venues')); ?>
			</li>
		</ul>
	</fieldset>
	