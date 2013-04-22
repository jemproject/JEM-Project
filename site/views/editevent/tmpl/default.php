<?php
/**
 * @version 1.1 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

// no direct access
defined('_JEXEC') or die;
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


			if (pressbutton == 'cancelevent' || pressbutton == 'addvenue') {
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
  			} else if ( validator.validate(form.locid) === false ) {
    			alert("<?php echo JText::_( 'COM_JEM_SELECT_VENUE', true ); ?>");
    			validator.handleResponse(false,form.locid);
    			return false;
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


		var tastendruck = false
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

    <form enctype="multipart/form-data" id="adminForm" action="<?php echo JRoute::_('index.php') ?>" method="post" class="form-validate">
        <div class="jem_save_buttons floattext">
            <button type="submit" class="positive" onclick="return submitbutton('saveevent')">
        	    <?php echo JText::_('COM_JEM_SAVE'); ?>
        	</button>
        	<button type="reset" class="negative" onclick="submitbutton('cancelevent')">
        	    <?php echo JText::_('COM_JEM_CANCEL'); ?>
        	</button>
        </div>

        <p class="clear"></p>
        
    	<fieldset class="jem_fldst_details">
    	
        	<legend><?php echo JText::_('COM_JEM_NORMAL_INFO'); ?></legend>

          <div class="jem_title floattext">
              <label for="title">
                  <?php echo JText::_( 'COM_JEM_TITLE' ).':'; ?>
              </label>

              <input class="inputbox required" type="text" id="title" name="title" value="<?php echo $this->row->title; ?>" size="65" maxlength="60" />
          </div>

          <div class="jem_venue floattext">
              <label for="a_id">
                  <?php echo JText::_( 'COM_JEM_VENUE' ).':'; ?>
              </label>
			
              <input type="text" id="a_name" name="venue" value="<?php echo $this->row->venue; ?>" disabled="disabled" />
			
              <div class='jem_buttons floattext'>
              	
                  <a class="jem_venue_reset" title="<?php echo JText::_('COM_JEM_NO_VENUE'); ?>" onclick="elSelectVenue(0,'<?php echo JText::_('COM_JEM_NO_VENUE'); ?>');return false;" href="#">
                      <span><?php  echo JText::_('COM_JEM_NO_VENUE'); ?></span>
                  </a>
                  <a class="jem_venue_select modal" title="<?php echo JText::_('COM_JEM_SELECT'); ?>" href="<?php echo JRoute::_('index.php?view=editevent&layout=choosevenue&tmpl=component'); ?>" rel="{handler: 'iframe', size: {x: 650, y: 375}}">
                      <span><?php echo JText::_('COM_JEM_SELECT')?></span>
                  </a>
                  
                  <input class="inputbox required" type="hidden" id="a_id" name="locid" value="<?php echo $this->row->locid; ?>" />
             
                <?php if ( $this->delloclink == 1 && !$this->row->id ) : //show location submission link ?>
                  <a class="jem_venue_add modal" title="<?php echo JText::_('COM_JEM_DELIVER_NEW_VENUE'); ?>" href="<?php echo JRoute::_('index.php?view=editvenue&mode=ajax&tmpl=component'); ?>" rel="{handler: 'iframe', size: {x: 800, y: 500}}">
                      <span><?php echo JText::_('COM_JEM_DELIVER_NEW_VENUE')?></span>
                  </a>
                <?php endif; ?>
              </div>
          </div>

          <div class="jem_category floattext">
          		<label for="cid" class="cid">
                  <?php echo JText::_( 'COM_JEM_CATEGORY' ).':';?>
              </label>
          		<?php
          		echo $this->categories;
          		?>
          </div>

          <div class="jem_start_date floattext">
              <label for="dates">
                  <?php echo JText::_( 'COM_JEM_DATE' ).':'; ?>
              </label>
              <?php echo JHTML::_('calendar', $this->row->dates, 'dates', 'dates', '%Y-%m-%d', array('class' => 'inputbox required validate-date')); ?>
              <small class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_NOTES' ); ?>::<?php echo JText::_('COM_JEM_DATE_HINT'); ?>">
      		    <?php echo $this->infoimage; ?>
          		</small>
      		</div>

      		<div class="jem_enddate floattext">
              <label for="enddates">
                  <?php echo JText::_( 'COM_JEM_ENDDATE' ).':'; ?>
              </label>
              <?php echo JHTML::_('calendar', $this->row->enddates, 'enddates', 'enddates', '%Y-%m-%d', array('class' => 'inputbox validate-date')); ?>
        			<small class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_NOTES' ); ?>::<?php echo JText::_('COM_JEM_DATE_HINT'); ?>">
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
        			<small class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_NOTES' ); ?>::<?php echo JText::_('COM_JEM_TIME_HINT'); ?>">
        			    <?php echo $this->infoimage; ?>
        			</small>
        			<?php else : ?>
        			<small class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_NOTES' ); ?>::<?php echo JText::_('COM_JEM_ENDTIME_HINT'); ?>">
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
        			<small class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_NOTES' ); ?>::<?php echo JText::_('COM_JEM_ENDTIME_HINT'); ?>">
        			    <?php echo $this->infoimage; ?>
        			</small>
      		</div>

        </fieldset>


    	<?php if ( $this->jemsettings->showfroregistra == 2 ) : ?>
    	<fieldset class="jem_fldst_registration">

          <legend><?php echo JText::_('COM_JEM_REGISTRATION'); ?></legend>

          <?php if ( $this->jemsettings->showfroregistra == 2 ) : ?>
          <div class="floattext">
              <p><strong><?php echo JText::_( 'COM_JEM_SUBMIT_REGISTER' ).':'; ?></strong></p>

              <label for="registra0"><?php echo JText::_( 'COM_JEM_NO' ); ?></label>
        			<input type="radio" name="registra" id="registra0" value="0" <?php echo (!$this->row->registra) ? 'checked="checked"': ''; ?> />

        			<br class="clear" />

              <label for="registra1"><?php echo JText::_( 'COM_JEM_YES' ); ?></label>
            	<input type="radio" name="registra" id="registra1" value="1" <?php echo ($this->row->registra) ? 'checked="checked"': ''; ?> />
          </div>
          
      		<div class="floattext">
              <label for="maxplaces"><?php echo JText::_( 'COM_JEM_MAX_PLACES' ); ?></label>
        			<input type="text" name="maxplaces" id="maxplaces" value="<?php echo $this->row->maxplaces; ?>" />
        			<small class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_MAX_PLACES' ); ?>::<?php echo JText::_('COM_JEM_MAX_PLACES_TIP'); ?>">
        			    <?php echo $this->infoimage; ?>
        			</small>
      		</div>
      		
          <div class="floattext">
        			<p><strong><?php echo JText::_( 'COM_JEM_ENABLE_WAITINGLIST' ).':'; ?></strong></p>
            	<label for="waitinglist0"><?php echo JText::_( 'COM_JEM_NO' ); ?></label>
        			<input type="radio" name="waitinglist" id="waitinglist0" value="0" <?php echo (!$this->row->waitinglist) ? 'checked="checked"': ''; ?> />
        			<br class="clear" />
            	<label for="waitinglist1"><?php echo JText::_( 'COM_JEM_YES' ); ?></label>
            	<input type="radio" name="waitinglist" id="waitinglist1" value="1" <?php echo ($this->row->waitinglist) ? 'checked="checked"': ''; ?> />
        			<small class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_ENABLE_WAITINGLIST' ); ?>::<?php echo JText::_('COM_JEM_ENABLE_WAITINGLIST_TIP'); ?>">
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

            	<label for="unregistra0"><?php echo JText::_( 'COM_JEM_NO' ); ?></label>
        			<input type="radio" name="unregistra" id="unregistra0" value="0" <?php echo (!$this->row->unregistra) ? 'checked="checked"': ''; ?> />

        			<br class="clear" />

            	<label for="unregistra1"><?php echo JText::_( 'COM_JEM_YES' ); ?></label>
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
                  <div class="jem_date>"><?php echo JHTML::_('calendar', ($this->row->recurrence_limit_date <> 0000-00-00) ? $this->row->recurrence_limit_date : JText::_( 'COM_JEM_UNLIMITED' ), "recurrence_limit_date", "recurrence_limit_date"); ?>
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
                start_recurrencescript();
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
      		    echo JHTML::_('image', 'media/com_jem/images/noimage.png', JText::_('COM_JEM_NO_IMAGE'), array('class' => 'modal'));
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
    	<?php endif; ?>


    	<fieldset class="description">
      		<legend><?php echo JText::_('COM_JEM_DESCRIPTION'); ?></legend>

      		<?php
      		//if usertyp min editor then editor else textfield
      		if ($this->editoruser) :
      			echo $this->editor->display('datdescription', $this->row->datdescription, '100%', '400', '70', '15', array('pagebreak', 'readmore') );
      		else :
      		?>
      		<textarea style="width:100%;" rows="10" name="datdescription" class="inputbox" wrap="virtual" onkeyup="berechne(this.form)"><?php echo $this->row->datdescription; ?></textarea><br />
      		<?php echo JText::_( 'COM_JEM_NO_HTML' ); ?><br />
      		<input disabled value="<?php echo $this->jemsettings->datdesclimit; ?>" size="4" name="zeige" /><?php echo JText::_( 'COM_JEM_AVAILABLE' ); ?><br />
      		<a href="javascript:rechne(document.adminForm);"><?php echo JText::_( 'COM_JEM_REFRESH' ); ?></a>
      		<?php endif; ?>
    	</fieldset>
    	
    	<?php echo $this->loadTemplate('attachments'); ?>
    	
<!--  removed to avoid double posts in ie7
      <div class="jem_save_buttons floattext">
          <button type="submit" class="submit" onclick="return submitbutton('saveevent')">
        	    <?php echo JText::_('COM_JEM_SAVE'); ?>
        	</button>
        	<button type="reset" class="button cancel" onclick="submitbutton('cancelevent')">
        	    <?php echo JText::_('COM_JEM_CANCEL'); ?>
        	</button>
      </div>
-->    
		<p class="clear">
    	<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
    	<input type="hidden" name="referer" value="<?php echo @$_SERVER['HTTP_REFERER']; ?>" />
    	<input type="hidden" name="created" value="<?php echo $this->row->created; ?>" />
    	<input type="hidden" name="author_ip" value="<?php echo $this->row->author_ip; ?>" />
    	<input type="hidden" name="created_by" value="<?php echo $this->row->created_by; ?>" />
    	<input type="hidden" name="curimage" value="<?php echo $this->row->datimage; ?>" />
    	<input type="hidden" name="version" value="<?php echo $this->row->version; ?>" />
		<input type="hidden" name="hits" value="<?php echo $this->row->hits; ?>" />
    	<?php echo JHTML::_( 'form.token' ); ?>
    	<input type="hidden" name="task" value="" />
    	</p>
    </form>

    <p class="copyright">
    	<?php echo JEMOutput::footer( ); ?>
    </p>

</div>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>