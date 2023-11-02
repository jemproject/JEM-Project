<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 * @todo: move js to a file
 */
 
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

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

$wa = $this->document->getWebAssetManager();
		$wa->useScript('keepalive')
			->useScript('form.validate')
			->useScript('inlinehelp')
			->useScript('multiselect');

// Create shortcut to parameters.
$params = $this->state->get('params');
$params = $params->toArray();

?>

<script type="text/javascript">
	function checkmaxplaces()
	{
		$('#jform_maxplaces').on('change', function(){
			if ($('#event-available')) {
						var val = parseInt($('#jform_maxplaces').val());
						var booked = parseInt($('#event-booked').val());
						$('#event-available').val((val-booked));
			}
			});

		$('#jform_maxplaces').on('keyup', function(){
			if ($('#event-available')) {
						var val = parseInt($('#jform_maxplaces').val());
						var booked = parseInt($('#event-booked').val());
						$('#event-available').val((val-booked));
			}
			});
	}

	function testcomm()
	{
		var commhandler = $("#jform_attribs_event_comunsolution");
		var nrcommhandler = commhandler.options[commhandler.selectedIndex].value;

		if (nrcommhandler == 1) {
			common();
		} else {
			commoff();
		}
	}

	function testmap()
	{
		var mapserv = $("#jform_attribs_event_show_mapserv");
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
		if (task == 'event.cancel' || document.formvalidator.isValid(document.getElementById('event-form'))) {
			Joomla.submitform(task, document.getElementById('event-form'));

			<?php //echo $this->form->getField('articletext')->save(); ?>

			document.getElementById("meta_keywords").value = $keywords;
			document.getElementById("meta_description").value = $description;
		}
}
</script>
<script>
   $(document).ready(function () {
    	var $registraSelect = $("#jform_registra");
    	var $restOfList = $registraSelect.closest(".adminformlist").find("li:not(:first-child)");
    	$registraSelect.on("change", function () {
    	    var selectedValue = parseInt($(this).val());
    	    if (selectedValue === 0) {
    	        $restOfList.hide();
    	    } else {
    	        $restOfList.show();
    	    }
    	});
    	var $minBookedUserInput = $("#jform_minbookeduser");
    	var $maxBookedUserInput = $("#jform_maxbookeduser");
    	var $maxPlacesInput = $("#jform_maxplaces");
    	var $reservedPlacesInput = $("#jform_reservedplaces");
    	$minBookedUserInput
    	    .add($maxBookedUserInput)
    	    .add($maxPlacesInput)
    	    .add($reservedPlacesInput)
    	    .on("change", function () {
    	        var minBookedUserValue = parseInt($minBookedUserInput.val());
    	        var maxBookedUserValue = parseInt($maxBookedUserInput.val());
    	        var maxPlacesValue = parseInt($maxPlacesInput.val());
    	        var reservedPlacesValue = parseInt($reservedPlacesInput.val());
    	        if (minBookedUserValue > maxPlacesValue && maxPlacesValue != 0) {
    	            $minBookedUserInput.val(maxPlacesValue);
    	        }
    	        if (maxBookedUserValue > maxPlacesValue && maxPlacesValue != 0) {
    	            $maxBookedUserInput.val(maxPlacesValue);
    	        }
    	        if (minBookedUserValue > maxBookedUserValue) {
    	            $minBookedUserInput.val(maxBookedUserValue);
    	        }
    	        if (reservedPlacesValue > maxPlacesValue && maxPlacesValue != 0) {
    	            $reservedPlacesInput.val(maxPlacesValue);
    	        }
    	    });
    	// Trigger the change event on page load to initialize the state
    	$registraSelect.change();
    	$minBookedUserInput.change();
	});
