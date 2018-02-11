<?php
/**
 * @version 2.2.3
 * @package JEM
 * @copyright (C) 2013-2018 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * @todo: move js to a file
 */
defined('_JEXEC') or die;

$options = array(
		'onActive' => 'function(title, description){
        description.setStyle("display", "block");
        title.addClass("open").removeClass("closed");
    }',
		'onBackground' => 'function(title, description){
        description.setStyle("display", "none");
        title.addClass("closed").removeClass("open");
    }',
		'opacityTransition' => true,
		'startOffset' => 0,  // 0 starts on the first tab, 1 starts the second, etc...
		'useCookie' => true, // this must not be a string. Don't use quotes.
);

JHtml::_('behavior.tooltip');
JHtml::_('behavior.formvalidation');
JHtml::_('behavior.keepalive');

// Create shortcut to parameters.
$params = $this->state->get('params');
$params = $params->toArray();
?>

<script type="text/javascript">
window.addEvent('domready', function(){
	checkmaxplaces();

	$("jform_attribs_event_show_mapserv").addEvent('change', testmap);

	var mapserv = $("jform_attribs_event_show_mapserv");
	var nrmapserv = mapserv.options[mapserv.selectedIndex].value;

	if (nrmapserv == 1 || nrmapserv == 2) {
		eventmapon();
	} else {
		eventmapoff();
	}

	$('jform_attribs_event_comunsolution').addEvent('change', testcomm);

	var commhandler = $("jform_attribs_event_comunsolution");
	var nrcommhandler = commhandler.options[commhandler.selectedIndex].value;

	if (nrcommhandler == 1) {
		common();
	} else {
		commoff();
	}
});

	function checkmaxplaces()
	{
		$('jform_maxplaces').addEvent('change', function(){
			if ($('event-available')) {
						var val = parseInt($('jform_maxplaces').value);
						var booked = parseInt($('event-booked').value);
						$('event-available').value = (val-booked);
			}
			});

		$('jform_maxplaces').addEvent('keyup', function(){
			if ($('event-available')) {
						var val = parseInt($('jform_maxplaces').value);
						var booked = parseInt($('event-booked').value);
						$('event-available').value = (val-booked);
			}
			});
	}

	function testcomm()
	{
		var commhandler = $("jform_attribs_event_comunsolution");
		var nrcommhandler = commhandler.options[commhandler.selectedIndex].value;

		if (nrcommhandler == 1) {
			common();
		} else {
			commoff();
		}
	}

	function testmap()
	{
		var mapserv = $("jform_attribs_event_show_mapserv");
		var nrmapserv = mapserv.options[mapserv.selectedIndex].value;

		if (nrmapserv == 1 || nrmapserv == 2) {
			eventmapon();
		} else {
			eventmapoff();
		}
	}

	function eventmapon()
	{
		document.getElementById('eventmap1').style.display = '';
		document.getElementById('eventmap2').style.display = '';
	}

	function eventmapoff()
	{
		document.getElementById('eventmap1').style.display = 'none';
		document.getElementById('eventmap2').style.display = 'none';
	}

	function common()
	{
		document.getElementById('comm1').style.display = '';
	}

	function commoff()
	{
		document.getElementById('comm1').style.display = 'none';
	}
</script>
<script type="text/javascript">
Joomla.submitbutton = function(task)
{
		if (task == 'event.cancel' || document.formvalidator.isValid(document.id('event-form'))) {
			Joomla.submitform(task, document.getElementById('event-form'));

			<?php echo $this->form->getField('articletext')->save(); ?>

			$("meta_keywords").value = $keywords;
			$("meta_description").value = $description;
		}
}
</script>
<script type="text/javascript">
window.addEvent('domready', function(){
	$("jform_unregistra").addEvent('change', showUnregistraUntil);

	showUnregistraUntil();
});

function showUnregistraUntil()
{
	var unregistra = $("jform_unregistra");
	var unregistramode = unregistra.options[unregistra.selectedIndex].value;

	if (unregistramode == 2) {
		document.getElementById('jform_unregistra_until_span').style.display = '';
	} else {
		document.getElementById('jform_unregistra_until_span').style.display = 'none';
	}
}
</script>

