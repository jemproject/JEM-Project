<?php
/**
 * @version 2.1.4
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

$max_custom_fields = $this->settings->get('global_editvenue_maxnumcustomfields', -1); // default to All
?>

	<!-- CUSTOM FIELDS -->
	<?php if ($max_custom_fields != 0) : ?>
	<fieldset class="panelform">
		<legend><?php echo JText::_('COM_JEM_EDITVENUE_CUSTOMFIELDS'); ?></legend>
		<ul class="adminformlist">
			<?php
				$fields = $this->form->getFieldset('custom');
				if ($max_custom_fields < 0) :
					$max_custom_fields = count($fields);
				endif;
				$cnt = 0;
				foreach($fields as $field) :
					if (++$cnt <= $max_custom_fields) :
					?><li><?php echo $field->label; ?><?php echo $field->input; ?></li><?php
					endif;
				endforeach;
			?>
		</ul>
	</fieldset>
	<?php endif; ?>

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
				<label for="userfile">
					<?php echo JText::_('COM_JEM_IMAGE'); ?>
					<small <?php echo JEMOutput::tooltip(JText::_('COM_JEM_NOTES'), JText::_('COM_JEM_MAX_IMAGE_FILE_SIZE').' '.$this->jemsettings->sizelimit.' kb', 'editlinktip'); ?>>
						<?php echo $this->infoimage; ?>
					</small>
				</label>
				<input class="inputbox <?php echo $this->jemsettings->imageenabled == 2 ? 'required' : ''; ?>" name="userfile" id="userfile" type="file" />
				<button type="button" class="button3" onclick="document.getElementById('userfile').value = ''"><?php echo JText::_('JSEARCH_FILTER_CLEAR') ?></button>
				<?php
				if ($this->item->locimage) :
					echo JHtml::image('media/com_jem/images/publish_r.png', null, array('id' => 'userfile-remove', 'data-id' => $this->item->id, 'data-type' => 'venues', 'title' => JText::_('COM_JEM_REMOVE_IMAGE')));
				endif;
				?>
			</li>
		</ul>
		<input type="hidden" name="removeimage" id="removeimage" value="0" />
	</fieldset>

