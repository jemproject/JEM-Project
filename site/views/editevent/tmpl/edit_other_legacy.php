<?php
/**
 * @version 2.2.3
 * @package JEM
 * @copyright (C) 2013-2018 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

$max_custom_fields = $this->settings->get('global_editevent_maxnumcustomfields', -1); // default to All
?>

	<!-- CUSTOM FIELDS -->
	<?php if ($max_custom_fields != 0) : ?>
	<fieldset class="panelform">
		<legend><?php echo JText::_('COM_JEM_EVENT_CUSTOMFIELDS_LEGEND'); ?></legend>
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

	<!-- REGISTRATION -->
	<fieldset class="panelform">
		<legend><?php echo JText::_('COM_JEM_EVENT_REGISTRATION_LEGEND'); ?></legend>
		<ul class="adminformlist">
		<?php if ($this->jemsettings->showfroregistra == 0) : ?>
			<li><?php echo $this->form->getLabel('registra'); ?> <?php echo JText::_('JNO'); ?></li>
		<?php else : ?>
			<?php if ($this->jemsettings->showfroregistra == 1) : ?>
			<li><?php echo $this->form->getLabel('registra'); ?> <?php echo JText::_('JYES'); ?></li>
			<?php else : ?>
			<li><?php echo $this->form->getLabel('registra'); ?> <?php echo $this->form->getInput('registra'); ?></li>
			<?php endif; ?>
			<?php if ($this->jemsettings->regallowinvitation == 1) : ?>
			<li><?php echo $this->form->getLabel('reginvitedonly'); ?> <?php echo $this->form->getInput('reginvitedonly'); ?></li>
			<?php endif; ?>
			<li><?php echo $this->form->getLabel('unregistra'); ?> <?php echo $this->form->getInput('unregistra'); ?>
				<?php echo $this->form->getInput('unregistra_until'); ?>
				<span id="jform_unregistra_until2"><?php echo JText::_('COM_JEM_EDITEVENT_FIELD_ANNULATION_UNTIL_POSTFIX'); ?></span>
			</li>
			<li><?php echo $this->form->getLabel('maxplaces'); ?> <?php echo $this->form->getInput('maxplaces'); ?></li>
			<li><?php echo $this->form->getLabel('waitinglist'); ?> <?php echo $this->form->getInput('waitinglist'); ?></li>
			<?php if ($this->jemsettings->regallowinvitation == 1) : ?>
			<li><?php echo $this->form->getLabel('invited'); ?> <?php echo $this->form->getInput('invited'); ?></li>
			<?php endif; ?>
			<hr>
			<li><?php echo $this->form->getLabel('booked'); ?> <?php echo $this->form->getInput('booked'); ?></li>
			<?php if ($this->item->maxplaces): ?>
			<li><?php echo $this->form->getLabel('avplaces'); ?> <?php echo $this->form->getInput('avplaces'); ?></li>
			<?php endif; ?>
		<?php endif; ?>
		</ul>
	</fieldset>

	<!-- IMAGE -->
	<?php if ($this->item->datimage || $this->jemsettings->imageenabled != 0) : ?>
	<fieldset class="jem_fldst_image">
		<legend><?php echo JText::_('COM_JEM_IMAGE'); ?></legend>
		<?php
		if ($this->item->datimage) :
			echo JemOutput::flyer($this->item, $this->dimage, 'event', 'datimage');
			?><input type="hidden" name="datimage" id="datimage" value="<?php echo $this->item->datimage; ?>" /><?php
		endif;
		?>
		<?php if ($this->jemsettings->imageenabled != 0) : ?>
		<ul class="adminformlist">
			<li>
				<?php /* We get field with id 'jform_userfile' and name 'jform[userfile]' */ ?>
				<?php echo $this->form->getLabel('userfile'); ?> <?php echo $this->form->getInput('userfile'); ?>
				<button type="button" class="button3" onclick="document.getElementById('jform_userfile').value = ''"><?php echo JText::_('JSEARCH_FILTER_CLEAR') ?></button>
				<?php
				if ($this->item->datimage) :
					echo JHtml::image('media/com_jem/images/publish_r.png', null, array('id' => 'userfile-remove', 'data-id' => $this->item->id, 'data-type' => 'events', 'title' => JText::_('COM_JEM_REMOVE_IMAGE')));
				endif;
				?>
			</li>
		</ul>
		<input type="hidden" name="removeimage" id="removeimage" value="0" />
		<?php endif; ?>
	</fieldset>
	<?php endif; ?>

	<!-- Recurrence -->
	<fieldset class="panelform">
		<legend><?php echo JText::_('COM_JEM_RECURRENCE'); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('recurrence_type'); ?> <?php echo $this->form->getInput('recurrence_type'); ?></li>
			<li id="recurrence_output"><label></label></li>
			<li id="counter_row" style="display: none;">
				<?php echo $this->form->getLabel('recurrence_limit_date'); ?>
				<?php echo $this->form->getInput('recurrence_limit_date'); ?>
				<br><div class="recurrence_notice"><small>
				<?php
				$anticipation = $this->jemsettings->recurrence_anticipation;
				$limitdate = new JDate('now +' . $anticipation . 'days');
				$limitdate = JemOutput::formatLongDateTime($limitdate->format('Y-m-d'), '');
				echo JText::sprintf(JText::_('COM_JEM_EDITEVENT_NOTICE_GENSHIELD'), $limitdate);
				?></small></div>
			</li>
		</ul>
		<input type="hidden" name="recurrence_number" id="recurrence_number" value="<?php echo $this->item->recurrence_number;?>" />
		<input type="hidden" name="recurrence_byday" id="recurrence_byday" value="<?php echo $this->item->recurrence_byday;?>" />

		<script type="text/javascript">
		<!--
		var $select_output = new Array();
			$select_output[1] = "<?php
			echo JText::_('COM_JEM_OUTPUT_DAY');
			?>";
			$select_output[2] = "<?php
			echo JText::_('COM_JEM_OUTPUT_WEEK');
			?>";
			$select_output[3] = "<?php
			echo JText::_('COM_JEM_OUTPUT_MONTH');
			?>";
			$select_output[4] = "<?php
			echo JText::_('COM_JEM_OUTPUT_WEEKDAY');
			?>";

		var $weekday = new Array();
			$weekday[0] = new Array("MO", "<?php echo JText::_('COM_JEM_MONDAY'); ?>");
			$weekday[1] = new Array("TU", "<?php echo JText::_('COM_JEM_TUESDAY'); ?>");
			$weekday[2] = new Array("WE", "<?php echo JText::_('COM_JEM_WEDNESDAY'); ?>");
			$weekday[3] = new Array("TH", "<?php echo JText::_('COM_JEM_THURSDAY'); ?>");
			$weekday[4] = new Array("FR", "<?php echo JText::_('COM_JEM_FRIDAY'); ?>");
			$weekday[5] = new Array("SA", "<?php echo JText::_('COM_JEM_SATURDAY'); ?>");
			$weekday[6] = new Array("SU", "<?php echo JText::_('COM_JEM_SUNDAY'); ?>");

		var $before_last = "<?php
			echo JText::_('COM_JEM_BEFORE_LAST');
			?>";
		var $last = "<?php
			echo JText::_('COM_JEM_LAST');
			?>";
			start_recurrencescript("jform_recurrence_type");
		-->
		</script>

		<?php /* show "old" recurrence settings for information */
		if (!empty($this->item->recurr_bak->recurrence_type)) {
			$recurr_type = '';
			$nullDate = JFactory::getDbo()->getNullDate();
			$rlDate = $this->item->recurr_bak->recurrence_limit_date;
			if (!empty($rlDate) && (strpos($nullDate, $rlDate) !== 0)) {
				$recurr_limit_date = JemOutput::formatdate($rlDate);
			} else {
				$recurr_limit_date = JText::_('COM_JEM_UNLIMITED');
			}

			switch ($this->item->recurr_bak->recurrence_type) {
			case 1:
				$recurr_type = JText::_('COM_JEM_DAYLY');
				$recurr_info = str_ireplace('[placeholder]',
				                            $this->item->recurr_bak->recurrence_number,
				                            JText::_('COM_JEM_OUTPUT_DAY'));
				break;
			case 2:
				$recurr_type = JText::_('COM_JEM_WEEKLY');
				$recurr_info = str_ireplace('[placeholder]',
				                            $this->item->recurr_bak->recurrence_number,
				                            JText::_('COM_JEM_OUTPUT_WEEK'));
				break;
			case 3:
				$recurr_type = JText::_('COM_JEM_MONTHLY');
				$recurr_info = str_ireplace('[placeholder]',
				                            $this->item->recurr_bak->recurrence_number,
				                            JText::_('COM_JEM_OUTPUT_MONTH'));
				break;
			case 4:
				$recurr_type = JText::_('COM_JEM_WEEKDAY');
				$recurr_byday = preg_replace('/(,)([^ ,]+)/', '$1 $2', $this->item->recurr_bak->recurrence_byday);
				$recurr_days = str_ireplace(array('MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SO'),
				                            array(JText::_('COM_JEM_MONDAY'), JText::_('COM_JEM_TUESDAY'),
				                                  JText::_('COM_JEM_WEDNESDAY'), JText::_('COM_JEM_THURSDAY'),
				                                  JText::_('COM_JEM_FRIDAY'), JText::_('COM_JEM_SATURDAY'),
				                                  JText::_('COM_JEM_SUNDAY')),
				                            $recurr_byday);
				$recurr_num  = str_ireplace(array('5', '6'),
				                            array(JText::_('COM_JEM_LAST'), JText::_('COM_JEM_BEFORE_LAST')),
				                            $this->item->recurr_bak->recurrence_number);
				$recurr_info = str_ireplace(array('[placeholder]', '[placeholder_weekday]'),
				                            array($recurr_num, $recurr_days),
				                            JText::_('COM_JEM_OUTPUT_WEEKDAY'));
				break;
			default:
				break;
			}

			if (!empty($recurr_type)) {
		 ?>
				<hr>
				<p><strong><?php echo JText::_('COM_JEM_RECURRING_INFO_TITLE'); ?></strong></p>
				<ul class="adminformlist">
					<li>
						<label><?php echo JText::_('COM_JEM_RECURRENCE'); ?></label>
						<input type="text" class="readonly" readonly="readonly" value="<?php echo $recurr_type; ?>">
					</li>
					<li>
						<div class="clear"></div>
						<label> </label>
						<?php echo $recurr_info; ?>
					</li>
					<li>
						<label><?php echo JText::_('COM_JEM_RECURRENCE_COUNTER'); ?></label>
						<input type="text" class="readonly" readonly="readonly" value="<?php echo $recurr_limit_date; ?>">
					</li>
				</ul>
		<?php
			}
		} ?>
	</fieldset>