</script>
<form
	action="<?php echo Route::_('index.php?option=com_jem&layout=edit&id='.(int) $this->item->id); ?>"
	class="form-validate" method="post" name="adminForm" id="event-form" enctype="multipart/form-data">

	<?php $recurr = empty($this->item->recurr_bak) ? $this->item : $this->item->recurr_bak; ?>
	<?php if (!empty($recurr->recurrence_number) || !empty($recurr->recurrence_type)) : ?>
	<div class="description">
		<div style="float:left;">
			<?php echo JemOutput::recurrenceicon($recurr, false, false); ?>
		</div>
		<div class="floattext" style="margin-left:36px;">
			<strong><?php echo Text::_('COM_JEM_EDITEVENT_WARN_RECURRENCE_TITLE'); ?></strong>
			<br>
			<?php
				if (!empty($recurr->recurrence_type) && empty($recurr->recurrence_first_id)) {
					echo nl2br(Text::_('COM_JEM_EDITEVENT_WARN_RECURRENCE_FIRST_TEXT'));
				} else {
					echo nl2br(Text::_('COM_JEM_EDITEVENT_WARN_RECURRENCE_TEXT'));
				}
			?>
		</div>
	</div>
	<div class="clear"></div>
	<?php endif; ?>

	<!-- START OF LEFT DIV -->
	<div class="row">
	<div class="col-md-7">

		<?php //echo HTMLHelper::_('tabs.start', 'det-pane'); ?>
		<?php //echo HTMLHelper::_('tabs.panel',Text::_('COM_JEM_EVENT_INFO_TAB'), 'info' ); ?>

		<?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'info', 'recall' => true, 'breakpoint' => 768]); ?>
		<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'info', Text::_('COM_JEM_EVENT_INFO_TAB')); ?>

		<!-- START OF LEFT FIELDSET -->
		<fieldset class="adminform">
			<legend>
				<?php echo empty($this->item->id) ? Text::_('COM_JEM_NEW_EVENT') : Text::sprintf('COM_JEM_EVENT_DETAILS', $this->item->id); ?>
			</legend>
			
			<ul class="adminformlist">
				<li><div class="label-form"><?php echo $this->form->renderfield('title'); ?></div></li>
				<li><div class="label-form"><?php echo $this->form->renderfield('alias'); ?></div></li>
				<li><div class="label-form"><?php echo $this->form->renderfield('dates'); ?></div></li>
				<li><div class="label-form"><?php echo $this->form->renderfield('enddates'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('times'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('endtimes'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('cats'); ?></div></li>
			</ul>
		</fieldset>

		<fieldset class="adminform">
			<ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('locid'); ?></div></li>
				<li><div class="label-form"><?php echo $this->form->renderfield('contactid'); ?></div></li>
				<li><div class="label-form"><?php echo $this->form->renderfield('published'); ?></div></li>
				<li><div class="label-form"><?php echo $this->form->renderfield('featured'); ?></div></li>
				<li><div class="label-form"><?php echo $this->form->renderfield('access'); ?></div></li>
			</ul>
		</fieldset>

		<fieldset class="adminform">
			<div class="clr"></div>
			<?php echo $this->form->getLabel('articletext'); ?>
			<div class="clr"></div>
			<?php echo $this->form->getInput('articletext'); ?>
			<!-- END OF FIELDSET -->
		</fieldset>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>
		<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'attachments', Text::_('COM_JEM_EVENT_ATTACHMENTS_TAB')); ?>
		<?php //echo HTMLHelper::_('tabs.panel',Text::_('COM_JEM_EVENT_ATTACHMENTS_TAB'), 'attachments' ); ?>
		<?php echo $this->loadTemplate('attachments'); ?>

		<?php //echo HTMLHelper::_('tabs.panel',Text::_('COM_JEM_EVENT_SETTINGS_TAB'), 'event-settings' ); ?>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>
		<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'event-settings', Text::_('COM_JEM_EVENT_SETTINGS_TAB')); ?>
		<?php echo $this->loadTemplate('settings'); ?>

		<?php //echo HTMLHelper::_('tabs.end'); ?>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>
		<!-- END OF LEFT DIV -->
	</div>

	<!--  START RIGHT DIV -->
	<div class="col-md-5">

		<!-- START OF SLIDERS -->
		<?php //echo HTMLHelper::_('sliders.start', 'event-sliders-'.$this->item->id, $options); ?>

		<!-- START OF PANEL PUBLISHING -->
		<?php //echo HTMLHelper::_('sliders.panel', Text::_('COM_JEM_FIELDSET_PUBLISHING'), 'publishing-details'); ?>

		<!-- RETRIEVING OF FIELDSET PUBLISHING -->
		<div class="accordion" id="accordionEventForm">
			<div class="accordion-item">
				<h2 class="accordion-header" id="publishing-details-header">
				<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#publishing-details" aria-expanded="true" aria-controls="publishing-details">
					<?php echo Text::_('COM_JEM_FIELDSET_PUBLISHING'); ?>
				</button>
				</h2>
				<div id="publishing-details" class="accordion-collapse collapse show" aria-labelledby="publishing-details-header" data-bs-parent="#accordionEventForm">
					<div class="accordion-body">
						<ul class="adminformlist">
							<li><div class="label-form"><?php echo $this->form->renderfield('id'); ?></div></li>
							<li><div class="label-form"><?php echo $this->form->renderfield('created_by'); ?></div></li>
							<li><div class="label-form"><?php echo $this->form->renderfield('hits'); ?></div></li>
							<li><div class="label-form"><?php echo $this->form->renderfield('created'); ?></div></li>
							<li><div class="label-form"><?php echo $this->form->renderfield('modified'); ?></div></li>
							<li><div class="label-form"><?php echo $this->form->renderfield('version'); ?></div></li>
						</ul>
					</div>
				</div>
			</div>
			<div class="accordion-item">
				<h2 class="accordion-header" id="custom-header">
				<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#custom" aria-expanded="true" aria-controls="custom">
					<?php echo Text::_('COM_JEM_CUSTOMFIELDS'); ?>
				</button>
				</h2>
				<div id="custom" class="accordion-collapse collapse" aria-labelledby="custom-header" data-bs-parent="#accordionEventForm">
					<div class="accordion-body">
						<ul class="adminformlist">
							<?php foreach($this->form->getFieldset('custom') as $field): ?>
							<li><?php echo $field->label; ?> <?php echo $field->input; ?>
							</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</div>
			<div class="accordion-item">
				<h2 class="accordion-header" id="registra-header">
				<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#registra" aria-expanded="true" aria-controls="registra">
					<?php echo Text::_('COM_JEM_REGISTRATION'); ?>
				</button>
				</h2>
				<div id="registra" class="accordion-collapse collapse" aria-labelledby="registra-header" data-bs-parent="#accordionEventForm">
					<div class="accordion-body">
						<ul class="adminformlist" style="margin-bottom: 60px;">
							<li><div class="label-form"><?php echo $this->form->renderfield('registra'); ?></div></li>
							<li><div class="label-form"><?php echo $this->form->renderfield('unregistra'); ?></div></li>
							<li><div class="label-form"><?php echo $this->form->renderfield('unregistra_until'); ?></div></li>
							<li><div class="label-form"><?php echo $this->form->renderfield('maxplaces'); ?></div></li>
                            <li><div class="label-form"><?php echo $this->form->renderfield('minbookeduser'); ?></div></li>
                            <li><div class="label-form"><?php echo $this->form->renderfield('maxbookeduser'); ?></div></li>
                            <li><div class="label-form"><?php echo $this->form->renderfield('reservedplaces'); ?></div></li>
                            <li><div class="label-form"><?php echo $this->form->renderfield('waitinglist'); ?></div></li>
                            <li><div class="label-form"><?php echo $this->form->renderfield('requestanswer'); ?></div></li>
							<li>
                                <div class="label-form"><div class="control-group">
                                        <div class="control-label">
                                            <label id="availableplaces-lbl"><?php echo Text::_ ('COM_JEM_AVAILABLE_PLACES') . ':';?></label>
                                        </div>
                                        <div class="controls">
                                            <input type="number" name="availableplaces" id="availableplaces" value=<?php echo  ($this->item->maxplaces? ($this->item->maxplaces-$this->item->booked-$this->item->reservedplaces):'0'); ?> class="form-control inputbox" size="4" aria-describedby="jform_reservedplaces-desc" readonly>
                                            <div id="availableplaces-desc" class="hide-aware-inline-help d-none">
                                                <small class="form-text">
                                                    <?php echo Text::_ ('COM_JEM_AVAILABLE_PLACES_DESC') ;?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
							</li>
						</ul>
					</div>
				</div>
			</div>
				<!-- START OF PANEL IMAGE -->
			<div class="accordion-item">
				<h2 class="accordion-header" id="image-event-header">
				<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#image-event" aria-expanded="true" aria-controls="image-event">
					<?php echo Text::_('COM_JEM_IMAGE'); ?>
				</button>
				</h2>
				
				<div id="image-event" class="accordion-collapse collapse" aria-labelledby="image-event-header" data-bs-parent="#accordionEventForm">
					<div class="accordion-body">
                        <ul class="adminformlist" style="margin-bottom: 130px;">
							<li><div class="label-form"><?php echo $this->form->renderfield('datimage'); ?></div></li>
						</ul>
					</div>
				</div>
			</div>
			<div class="accordion-item">
				<h2 class="accordion-header" id="recurrence-header">
				<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#recurrence" aria-expanded="true" aria-controls="recurrence">
					<?php echo Text::_('COM_JEM_RECURRING_EVENTS'); ?>
				</button>
				</h2>
				
				<div id="recurrence" class="accordion-collapse collapse" aria-labelledby="recurrence-header" data-bs-parent="#accordionEventForm">
					<div class="accordion-body">
						<ul class="adminformlist">
							<li><div class="label-form"><?php echo $this->form->renderfield('recurrence_type'); ?></div></li>
							<li id="recurrence_output" class="m-3">
							<label></label>
							</li>
							<li id="counter_row" style="display: none;">
                                <div class="label-form"><?php echo $this->form->renderfield('recurrence_limit_date'); ?></div>
								<br><div><small>
								<?php
								$anticipation	= $this->jemsettings->recurrence_anticipation;
								$limitdate = new JDate('now +'.$anticipation.'days');
								$limitdate = $limitdate->format('d-m-Y');
								echo Text::sprintf(Text::_('COM_JEM_EVENT_NOTICE_GENSHIELD'),$limitdate);
								?></small></div>
							</li>
						</ul>

						<input type="hidden" name="recurrence_number" id="recurrence_number" value="<?php echo $this->item->recurrence_number;?>" />
						<input type="hidden" name="recurrence_byday" id="recurrence_byday" value="<?php echo $this->item->recurrence_byday;?>" />

						<script
						type="text/javascript">
						<!--
							var $select_output = new Array();
							$select_output[1] = "<?php echo Text::_ ('COM_JEM_OUTPUT_DAY'); ?>";
							$select_output[2] = "<?php echo Text::_ ('COM_JEM_OUTPUT_WEEK'); ?>";
							$select_output[3] = "<?php echo Text::_ ('COM_JEM_OUTPUT_MONTH'); ?>";
							$select_output[4] = "<?php echo Text::_ ('COM_JEM_OUTPUT_WEEKDAY'); ?>";

							var $weekday = new Array();
							$weekday[0] = new Array("MO", "<?php echo Text::_ ('COM_JEM_MONDAY'); ?>");
							$weekday[1] = new Array("TU", "<?php echo Text::_ ('COM_JEM_TUESDAY'); ?>");
							$weekday[2] = new Array("WE", "<?php echo Text::_ ('COM_JEM_WEDNESDAY'); ?>");
							$weekday[3] = new Array("TH", "<?php echo Text::_ ('COM_JEM_THURSDAY'); ?>");
							$weekday[4] = new Array("FR", "<?php echo Text::_ ('COM_JEM_FRIDAY'); ?>");
							$weekday[5] = new Array("SA", "<?php echo Text::_ ('COM_JEM_SATURDAY'); ?>");
							$weekday[6] = new Array("SU", "<?php echo Text::_ ('COM_JEM_SUNDAY'); ?>");

							var $before_last = "<?php echo Text::_ ('COM_JEM_BEFORE_LAST'); ?>";
							var $last = "<?php echo Text::_ ('COM_JEM_LAST'); ?>";
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
									<fieldset class="panelform">
									<p><strong><?php echo Text::_('COM_JEM_RECURRING_INFO_TITLE'); ?></strong></p>
									<ul class="adminformlist">
									<li class="has-success"><label><?php echo Text::_('COM_JEM_RECURRENCE'); ?></label>
										<input type="text" value="<?php echo $recurr_type; ?>, <?php echo $recurr_info; ?>" class="form-control readonly inputbox valid form-control-success" readonly="">
									</li>
									<li><label><?php echo Text::_('COM_JEM_RECURRENCE_COUNTER'); ?></label>
										<input type="text" value="<?php echo $recurr_limit_date; ?>" class="form-control readonly inputbox valid form-control-success" readonly="">
									</li>
										</ul>
									</fieldset>
									<?php
								}
							}
						?>
					</div>
				</div>
			</div>
			<!-- START OF PANEL META -->
			<div class="accordion-item">
				<h2 class="accordion-header" id="meta-event-header">
				<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#meta-event" aria-expanded="true" aria-controls="meta-event">
					<?php echo Text::_('COM_JEM_METADATA_INFORMATION'); ?>
				</button>
				</h2>
				
				<div id="meta-event" class="accordion-collapse collapse" aria-labelledby="meta-event-header" data-bs-parent="#accordionEventForm">
					<div class="accordion-body">
						<fieldset class="panelform">
							<input class="inputbox" type="button" onclick="insert_keyword('[title]')" value="<?php echo Text::_ ( 'COM_JEM_EVENT_TITLE' );	?>" />
							<input class="inputbox" type="button" onclick="insert_keyword('[a_name]')" value="<?php	echo Text::_ ( 'COM_JEM_VENUE' );?>" />
							<input class="inputbox" type="button" onclick="insert_keyword('[categories]')" value="<?php	echo Text::_ ( 'COM_JEM_CATEGORIES' );?>" />
							<input class="inputbox" type="button" onclick="insert_keyword('[dates]')" value="<?php echo Text::_ ( 'COM_JEM_STARTDATE' );?>" />

							<p>
								<input class="inputbox" type="button" onclick="insert_keyword('[times]')" value="<?php echo Text::_ ( 'COM_JEM_STARTTIME' );?>" />
								<input class="inputbox" type="button" onclick="insert_keyword('[enddates]')" value="<?php echo Text::_ ( 'COM_JEM_ENDDATE' );?>" />
								<input class="inputbox" type="button" onclick="insert_keyword('[endtimes]')" value="<?php echo Text::_ ( 'COM_JEM_ENDTIME' );?>" />
							</p>
							<br />

							<br />
							<label for="meta_keywords"><?php echo Text::_ ('COM_JEM_META_KEYWORDS') . ':';?></label>
							<br />

							<?php
							if (! empty ( $this->item->meta_keywords )) {
								$meta_keywords = $this->item->meta_keywords;
							} else {
								$meta_keywords = $this->jemsettings->meta_keywords;
							}
							?>
							<textarea class="inputbox form-control" name="meta_keywords" id="meta_keywords" rows="6" cols="40" maxlength="150" onfocus="get_inputbox('meta_keywords')" onblur="change_metatags()"><?php echo $meta_keywords; ?></textarea>

							<label for="meta_description"><?php echo Text::_ ('COM_JEM_META_DESCRIPTION') . ':';?></label>
							<br />

							<?php
							if (! empty ( $this->item->meta_description )) {
								$meta_description = $this->item->meta_description;
							} else {
								$meta_description = $this->jemsettings->meta_description;
							}
							?>
							<textarea class="inputbox form-control" name="meta_description" id="meta_description" rows="6" cols="40" maxlength="200"	onfocus="get_inputbox('meta_description')" onblur="change_metatags()"><?php echo $meta_description;?></textarea>
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
							echo Text::_ ( 'COM_JEM_META_ERROR' );
							?>");	// window.onload is already in use, call the function manualy instead
						-->
						</script>
					</div>
				</div>
			</div>
		</div>

		<input type="hidden" name="task" value="" />
		<input type="hidden" name="author_ip" value="<?php echo $this->item->author_ip; ?>" />
		<?php echo HTMLHelper::_('form.token'); ?>
		<!--  END RIGHT DIV -->
	</div>
	<div class="clr"></div>
</form>