<form
	action="<?php echo JRoute::_('index.php?option=com_jem&layout=edit&id='.(int) $this->item->id); ?>"
	class="form-validate" method="post" name="adminForm" id="event-form" enctype="multipart/form-data">

	<?php $recurr = empty($this->item->recurr_bak) ? $this->item : $this->item->recurr_bak; ?>
	<?php if (!empty($recurr->recurrence_number) || !empty($recurr->recurrence_type)) : ?>
	<div class="description">
		<div style="float:left;">
			<?php echo JemOutput::recurrenceicon($recurr, false, false); ?>
		</div>
		<div class="floattext" style="margin-left:36px;">
			<strong><?php echo JText::_('COM_JEM_EDITEVENT_WARN_RECURRENCE_TITLE'); ?></strong>
			<br>
			<?php
				if (!empty($recurr->recurrence_type) && empty($recurr->recurrence_first_id)) {
					echo nl2br(JText::_('COM_JEM_EDITEVENT_WARN_RECURRENCE_FIRST_TEXT'));
				} else {
					echo nl2br(JText::_('COM_JEM_EDITEVENT_WARN_RECURRENCE_TEXT'));
				}
			?>
		</div>
	</div>
	<div class="clear"></div>
	<?php endif; ?>

	<!-- START OF LEFT DIV -->
	<div class="width-55 fltlft">

		<?php echo JHtml::_('tabs.start', 'det-pane'); ?>
		<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EVENT_INFO_TAB'), 'info' ); ?>

		<!-- START OF LEFT FIELDSET -->
		<fieldset class="adminform">
			<legend>
				<?php echo empty($this->item->id) ? JText::_('COM_JEM_NEW_EVENT') : JText::sprintf('COM_JEM_EVENT_DETAILS', $this->item->id); ?>
			</legend>

			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('title');?> <?php echo $this->form->getInput('title'); ?>
				</li>
				<li><?php echo $this->form->getLabel('alias'); ?> <?php echo $this->form->getInput('alias'); ?>
				</li>
				<li><?php echo $this->form->getLabel('dates'); ?> <?php echo $this->form->getInput('dates'); ?>
				</li>
				<li><?php echo $this->form->getLabel('enddates'); ?> <?php echo $this->form->getInput('enddates'); ?>
				</li>
				<li><?php echo $this->form->getLabel('times'); ?> <?php echo $this->form->getInput('times'); ?>
				</li>
				<li><?php echo $this->form->getLabel('endtimes'); ?> <?php echo $this->form->getInput('endtimes'); ?>
				</li>
				<li><?php echo $this->form->getLabel('cats'); ?> <?php echo $this->form->getInput('cats'); ?>
				</li>
			</ul>
		</fieldset>

		<fieldset class="adminform">
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('locid'); ?> <?php echo $this->form->getInput('locid'); ?>
				</li>
				<li><?php echo $this->form->getLabel('contactid'); ?> <?php echo $this->form->getInput('contactid'); ?>
				</li>
				<li><?php echo $this->form->getLabel('published'); ?> <?php echo $this->form->getInput('published'); ?>
				</li>
				<li><?php echo $this->form->getLabel('featured'); ?> <?php echo $this->form->getInput('featured'); ?>
				</li>
				<li><?php echo $this->form->getLabel('access'); ?> <?php echo $this->form->getInput('access'); ?>
				</li>
			</ul>
		</fieldset>

		<fieldset class="adminform">
			<div class="clr"></div>
			<?php echo $this->form->getLabel('articletext'); ?>
			<div class="clr"></div>
			<?php echo $this->form->getInput('articletext'); ?>
			<!-- END OF FIELDSET -->
		</fieldset>

		<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EVENT_ATTACHMENTS_TAB'), 'attachments' ); ?>
		<?php echo $this->loadTemplate('attachments'); ?>

		<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EVENT_SETTINGS_TAB'), 'event-settings' ); ?>
		<?php echo $this->loadTemplate('settings'); ?>

		<?php echo JHtml::_('tabs.end'); ?>
		<!-- END OF LEFT DIV -->
	</div>

	<!--  START RIGHT DIV -->
	<div class="width-40 fltrt">

		<!-- START OF SLIDERS -->
		<?php echo JHtml::_('sliders.start', 'event-sliders-'.$this->item->id, $options); ?>

		<!-- START OF PANEL PUBLISHING -->
		<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_FIELDSET_PUBLISHING'), 'publishing-details'); ?>

		<!-- RETRIEVING OF FIELDSET PUBLISHING -->
		<fieldset class="panelform">
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('id'); ?> <?php echo $this->form->getInput('id'); ?>
				</li>
				<li><?php echo $this->form->getLabel('created_by'); ?> <?php echo $this->form->getInput('created_by'); ?>
				</li>
				<li><?php echo $this->form->getLabel('hits'); ?> <?php echo $this->form->getInput('hits'); ?>
				</li>
				<li><?php echo $this->form->getLabel('created'); ?> <?php echo $this->form->getInput('created'); ?>
				</li>
				<li><?php echo $this->form->getLabel('modified'); ?> <?php echo $this->form->getInput('modified'); ?>
				</li>
				<li><?php echo $this->form->getLabel('version'); ?> <?php echo $this->form->getInput('version'); ?>
				</li>
			</ul>
		</fieldset>

		<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_CUSTOMFIELDS'), 'custom'); ?>
		<fieldset class="panelform">
			<ul class="adminformlist">
				<?php foreach($this->form->getFieldset('custom') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?>
				</li>
				<?php endforeach; ?>
			</ul>
		</fieldset>

		<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_REGISTRATION'), 'registra'); ?>
		<fieldset class="panelform">
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('registra'); ?> <?php echo $this->form->getInput('registra'); ?>
				</li>
				<li><?php echo $this->form->getLabel('unregistra'); ?> <?php echo $this->form->getInput('unregistra'); ?>
				<!--/li>
				<li--><span id="jform_unregistra_until_span"><?php echo $this->form->getInput('unregistra_until'); ?><?php echo JText::_('COM_JEM_EVENT_FIELD_ANNULATION_UNTIL_POSTFIX'); ?></span>
				</li>
				<li><?php echo $this->form->getLabel('maxplaces'); ?> <?php echo $this->form->getInput('maxplaces'); ?>
				</li>
				<li><label><?php echo JText::_ ('COM_JEM_BOOKED_PLACES') . ':';?></label><input id="event-booked" class="readonly inputbox" type="text" readonly="true" value="<?php echo $this->item->booked; ?>" />
				</li>
				<?php if ($this->item->maxplaces): ?>
				<li><label><?php echo JText::_ ('COM_JEM_AVAILABLE_PLACES') . ':';?></label><input id="event-available" class="readonly inputbox" type="text" readonly="true" value="<?php echo ($this->item->maxplaces-$this->item->booked); ?>" />
				</li>
				<?php endif; ?>
				<li><?php echo $this->form->getLabel('waitinglist'); ?> <?php echo $this->form->getInput('waitinglist'); ?>
				</li>
			</ul>
		</fieldset>

		<!-- START OF PANEL IMAGE -->
		<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_IMAGE'), 'image-event'); ?>

		<fieldset class="panelform">
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('datimage'); ?> <?php echo $this->form->getInput('datimage'); ?>
				</li>
			</ul>
		</fieldset>

		<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_RECURRING_EVENTS'), 'recurrence'); ?>
		<fieldset class="panelform">
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('recurrence_type'); ?> <?php echo $this->form->getInput('recurrence_type'); ?>
				</li>
				<li id="recurrence_output">
				<label></label>
				</li>
				<li id="counter_row" style="display: none;">
					<?php echo $this->form->getLabel('recurrence_limit_date'); ?> <?php echo $this->form->getInput('recurrence_limit_date'); ?>
					<br><div><small>
					<?php
					$anticipation	= $this->jemsettings->recurrence_anticipation;
					$limitdate = new JDate('now +'.$anticipation.'days');
					$limitdate = $limitdate->format('d-m-Y');
					echo JText::sprintf(JText::_('COM_JEM_EVENT_NOTICE_GENSHIELD'),$limitdate);
					?></small></div>
				</li>
			</ul>

			<input type="hidden" name="recurrence_number" id="recurrence_number" value="<?php echo $this->item->recurrence_number;?>" />
			<input type="hidden" name="recurrence_byday" id="recurrence_byday" value="<?php echo $this->item->recurrence_byday;?>" />

			<script
			type="text/javascript">
			<!--
				var $select_output = new Array();
				$select_output[1] = "<?php echo JText::_ ('COM_JEM_OUTPUT_DAY'); ?>";
				$select_output[2] = "<?php echo JText::_ ('COM_JEM_OUTPUT_WEEK'); ?>";
				$select_output[3] = "<?php echo JText::_ ('COM_JEM_OUTPUT_MONTH'); ?>";
				$select_output[4] = "<?php echo JText::_ ('COM_JEM_OUTPUT_WEEKDAY'); ?>";

				var $weekday = new Array();
				$weekday[0] = new Array("MO", "<?php echo JText::_ ('COM_JEM_MONDAY'); ?>");
				$weekday[1] = new Array("TU", "<?php echo JText::_ ('COM_JEM_TUESDAY'); ?>");
				$weekday[2] = new Array("WE", "<?php echo JText::_ ('COM_JEM_WEDNESDAY'); ?>");
				$weekday[3] = new Array("TH", "<?php echo JText::_ ('COM_JEM_THURSDAY'); ?>");
				$weekday[4] = new Array("FR", "<?php echo JText::_ ('COM_JEM_FRIDAY'); ?>");
				$weekday[5] = new Array("SA", "<?php echo JText::_ ('COM_JEM_SATURDAY'); ?>");
				$weekday[6] = new Array("SU", "<?php echo JText::_ ('COM_JEM_SUNDAY'); ?>");

				var $before_last = "<?php echo JText::_ ('COM_JEM_BEFORE_LAST'); ?>";
				var $last = "<?php echo JText::_ ('COM_JEM_LAST'); ?>";
				start_recurrencescript("jform_recurrence_type");
			-->
			</script>
		</fieldset>

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
				<fieldset class="panelform">
					<p><strong><?php echo JText::_('COM_JEM_RECURRING_INFO_TITLE'); ?></strong></p>
					<ul>
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
				</fieldset>
		<?php
			}
		} ?>

		<!-- START OF PANEL META -->
		<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_METADATA_INFORMATION'), 'meta-event'); ?>

		<!-- RETRIEVING OF FIELDSET META -->
		<fieldset class="panelform">
			<input class="inputbox" type="button" onclick="insert_keyword('[title]')" value="<?php echo JText::_ ( 'COM_JEM_EVENT_TITLE' );	?>" />
			<input class="inputbox" type="button" onclick="insert_keyword('[a_name]')" value="<?php	echo JText::_ ( 'COM_JEM_VENUE' );?>" />
			<input class="inputbox" type="button" onclick="insert_keyword('[categories]')" value="<?php	echo JText::_ ( 'COM_JEM_CATEGORIES' );?>" />
			<input class="inputbox" type="button" onclick="insert_keyword('[dates]')" value="<?php echo JText::_ ( 'COM_JEM_DATE' );?>" />

			<p>
				<input class="inputbox" type="button" onclick="insert_keyword('[times]')" value="<?php echo JText::_ ( 'COM_JEM_EVENT_TIME' );?>" />
				<input class="inputbox" type="button" onclick="insert_keyword('[enddates]')" value="<?php echo JText::_ ( 'COM_JEM_ENDDATE' );?>" />
				<input class="inputbox" type="button" onclick="insert_keyword('[endtimes]')" value="<?php echo JText::_ ( 'COM_JEM_END_TIME' );?>" />
			</p>
			<br />

			<br />
			<label for="meta_keywords"><?php echo JText::_ ('COM_JEM_META_KEYWORDS') . ':';?></label>
			<br />

			<?php
			if (! empty ( $this->item->meta_keywords )) {
				$meta_keywords = $this->item->meta_keywords;
			} else {
				$meta_keywords = $this->jemsettings->meta_keywords;
			}
			?>
			<textarea class="inputbox" name="meta_keywords" id="meta_keywords" rows="5" cols="40" maxlength="150" onfocus="get_inputbox('meta_keywords')" onblur="change_metatags()"><?php echo $meta_keywords; ?></textarea>

			<label for="meta_description"><?php echo JText::_ ('COM_JEM_META_DESCRIPTION') . ':';?></label>
			<br />

			<?php
			if (! empty ( $this->item->meta_description )) {
				$meta_description = $this->item->meta_description;
			} else {
				$meta_description = $this->jemsettings->meta_description;
			}
			?>
			<textarea class="inputbox" name="meta_description" id="meta_description" rows="5" cols="40" maxlength="200"	onfocus="get_inputbox('meta_description')" onblur="change_metatags()"><?php echo $meta_description;?></textarea>
		</fieldset>

		<fieldset class="panelform">
			<ul class="adminformlist">
			<?php foreach($this->form->getGroup('metadata') as $field): ?>
				<li>
				<?php if (!$field->hidden): ?>
					<?php echo $field->label; ?>
				<?php endif; ?>
				<?php echo $field->input; ?>
				</li>
			<?php endforeach; ?>
			</ul>
		</fieldset>

		<script type="text/javascript">
		<!--
			starter("<?php
			echo JText::_ ( 'COM_JEM_META_ERROR' );
			?>");	// window.onload is already in use, call the function manualy instead
		-->
		</script>

		<?php echo JHtml::_('sliders.end'); ?>

		<input type="hidden" name="task" value="" />
		<input type="hidden" name="author_ip" value="<?php echo $this->item->author_ip; ?>" />
		<?php echo JHtml::_('form.token'); ?>
		<!--  END RIGHT DIV -->
	</div>
	<div class="clr"></div>
</form>