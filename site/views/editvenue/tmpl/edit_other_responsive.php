<?php
/**
 * @version 2.2.3
 * @package JEM
 * @copyright (C) 2013-2018 joomlaeventmanager.net
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
		<dl class="adminformlist jem-dl-long">
			<?php
				$fields = $this->form->getFieldset('custom');
				if ($max_custom_fields < 0) :
					$max_custom_fields = count($fields);
				endif;
				$cnt = 0;
				foreach($fields as $field) :
					if (++$cnt <= $max_custom_fields) :
					?><dt><?php echo $field->label; ?></dt><dd><?php echo $field->input; ?></dd><?php
					endif;
				endforeach;
			?>
		</dl>
	</fieldset>
  <p>&nbsp;</p>
	<?php endif; ?>

  <!-- IMAGE -->
	<?php if ($this->item->locimage || $this->jemsettings->imageenabled != 0) : ?>
	<fieldset class="jem_fldst_image">
    <legend><?php echo JText::_('COM_JEM_IMAGE'); ?></legend>
		<?php if ($this->jemsettings->imageenabled != 0) : ?>
		<dl class="adminformlist jem-dl">
			<dt><?php echo $this->form->getLabel('userfile'); ?></dt>
        <?php if ($this->item->locimage) : ?>
          <dd>
            <?php echo JEMOutput::flyer($this->item, $this->limage, 'venue', 'locimage'); ?>
            <input type="hidden" name="locimage" id="locimage" value="<?php echo $this->item->locimage; ?>" />		
          </dd>
          <dt> </dt>
        <?php endif; ?>
        <dd><?php echo $this->form->getInput('userfile'); ?></dd>
        <dt> </dt>
				<dd><button type="button" class="button3 btn" onclick="document.getElementById('jform_userfile').value = ''"><?php echo JText::_('JSEARCH_FILTER_CLEAR') ?></button></dd>
				<?php if ($this->item->locimage) : ?>
          <dt><?php echo JText::_('COM_JEM_REMOVE_IMAGE'); ?></dt>
          <dd><?php
            echo JHtml::image('media/com_jem/images/publish_r.png', null, array('id' => 'userfile-remove', 'data-id' => $this->item->id, 'data-type' => 'venues', 'title' => JText::_('COM_JEM_REMOVE_IMAGE'))); ?>
          </dd>
        <?php endif; ?>
			</li>
		</dl>
		<input type="hidden" name="removeimage" id="removeimage" value="0" />
		<?php endif; ?>
	</fieldset>
	<?php endif; ?>


