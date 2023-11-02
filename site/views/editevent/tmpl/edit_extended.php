<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

?>

<!-- RECURRENCE START -->
<div>
	<fieldset class="panelform">
		<legend><?php echo Text::_('COM_JEM_RECURRENCE'); ?></legend>
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
				echo Text::sprintf(Text::_('COM_JEM_EDITEVENT_NOTICE_GENSHIELD'), $limitdate);
				?></small></div>
			</li>
		</ul>
		<input type="hidden" name="recurrence_number" id="recurrence_number" value="<?php echo $this->item->recurrence_number;?>" />
		<input type="hidden" name="recurrence_byday" id="recurrence_byday" value="<?php echo $this->item->recurrence_byday;?>" />

		<script type="text/javascript">
		<!--
		var $select_output = new Array();
			$select_output[1] = "<?php
			echo Text::_('COM_JEM_OUTPUT_DAY');
			?>";
			$select_output[2] = "<?php
			echo Text::_('COM_JEM_OUTPUT_WEEK');
			?>";
			$select_output[3] = "<?php
			echo Text::_('COM_JEM_OUTPUT_MONTH');
			?>";
			$select_output[4] = "<?php
			echo Text::_('COM_JEM_OUTPUT_WEEKDAY');
			?>";

		var $weekday = new Array();
			$weekday[0] = new Array("MO", "<?php echo Text::_('COM_JEM_MONDAY'); ?>");
			$weekday[1] = new Array("TU", "<?php echo Text::_('COM_JEM_TUESDAY'); ?>");
			$weekday[2] = new Array("WE", "<?php echo Text::_('COM_JEM_WEDNESDAY'); ?>");
			$weekday[3] = new Array("TH", "<?php echo Text::_('COM_JEM_THURSDAY'); ?>");
			$weekday[4] = new Array("FR", "<?php echo Text::_('COM_JEM_FRIDAY'); ?>");
			$weekday[5] = new Array("SA", "<?php echo Text::_('COM_JEM_SATURDAY'); ?>");
			$weekday[6] = new Array("SU", "<?php echo Text::_('COM_JEM_SUNDAY'); ?>");

		var $before_last = "<?php
			echo Text::_('COM_JEM_BEFORE_LAST');
			?>";
		var $last = "<?php
			echo Text::_('COM_JEM_LAST');
			?>";
			start_recurrencescript("jform_recurrence_type");
		-->
		</script>

		<?php /* show "old" recurrence settings for information */
		if (!empty($this->item->recurr_bak->recurrence_type)) {
			$recurr_type = '';
            $nullDate = Factory::getContainer()->get('DatabaseDriver')->getNullDate();
			$rlDate = $this->item->recurr_bak->recurrence_limit_date;
			if (!empty($rlDate) && (strpos($nullDate, $rlDate) !== 0)) {
				$recurr_limit_date = JemOutput::formatdate($rlDate);
			} else {
				$recurr_limit_date = Text::_('COM_JEM_UNLIMITED');
			}

			switch ($this->item->recurr_bak->recurrence_type) {
			case 1:
				$recurr_type = Text::_('COM_JEM_DAILY');
				$recurr_info = str_ireplace('[placeholder]',
				                            $this->item->recurr_bak->recurrence_number,
				                            Text::_('COM_JEM_OUTPUT_DAY'));
				break;
			case 2:
				$recurr_type = Text::_('COM_JEM_WEEKLY');
				$recurr_info = str_ireplace('[placeholder]',
				                            $this->item->recurr_bak->recurrence_number,
				                            Text::_('COM_JEM_OUTPUT_WEEK'));
				break;
			case 3:
				$recurr_type = Text::_('COM_JEM_MONTHLY');
				$recurr_info = str_ireplace('[placeholder]',
				                            $this->item->recurr_bak->recurrence_number,
				                            Text::_('COM_JEM_OUTPUT_MONTH'));
				break;
			case 4:
				$recurr_type = Text::_('COM_JEM_WEEKDAY');
				$recurr_byday = preg_replace('/(,)([^ ,]+)/', '$1 $2', $this->item->recurr_bak->recurrence_byday);
				$recurr_days = str_ireplace(array('MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SO'),
				                            array(Text::_('COM_JEM_MONDAY'), Text::_('COM_JEM_TUESDAY'),
				                                  Text::_('COM_JEM_WEDNESDAY'), Text::_('COM_JEM_THURSDAY'),
				                                  Text::_('COM_JEM_FRIDAY'), Text::_('COM_JEM_SATURDAY'),
				                                  Text::_('COM_JEM_SUNDAY')),
				                            $recurr_byday);
				$recurr_num  = str_ireplace(array('5', '6'),
				                            array(Text::_('COM_JEM_LAST'), Text::_('COM_JEM_BEFORE_LAST')),
				                            $this->item->recurr_bak->recurrence_number);
				$recurr_info = str_ireplace(array('[placeholder]', '[placeholder_weekday]'),
				                            array($recurr_num, $recurr_days),
				                            Text::_('COM_JEM_OUTPUT_WEEKDAY'));
				break;
			default:
				break;
			}

			if (!empty($recurr_type)) {
		 ?>
				<hr />
				<p><strong><?php echo Text::_('COM_JEM_RECURRING_INFO_TITLE'); ?></strong></p>
				<ul class="adminformlist">
					<li>
						<label><?php echo Text::_('COM_JEM_RECURRENCE'); ?></label>
						<input type="text" class="readonly" readonly="readonly" value="<?php echo $recurr_type; ?>">
					</li>
					<li>
						<div class="clear"></div>
						<label> </label>
						<?php echo $recurr_info; ?>
					</li>
					<li>
						<label><?php echo Text::_('COM_JEM_RECURRENCE_COUNTER'); ?></label>
						<input type="text" class="readonly" readonly="readonly" value="<?php echo $recurr_limit_date; ?>">
					</li>
				</ul>
		<?php
			}
		} ?>
	</fieldset>
	<!-- RECURRENCE END -->
	
	<!-- CONTACT START -->
				<fieldset class="adminform">
				<legend><?php echo Text::_('COM_JEM_CONTACT'); ?></legend>
				<ul class="adminformlist">
					<li><?php echo $this->form->getLabel('contactid'); ?> <?php echo $this->form->getInput('contactid'); ?></li>
				</ul>
			</fieldset>
	<!-- CONTACT END -->
	
	<!-- REGISTRATION START -->
	<fieldset class="panelform">
		<legend><?php echo Text::_('COM_JEM_EVENT_REGISTRATION_LEGEND'); ?></legend>
		<ul class="adminformlist">
		<?php if ($this->jemsettings->showfroregistra == 0) : ?>
			<li><?php echo $this->form->getLabel('registra'); ?> <?php echo Text::_('JNO'); ?></li>
		<?php else : ?>
			<?php if ($this->jemsettings->showfroregistra == 1) : ?>
			<li><?php echo $this->form->getLabel('registra'); ?> <?php echo Text::_('JYES'); ?></li>
			<?php else : ?>
			<li><?php echo $this->form->getLabel('registra'); ?> <?php echo $this->form->getInput('registra'); ?></li>
			<?php endif; ?>
			<br/>
			<?php if ($this->jemsettings->regallowinvitation == 1) : ?>
			<li><?php echo $this->form->getLabel('reginvitedonly'); ?> <?php echo $this->form->getInput('reginvitedonly'); ?></li>
			<?php endif; ?>
			<br/>
			<li><?php echo $this->form->getLabel('unregistra'); ?> <?php echo $this->form->getInput('unregistra'); ?>
				<?php echo $this->form->getInput('unregistra_until'); ?>
				<span id="jform_unregistra_until2"><?php echo Text::_('COM_JEM_EDITEVENT_FIELD_ANNULATION_UNTIL_POSTFIX'); ?></span>
			</li>
			<br/><br/>			
			<li><?php echo $this->form->getLabel('maxplaces'); ?> <?php echo $this->form->getInput('maxplaces'); ?></li>
            <br/><br/>
            <li><?php echo $this->form->getLabel('minbookeduser'); ?> <?php echo $this->form->getInput('minbookeduser'); ?></li>
            <br/><br/>
            <li><?php echo $this->form->getLabel('maxbookeduser'); ?> <?php echo $this->form->getInput('maxbookeduser'); ?></li>
            <br/><br/>
			<li><label style='margin-top: 1rem;'><?php echo Text::_ ('COM_JEM_EDITEVENT_FIELD_RESERVED_PLACES');?></label> <?php echo $this->form->getInput('reservedplaces'); ?></li>
            <br/><br/>
			<li><?php echo $this->form->getLabel('waitinglist'); ?> <?php echo $this->form->getInput('waitinglist'); ?></li>
			<br/><br/>
			<li><?php echo $this->form->getLabel('requestanswer'); ?> <?php echo $this->form->getInput('requestanswer'); ?></li>
			<br/><br/>
			<?php if ($this->jemsettings->regallowinvitation == 1) : ?>
			<li><?php echo $this->form->getLabel('invited'); ?> <?php echo $this->form->getInput('invited'); ?></li>
			<br/><br/>
			<?php endif; ?>
			<li><label style='margin-top: 1rem;'><?php echo Text::_ ('COM_JEM_EDITEVENT_FIELD_BOOKED_PLACES');?></label> <?php echo '<input id="event-booked" class="form-control readonly inputbox" type="text" readonly="true" value="' . $this->item->booked . '" />'; ?></li>
			<br/><br/>
			<?php if ($this->item->maxplaces): ?>
			<li><?php echo $this->form->getLabel('avplaces'); ?> <?php echo '<input id="event-available" class="form-control readonly inputbox" type="text" readonly="true" value="' . ($this->item->maxplaces-$this->item->booked-$this->item->reservedplaces) . '" />'; ?></li>
			<?php endif; ?>
		<?php endif; ?>
		</ul>
	</fieldset>
</div>	
	<!-- REGISTRATION END -->
