<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
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
		'startOffset' => 0,  // 0 starts on the first tab, 1 starts the second, etc...
		'useCookie' => true, // this must not be a string. Don't use quotes.
);
?>

<script type="text/javascript">
	window.addEvent('domready', function(){
		document.formvalidator.setHandler('date',
			function (value) {
				if(value=="") {
					return true;
				} else {
					timer = new Date();
					time = timer.getTime();
					regexp = new Array();
					regexp[time] = new RegExp('[0-9]{4}-[0-1][0-9]-[0-3][0-9]','gi');
					return regexp[time].test(value);
				}
			}
		);
	});

	function submitbutton( pressbutton ) {
		if (pressbutton == 'editevent.cancelevent' || pressbutton == 'editevent.addvenue') {
			elsubmitform( pressbutton );
			return;
		}

		var form = document.getElementById('adminForm');
		var validator = document.formvalidator;
		var title = form.title.value;
		title.replace(/\s/g,'');

		if ( title.length==0 ) {
			alert("<?php echo JText::_( 'COM_JEM_ADD_TITLE', true ); ?>");
			validator.handleResponse(false,form.title);
			return false;
		//} else if ( validator.validate(form.locid) === false ) {
		//	alert("<?php // echo JText::_( 'COM_JEM_SELECT_VENUE', true ); ?>");
		//	validator.handleResponse(false,form.locid);
		//	return false;
			} else if ( form.cid.selectedIndex == -1 ) {
			alert("<?php echo JText::_( 'COM_JEM_SELECT_CATEGORY', true ); ?>");
			validator.handleResponse(false,form.cid);
			return false;
		} else if ( validator.validate(form.dates) === false ) {
			alert("<?php echo JText::_( 'COM_JEM_DATE_WRONG', true ); ?>");
			validator.handleResponse(false,form.dates);
			return false;
		} else if ( validator.validate(form.enddates) === false ) {
			alert("<?php echo JText::_( 'COM_JEM_DATE_WRONG', true ); ?>");
			validator.handleResponse(false,form.enddates);
			return false;
		} else {
		<?php
		if ($this->editoruser) {
				// JavaScript for extracting editor text
				echo $this->editor->save( 'datdescription' );
		}
		?>
		$("meta_keywords").value = $keywords;
		$("meta_description").value = $description;
			submit_unlimited();
			elsubmitform(pressbutton);

			return true;
		}
	}

	//joomla submitform needs form name
	function elsubmitform(pressbutton){
		var form = document.getElementById('adminForm');
		if (pressbutton) {
			form.task.value=pressbutton;
		}
		if (typeof form.onsubmit == "function") {
			form.onsubmit();
		}
		form.submit();
	}

	var tastendruck = false;

	function rechne(restzeichen)
	{
		maximum = <?php echo $this->jemsettings->datdesclimit; ?>

		if (restzeichen.datdescription.value.length > maximum) {
			restzeichen.datdescription.value = restzeichen.datdescription.value.substring(0, maximum)
			links = 0
		} else {
			links = maximum - restzeichen.datdescription.value.length
		}
		restzeichen.zeige.value = links
	}

	function berechne(restzeichen)
	{
		tastendruck = true
		rechne(restzeichen)
	}
</script>


