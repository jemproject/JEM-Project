<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die;


?>

	<!-- CUSTOM FIELDS -->
	<fieldset class="panelform">
	<legend><?php echo JText::_('COM_JEM_EVENT_CUSTOMFIELDS_LEGEND') ?></legend>
		<ul class="adminformlist">
				<?php foreach($this->form->getFieldset('custom') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?>
				</li>
				<?php endforeach; ?>
		</ul>
	</fieldset>
	
	<!-- REGISTRATION -->
	<fieldset class="panelform">
	<legend><?php echo JText::_('COM_JEM_EVENT_REGISTRATION_LEGEND') ?></legend>
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('registra'); ?> <?php echo $this->form->getInput('registra'); ?>
				</li>
				<li><?php echo $this->form->getLabel('unregistra'); ?> <?php echo $this->form->getInput('unregistra'); ?>
				</li>
				<li><?php echo $this->form->getLabel('maxplaces'); ?> <?php echo $this->form->getInput('maxplaces'); ?>
				</li>

				<li><label><?php echo JText::_ ( 'COM_JEM_BOOKED_PLACES' ) . ':';?></label><input id="event-booked" type="text"  disabled="disabled" readonly="readonly" value="<?php echo $this->item->booked; ?>"  />
				</li>

				<?php if ($this->item->maxplaces): ?>
				<li><label><?php echo JText::_ ( 'COM_JEM_AVAILABLE_PLACES' ) . ':';?></label><input id="event-available" type="text"  disabled="disabled" readonly="readonly" value="<?php echo ($this->item->maxplaces-$this->item->booked); ?>" />
				</li>
				<?php
				endif;
				?>

				<li><?php echo $this->form->getLabel('waitinglist'); ?> <?php echo $this->form->getInput('waitinglist'); ?>
				</li>
			</ul>
		</fieldset>
		
		<!-- IMAGE -->
		<fieldset class="jem_fldst_image">
			<legend><?php echo JText::_('COM_JEM_IMAGE'); ?></legend>
					<?php
                        if ($this->item->datimage) :
                                echo JEMOutput::flyer( $this->item, $this->dimage, 'event' );
                        else :
                                echo JHtml::_('image', 'com_jem/noimage.png', JText::_('COM_JEM_NO_IMAGE'));
                        endif;
                        ?>
                        <label for="userfile"><?php echo JText::_('COM_JEM_IMAGE'); ?></label>
                        <input class="inputbox <?php echo $this->jemsettings->imageenabled == 2 ? 'required' : ''; ?>" name="userfile" id="userfile" type="file" />
                        <small class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_NOTES' ); ?>::<?php echo JText::_('COM_JEM_MAX_IMAGE_FILE_SIZE').' '.$this->jemsettings->sizelimit.' kb'; ?>">
                                <?php echo $this->infoimage; ?>
                        </small>
                        <!--<div class="jem_current_image"><?php echo JText::_( 'COM_JEM_CURRENT_IMAGE' ); ?></div>
                        <div class="jem_selected_image"><?php echo JText::_( 'COM_JEM_SELECTED_IMAGE' ); ?></div>-->
          </fieldset>
		
		
		<fieldset class="panelform">
			<legend><?php echo JText::_('COM_JEM_RECURRENCE'); ?></legend>
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('recurrence_type'); ?> <?php echo $this->form->getInput('recurrence_type'); ?>
				</li>
				<li id="recurrence_output">
				<label></label>
				</li>
				<li id="counter_row" style="display: none;">
					<?php echo $this->form->getLabel('recurrence_limit_date'); ?> <?php echo $this->form->getInput('recurrence_limit_date'); ?>
				</li>
			</ul>

				<input type="hidden" name="recurrence_number" id="recurrence_number" value="<?php echo $this->item->recurrence_number;?>" />
				<input type="hidden" name="recurrence_byday" id="recurrence_byday" value="<?php echo $this->item->recurrence_byday;?>" />

			<script
			type="text/javascript">
			<!--
				var $select_output = new Array();
				$select_output[1] = "<?php
				echo JText::_ ( 'COM_JEM_OUTPUT_DAY' );
				?>";
				$select_output[2] = "<?php
				echo JText::_ ( 'COM_JEM_OUTPUT_WEEK' );
				?>";
				$select_output[3] = "<?php
				echo JText::_ ( 'COM_JEM_OUTPUT_MONTH' );
				?>";
				$select_output[4] = "<?php
				echo JText::_ ( 'COM_JEM_OUTPUT_WEEKDAY' );
				?>";

				var $weekday = new Array();
				$weekday[0] = new Array("MO", "<?php echo JText::_ ( 'COM_JEM_MONDAY' ); ?>");
				$weekday[1] = new Array("TU", "<?php echo JText::_ ( 'COM_JEM_TUESDAY' ); ?>");
				$weekday[2] = new Array("WE", "<?php echo JText::_ ( 'COM_JEM_WEDNESDAY' ); ?>");
				$weekday[3] = new Array("TH", "<?php echo JText::_ ( 'COM_JEM_THURSDAY' ); ?>");
				$weekday[4] = new Array("FR", "<?php echo JText::_ ( 'COM_JEM_FRIDAY' ); ?>");
				$weekday[5] = new Array("SA", "<?php echo JText::_ ( 'COM_JEM_SATURDAY' ); ?>");
				$weekday[6] = new Array("SU", "<?php echo JText::_ ( 'COM_JEM_SUNDAY' ); ?>");

				var $before_last = "<?php
				echo JText::_ ( 'COM_JEM_BEFORE_LAST' );
				?>";
				var $last = "<?php
				echo JText::_ ( 'COM_JEM_LAST' );
				?>";
				start_recurrencescript("jform_recurrence_type");
			-->
			</script>
		</fieldset>
		
		
		