<div id="jem" class="jem_editevent">
	<form enctype="multipart/form-data" id="adminForm" action="<?php echo JRoute::_('index.php') ?>" method="post" class="form-validate">
		<div class="buttons">
			<button type="submit" class="positive" onclick="return submitbutton('editevent.saveevent')">
				<?php echo JText::_('COM_JEM_SAVE'); ?>
			</button>
			<button type="reset" class="negative" onclick="submitbutton('editevent.cancelevent')">
				<?php echo JText::_('COM_JEM_CANCEL'); ?>
			</button>
		</div>

		<?php if ($this->params->def( 'show_page_title', 1 )) : ?>
		<h1 class="componentheading">
			<?php echo $this->title; ?>
		</h1>
		<?php endif; ?>

		<?php if ($this->params->get('showintrotext')) : ?>
		<div class="description no_space floattext">
			<?php echo $this->params->get('introtext'); ?>
		</div>
		<?php endif; ?>

		<p>&nbsp;</p>

		<?php echo JHtml::_('tabs.start','event-pane',$options); ?>
		<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EVENT_MAIN_TAB'), 'event' ); ?>

		<fieldset class="jem_fldst_details">
			<legend><?php echo JText::_('COM_JEM_NORMAL_INFO'); ?></legend>

			<div class="jem_title floattext">
				<label for="title">
					<?php echo JText::_( 'COM_JEM_TITLE' ).':'; ?>
			 	</label>

				<input class="inputbox required" type="text" id="title" name="title" value="<?php echo $this->row->title; ?>" size="40" maxlength="60" />
			</div>

			<div class="jem_venue floattext">
				<label for="a_id">
					<?php echo JText::_( 'COM_JEM_VENUE' ).':'; ?>
				</label>

				<input type="text" id="a_name" name="venue" value="<?php echo $this->row->venue; ?>" disabled="disabled" />

				<div>
					<a class="flyermodal button1" title="<?php echo JText::_('COM_JEM_SELECT'); ?>" href="<?php echo JRoute::_('index.php?view=editevent&layout=choosevenue&tmpl=component'); ?>" rel="{handler: 'iframe', size: {x: 650, y: 375}}">
						<?php echo JText::_('COM_JEM_SELECT')?>
					</a>

					<input class="inputbox required" type="hidden" id="a_id" name="locid" value="<?php echo $this->row->locid; ?>" />

					<?php if ( $this->delloclink == 1 && !$this->row->id ) : //show location submission link ?>
						<a class="flyermodal button1" title="<?php echo JText::_('COM_JEM_DELIVER_NEW_VENUE'); ?>" href="<?php echo JRoute::_('index.php?view=editvenue&mode=ajax&tmpl=component'); ?>" rel="{handler: 'iframe', size: {x: 800, y: 500}}">
							<?php echo JText::_('COM_JEM_DELIVER_NEW_VENUE')?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<div class="jem_category floattext">
				<label for="cid" class="cid">
					<?php echo JText::_( 'COM_JEM_CATEGORY' ).':';?>
				</label>
				<?php echo $this->categories; ?>
			</div>

			<div class="jem_start_date floattext">
				<label for="dates">
					<?php echo JText::_( 'COM_JEM_DATE' ).':'; ?>
				</label>
				<?php echo JHtml::_('calendar', $this->row->dates, 'dates', 'dates', '%Y-%m-%d', array('class' => 'inputbox required validate-date')); ?>
				<small class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DATE_HINT' ); ?>::<?php echo JText::_('COM_JEM_DATE_HINT'); ?>">
					<?php echo $this->infoimage; ?>
				</small>
			</div>

			<div class="jem_enddate floattext">
				<label for="enddates">
					<?php echo JText::_( 'COM_JEM_ENDDATE' ).':'; ?>
				</label>
				<?php echo JHtml::_('calendar', $this->row->enddates, 'enddates', 'enddates', '%Y-%m-%d', array('class' => 'inputbox validate-date')); ?>
				<small class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DATE_HINT' ); ?>::<?php echo JText::_('COM_JEM_DATE_HINT'); ?>">
					<?php echo $this->infoimage; ?>
				</small>
			</div>

			<div class="jem_date jem_start_time floattext">
				<label for="jem_start_time">
					<?php echo JText::_( 'COM_JEM_TIME' ).':'; ?>
				</label>
				<?php
				/* <input class="inputbox validate-time" id="jem_start_time" name="times" value="<?php echo substr($this->row->times, 0, 5); ?>" size="15" maxlength="8" /> */
				echo JEMHelper::buildtimeselect(23, 'starthours', substr( $this->row->times, 0, 2 )).' : ';
				echo JEMHelper::buildtimeselect(59, 'startminutes', substr( $this->row->times, 3, 2 ));
				?>
				<?php if ( $this->jemsettings->showtime == 1 ) : ?>
				<small class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_TIME_HINT' ); ?>::<?php echo JText::_('COM_JEM_TIME_HINT'); ?>">
					<?php echo $this->infoimage; ?>
				</small>
				<?php else : ?>
				<small class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_ENDTIME_HINT' ); ?>::<?php echo JText::_('COM_JEM_ENDTIME_HINT'); ?>">
					<?php echo $this->infoimage; ?>
				</small>
				<?php endif;?>
			</div>

			<div class="jem_date jem_end_time floattext">
				<label for="jem_end_time">
					<?php echo JText::_( 'COM_JEM_ENDTIME' ).':'; ?>
				</label>
				<?php
				/* <input class="inputbox validate-time" id="jem_end_time" name="endtimes" value="<?php echo substr($this->row->endtimes, 0, 5); ?>" size="15" maxlength="8" />&nbsp; */
				echo JEMHelper::buildtimeselect(23, 'endhours', substr( $this->row->endtimes, 0, 2 )).' : ';
				echo JEMHelper::buildtimeselect(59, 'endminutes', substr( $this->row->endtimes, 3, 2 ));
				?>
				<small class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_ENDTIME_HINT' ); ?>::<?php echo JText::_('COM_JEM_ENDTIME_HINT'); ?>">
					<?php echo $this->infoimage; ?>
				</small>
			</div>
		</fieldset>

		<!--  DESCRIPTION  -->
		<fieldset class="description">
			<legend><?php echo JText::_('COM_JEM_DESCRIPTION'); ?></legend>

			<?php
			//if usertyp min editor then editor else textfield
			if ($this->editoruser) :
				echo $this->editor->display('datdescription', $this->row->datdescription, '100%', '400', '70', '15', array('pagebreak', 'readmore') );
			else :
			?>
			<textarea style="width:100%;" rows="10" name="datdescription" class="inputbox" wrap="soft" onkeyup="berechne(this.form)"><?php echo $this->row->datdescription; ?></textarea><br />
			<?php echo JText::_( 'COM_JEM_NO_HTML' ); ?><br />
			<input disabled value="<?php echo $this->jemsettings->datdesclimit; ?>" size="4" name="zeige" /><?php echo JText::_( 'COM_JEM_AVAILABLE' ); ?><br />
			<a href="javascript:rechne(document.adminForm);"><?php echo JText::_( 'COM_JEM_REFRESH' ); ?></a>
			<?php endif; ?>
		</fieldset>


		<!-- TAB: SECOND -->
		<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EVENT_SECOND_TAB'), 'eventsecond' ); ?>

		<!-- CUSTOM FIELDS -->
		<fieldset>
			<legend><?php echo JText::_('COM_JEM_CUSTOM_FIELDS'); ?></legend>

			<?php
			for($cr = 1; $cr <= 10; $cr++) {
				$currentRow = $this->row->{'custom'.$cr};
			?>
				<div class="jem_custom<?php echo $cr; ?> floattext">
					<label for="custom<?php echo $cr; ?>">
						<?php echo JText::_('COM_JEM_EVENT_CUSTOM_FIELD'.$cr).':'; ?>
					</label>
					<input type="text" class="inputbox" id="custom<?php echo $cr; ?>" name="custom<?php echo $cr; ?>" value="<?php echo $this->escape($currentRow); ?>" size="65" maxlength="60" />
				</div>
			<?php
			}
			?>
		</fieldset>


		<?php if ( $this->jemsettings->showfroregistra == 2 ) : ?>
		<fieldset class="jem_fldst_registration">

			<legend><?php echo JText::_('COM_JEM_REGISTRATION'); ?></legend>

			<?php if ( $this->jemsettings->showfroregistra == 2 ) : ?>
				<div class="floattext">
					<p><strong><?php echo JText::_( 'COM_JEM_SUBMIT_REGISTER' ).':'; ?></strong></p>

					<label for="registra0"><?php echo JText::_( 'JNO' ); ?></label>
						<input type="radio" name="registra" id="registra0" value="0" <?php echo (!$this->row->registra) ? 'checked="checked"': ''; ?> />

						<br class="clear" />

					<label for="registra1"><?php echo JText::_( 'JYES' ); ?></label>
					<input type="radio" name="registra" id="registra1" value="1" <?php echo ($this->row->registra) ? 'checked="checked"': ''; ?> />
				</div>

				<div class="floattext">
					<label for="maxplaces"><?php echo JText::_( 'COM_JEM_MAX_PLACES' ); ?></label>
					<input type="text" name="maxplaces" id="maxplaces" value="<?php echo $this->row->maxplaces; ?>" />
					<small class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_MAX_PLACES' ); ?>::<?php echo JText::_('COM_JEM_MAX_PLACES_DESC'); ?>">
						<?php echo $this->infoimage; ?>
					</small>
				</div>

				<div class="floattext">
					<p><strong><?php echo JText::_( 'COM_JEM_ENABLE_WAITINGLIST' ).':'; ?></strong></p>
					<label for="waitinglist0"><?php echo JText::_( 'JNO' ); ?></label>
					<input type="radio" name="waitinglist" id="waitinglist0" value="0" <?php echo (!$this->row->waitinglist) ? 'checked="checked"': ''; ?> />
					<br class="clear" />
					<label for="waitinglist1"><?php echo JText::_( 'JYES' ); ?></label>
					<input type="radio" name="waitinglist" id="waitinglist1" value="1" <?php echo ($this->row->waitinglist) ? 'checked="checked"': ''; ?> />
					<small class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_ENABLE_WAITINGLIST' ); ?>::<?php echo JText::_('COM_JEM_ENABLE_WAITINGLIST_DESC'); ?>">
						<?php echo $this->infoimage; ?>
					</small>
				</div>
				<?php
				//register end
			endif;

			if ( $this->jemsettings->showfrounregistra == 2 ) :
			?>
			<div class="jem_unregister floattext">
				<p><strong><?php echo JText::_( 'COM_JEM_SUBMIT_UNREGISTER' ).':'; ?></strong></p>

				<label for="unregistra0"><?php echo JText::_( 'JNO' ); ?></label>
				<input type="radio" name="unregistra" id="unregistra0" value="0" <?php echo (!$this->row->unregistra) ? 'checked="checked"': ''; ?> />

				<br class="clear" />

				<label for="unregistra1"><?php echo JText::_( 'JYES' ); ?></label>
				<input type="radio" name="unregistra" id="unregistra1" value="1" <?php echo ($this->row->unregistra) ? 'checked="checked"': ''; ?> />
			</div>
			<?php
			//unregister end
			endif;
			?>
		</fieldset>

		<?php
		//registration end
		endif;
		?>

		<fieldset class="jem_fldst_recurrence">
			<legend><?php echo JText::_('COM_JEM_RECURRENCE'); ?></legend>

			<div class="recurrence_select floattext">
				<label for="recurrence_select"><?php echo JText::_( 'COM_JEM_RECURRENCE' ); ?>:</label>
				<?php echo $this->lists['recurrence_type']; ?>
			</div>

			<div class="recurrence_output floattext">
				<label id="recurrence_output">&nbsp;</label>
				<div id="counter_row" style="display:none;">
					<label for="recurrence_limit_date"><?php echo JText::_( 'COM_JEM_RECURRENCE_COUNTER' ); ?>:</label>
					<div class="jem_date>"><?php echo JHtml::_('calendar', ($this->row->recurrence_limit_date <> 0000-00-00) ? $this->row->recurrence_limit_date : JText::_( 'COM_JEM_UNLIMITED' ), "recurrence_limit_date", "recurrence_limit_date"); ?>
						<a href="#" onclick="include_unlimited('<?php echo JText::_( 'COM_JEM_UNLIMITED' ); ?>'); return false;"><img src="media/com_jem/images/unlimited.png" width="16" height="16" alt="<?php echo JText::_( 'COM_JEM_UNLIMITED' ); ?>" /></a>
					</div>
				</div>
			</div>

			<input type="hidden" name="recurrence_number" id="recurrence_number" value="<?php echo $this->row->recurrence_number;?>" />
			<input type="hidden" name="recurrence_byday" id="recurrence_byday" value="<?php echo $this->row->recurrence_byday;?>" />

			<script type="text/javascript">
			<!--
				var $select_output = new Array();
				$select_output[1] = "<?php echo JText::_( 'COM_JEM_OUTPUT_DAY' ); ?>";
				$select_output[2] = "<?php echo JText::_( 'COM_JEM_OUTPUT_WEEK' ); ?>";
				$select_output[3] = "<?php echo JText::_( 'COM_JEM_OUTPUT_MONTH' ); ?>";
				$select_output[4] = "<?php echo JText::_( 'COM_JEM_OUTPUT_WEEKDAY' ); ?>";

				var $weekday = new Array();
				$weekday[0] = new Array("MO", "<?php  echo JText::_ ( 'COM_JEM_MONDAY' ); ?>");
				$weekday[1] = new Array("TU", "<?php  echo JText::_ ( 'COM_JEM_TUESDAY' ); ?>");
				$weekday[2] = new Array("WE", "<?php  echo JText::_ ( 'COM_JEM_WEDNESDAY' ); ?>");
				$weekday[3] = new Array("TH", "<?php  echo JText::_ ( 'COM_JEM_THURSDAY' ); ?>");
				$weekday[4] = new Array("FR", "<?php  echo JText::_ ( 'COM_JEM_FRIDAY' ); ?>");
				$weekday[5] = new Array("SA", "<?php  echo JText::_ ( 'COM_JEM_SATURDAY' ); ?>");
				$weekday[6] = new Array("SU", "<?php  echo JText::_ ( 'COM_JEM_SUNDAY' ); ?>");

				var $before_last = "<?php echo JText::_( 'COM_JEM_BEFORE_LAST' ); ?>";
				var $last = "<?php echo JText::_( 'COM_JEM_LAST' ); ?>";
				start_recurrencescript("recurrence_type");
			-->
			</script>
		</fieldset>

		<?php if (( $this->jemsettings->imageenabled == 2 ) || ($this->jemsettings->imageenabled == 1)) : ?>
		<fieldset class="jem_fldst_image">
			<legend><?php echo JText::_('COM_JEM_IMAGE'); ?></legend>
			<?php
			if ($this->row->datimage) :
				echo JEMOutput::flyer( $this->row, $this->dimage, 'event' );
			else :
				echo JHtml::_('image', 'com_jem/noimage.png', JText::_('COM_JEM_NO_IMAGE'));
			endif;
			?>
			<label for="userfile"><?php echo JText::_('COM_JEM_IMAGE'); ?></label>
			<input class="inputbox <?php echo $this->jemsettings->imageenabled == 2 ? 'required' : ''; ?>" name="userfile" id="userfile" type="file" />
			<small class="editlinktip hasTip" title="<?php echo JText::_('COM_JEM_MAX_IMAGE_FILE_SIZE'); ?>::<?php echo JText::_('COM_JEM_MAX_IMAGE_FILE_SIZE').' '.$this->jemsettings->sizelimit.' kb'; ?>">
				<?php echo $this->infoimage; ?>
			</small>
			<!--<div class="jem_current_image"><?php echo JText::_('COM_JEM_CURRENT_IMAGE'); ?></div>
			<div class="jem_selected_image"><?php echo JText::_('COM_JEM_SELECTED_IMAGE'); ?></div>-->
		</fieldset>
		<?php endif; ?>

		<!--  START META FIELDSET -->
		<fieldset class="jem_fldst_meta">
			<legend><?php echo JText::_('COM_JEM_META_HANDLING'); ?></legend>
			<table style="width:100%">
			<tr>
				<td>
					<input class="inputbox" type="button" onclick="insert_keyword('[title]')" value="<?php echo JText::_ ( 'COM_JEM_TITLE' );	?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[a_name]')" value="<?php	echo JText::_ ( 'COM_JEM_VENUE' );?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[categories]')" value="<?php	echo JText::_ ( 'COM_JEM_CATEGORIES' );?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[dates]')" value="<?php echo JText::_ ( 'COM_JEM_DATE' );?>" />
						<input class="inputbox" type="button" onclick="insert_keyword('[times]')" value="<?php echo JText::_ ( 'COM_JEM_TIME' );?>" />
						<input class="inputbox" type="button" onclick="insert_keyword('[enddates]')" value="<?php echo JText::_ ( 'COM_JEM_ENDDATE' );?>" />
						<input class="inputbox" type="button" onclick="insert_keyword('[endtimes]')" value="<?php echo JText::_ ( 'COM_JEM_ENDTIME' );?>" />
					<br />
					<label for="meta_keywords">
						<?php echo JText::_ ( 'COM_JEM_META_KEYWORDS' ) . ':';?>
					</label>
					<br />
						<?php
						if (! empty ( $this->row->meta_keywords )) {
							$meta_keywords = $this->row->meta_keywords;
						} else {
							$meta_keywords = $this->jemsettings->meta_keywords;
						}
						?>
					<textarea class="inputbox" name="meta_keywords" id="meta_keywords" rows="5" cols="40" maxlength="150" onfocus="get_inputbox('meta_keywords')" onblur="change_metatags()"><?php echo $meta_keywords; ?></textarea>
				</td>
			<tr>
			<tr>
				<td>
					<label for="meta_description">
						<?php echo JText::_ ( 'COM_JEM_META_DESCRIPTION' ) . ':';?>
					</label>
					<br />
					<?php
					if (! empty ( $this->row->meta_description )) {
						$meta_description = $this->row->meta_description;
					} else {
						$meta_description = $this->jemsettings->meta_description;
					}
					?>
					<textarea class="inputbox" name="meta_description" id="meta_description" rows="5" cols="40" maxlength="200"	onfocus="get_inputbox('meta_description')" onblur="change_metatags()"><?php echo $meta_description;?></textarea>
				</td>
			</tr>
				<!-- include the metatags end-->
			</table>
			<script type="text/javascript">
			<!--
				starter("<?php
				echo JText::_ ( 'COM_JEM_META_ERROR' );
				?>");	// window.onload is already in use, call the function manualy instead
			-->
			</script>

		</fieldset>
		<!--  END META FIELDSET -->

		<?php echo $this->loadTemplate('attachments_edit'); ?>


		<?php echo JHtml::_('tabs.end'); ?>
		<p class="clear">
		<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
		<input type="hidden" name="referer" value="<?php echo @$_SERVER['HTTP_REFERER']; ?>" />
		<input type="hidden" name="created" value="<?php echo $this->row->created; ?>" />
		<input type="hidden" name="author_ip" value="<?php echo $this->row->author_ip; ?>" />
		<input type="hidden" name="created_by" value="<?php echo $this->row->created_by; ?>" />
		<input type="hidden" name="curimage" value="<?php echo $this->row->datimage; ?>" />
		<input type="hidden" name="version" value="<?php echo $this->row->version; ?>" />
		<input type="hidden" name="hits" value="<?php echo $this->row->hits; ?>" />
		<?php echo JHtml::_('form.token'); ?>
		<input type="hidden" name="task" value="" />
		</p>
	</form>

	<div class="copyright">
		<?php echo JEMOutput::footer( ); ?>
	</div>
</div>

<?php
//keep session alive while editing
JHtml::_('behavior.keepalive');
?>