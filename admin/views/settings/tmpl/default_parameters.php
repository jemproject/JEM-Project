<table class="noshow">
	<tr>
   		<td width="50%" valign="top">
			<table class="noshow">
      			<tr>
        			<td width="50%" valign="top">
						<fieldset class="adminform">
							<legend><?php echo JText::_( 'COM_JEM_GLOBAL_PARAMETERS' ); ?></legend>
							<table class="admintable" cellspacing="1">
								<tbody>
      								<tr>
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_FILTER' ); ?>::<?php echo JText::_('COM_JEM_FILTER_DESC'); ?>">
												<?php echo JText::_( 'COM_JEM_FILTER' ); ?>
											</span>
										</td>
       									<td valign="top">
        									<?php
											echo JHTML::_('select.booleanlist', 'filter', 'class="inputbox"', $this->elsettings->filter, 'JSHOW', 'JHIDE' );
        									?>
       	 								</td>
      								</tr>
      								<tr>
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DISPLAY_SELECT' ); ?>::<?php echo JText::_('COM_JEM_DISPLAY_SELECT_DESC'); ?>">
												<?php echo JText::_( 'COM_JEM_DISPLAY_SELECT' ); ?>
											</span>
										</td>
       									<td valign="top">
        									<?php
											echo JHTML::_('select.booleanlist', 'display', 'class="inputbox"', $this->elsettings->display, 'JSHOW', 'JHIDE' );
        									?>
       	 								</td>
      								</tr>
      								<tr valign="top">
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SHOW_ICONS' ); ?>::<?php echo JText::_('COM_JEM_SHOW_ICONS_DESC'); ?>">
												<?php echo JText::_( 'COM_JEM_SHOW_ICONS' ); ?>
											</span>
										</td>
       									<td valign="top">
		 									<?php
          									echo JHTML::_('select.booleanlist', 'icons', 'class="inputbox"', $this->elsettings->icons, 'JSHOW', 'JHIDE' );
       										?>
       	 								</td>
      								</tr>
      								<tr valign="top">
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_PRINT_ICON' ); ?>::<?php echo JText::_('COM_JEM_PRINT_ICON_DESC'); ?>">
												<?php echo JText::_( 'COM_JEM_PRINT_ICON' ); ?>
											</span>
										</td>
       									<td valign="top">
		 									<?php
          									echo JHTML::_('select.booleanlist', 'show_print_icon', 'class="inputbox"', $this->elsettings->show_print_icon, 'JSHOW', 'JHIDE' );
       										?>
       	 								</td>
      								</tr>
      								 <tr valign="top">
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_ARCHIVE_ICON' ); ?>::<?php echo JText::_('COM_JEM_ARCHIVE_ICON_DESC'); ?>">
												<?php echo JText::_( 'COM_JEM_ARCHIVE_ICON' ); ?>
											</span>
										</td>
       									<td valign="top">
		 									<?php
          									echo JHTML::_('select.booleanlist', 'show_archive_icon', 'class="inputbox"', $this->elsettings->show_archive_icon, 'JSHOW', 'JHIDE' );
       										?>
       	 								</td>
      								</tr>
      								
      								
      								<tr valign="top">
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_EMAIL_ICON' ); ?>::<?php echo JText::_('COM_JEM_EMAIL_ICON_DESC'); ?>">
												<?php echo JText::_( 'COM_JEM_EMAIL_ICON' ); ?>
											</span>
										</td>
       									<td valign="top">
		 									<?php
          									echo JHTML::_('select.booleanlist', 'show_email_icon', 'class="inputbox"', $this->elsettings->show_email_icon, 'JSHOW', 'JHIDE' );
       										?>
       	 								</td>
      								</tr>
      								<tr valign="top">
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SETTINGS_SHOW_EVENTS_ICAL' ); ?>::<?php echo JText::_('COM_JEM_SETTINGS_SHOW_EVENTS_ICAL_DESC'); ?>">
												<?php echo JText::_( 'COM_JEM_SETTINGS_SHOW_EVENTS_ICAL' ); ?>
											</span>
										</td>
       									<td valign="top">
		 									<?php
          									echo JHTML::_('select.booleanlist', 'events_ical', 'class="inputbox"', $this->elsettings->events_ical, 'JSHOW', 'JHIDE' );
       										?>
       	 								</td>
      								</tr>
      							
      			
      								<tr>
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SETTINGS_ICAL_MAX_ITEMS' ); ?>::<?php echo JText::_('COM_JEM_SETTINGS_ICAL_MAX_ITEMS_DESC'); ?>">
												<?php echo JText::_( 'COM_JEM_SETTINGS_ICAL_MAX_ITEMS' ); ?>
											</span>
										</td>
       									<td valign="top">
											<input type="text" name="icslimit" value="<?php echo $this->elsettings->icslimit; ?>" size="3" maxlength="3" />
       	 								</td>
      								</tr>
      								
      								<tr valign="top">
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SETTINGS_SHOW_CATEGORY_IMAGES' ); ?>::<?php echo JText::_('COM_JEM_SETTINGS_SHOW_CATEGORY_IMAGES_DESC'); ?>">
												<?php echo JText::_( 'COM_JEM_SETTINGS_SHOW_CATEGORY_IMAGES' ); ?>
											</span>
										</td>
       									<td valign="top">
		 									<?php
          									echo JHTML::_('select.booleanlist', 'discatheader', 'class="inputbox"', $this->elsettings->discatheader, 'JSHOW', 'JHIDE' );
       										?>
       	 								</td>
      								</tr>
      								
      								<tr valign="top">
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SETTINGS_SHOW_MY_EVENTS' ); ?>::<?php echo JText::_('COM_JEM_SETTINGS_SHOW_MY_EVENTS_DESC'); ?>">
												<?php echo JText::_( 'COM_JEM_SETTINGS_SHOW_MY_EVENTS' ); ?>
											</span>
										</td>
       									<td valign="top">
		 									<?php
          									echo JHTML::_('select.booleanlist', 'displaymyevents', 'class="inputbox"', $this->elsettings->displaymyevents, 'JSHOW', 'JHIDE' );
       										?>
       	 								</td>
      								</tr>
      								<tr valign="top">
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SETTINGS_RECURRENCE_LIMITDAYS' ); ?>::<?php echo JText::_('COM_JEM_SETTINGS_RECURENCE_LIMITDAYS_DESC'); ?>">
												<?php echo JText::_( 'COM_JEM_SETTINGS_RECURRENCE_LIMITDAYS' ); ?>
											</span>
										</td>
       									<td valign="top">
		 							<input type="text" name="repeat_window" value="<?php echo $this->elsettings->repeat_window; ?>" size="5" maxlength="3" />
       	 								</td>
      								</tr>
      								
      								<!-- ----------------  ------------------  ------------  -->
      								
      								
      								<!--
      								<tr valign="top">
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SETTINGS_ICAL_TIMEZONE' ); ?>::<?php echo JText::_('COM_JEM_SETTINGS_ICAL_TIMEZONE_DESC'); ?>">
												<?php 
												//echo JText::_( 'COM_JEM_SETTINGS_ICAL_TIMEZONE' ); 
												?>
											</span>
										</td>
       									
		 									<td valign="top">
        									<?php
		  									//$nr = array();
											//$nr[] = JHTML::_('select.option', '0', JText::_('COM_JEM_SETTINGS_ICAL_TIMEZONE_FLOAT') );
											//$nr[] = JHTML::_('select.option', '1', JText::_('COM_JEM_SETTINGS_ICAL_TIMEZONE_JOOMLA') );
											//$nrevents = JHTML::_('select.genericlist', $nr, 'ical_tz', 'size="1" class="inputbox"', 'value', 'text', $this->elsettings->ical_tz );
											//echo $nrevents;
        									?>
       	 								
       	 								</td>
      								</tr>
      								-->
      								
      								
      								<tr valign="top">
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SETTINGS_SELECTFIRSTWEEKDAY' ); ?>::<?php echo JText::_('COM_JEM_SETTINGS_SELECTFIRSTWEEKDAY_DESC'); ?>">
												<?php echo JText::_( 'COM_JEM_SETTINGS_SELECTFIRSTWEEKDAY' ); ?>
											</span>
										</td>
       									
		 									<td valign="top">
        									<?php
		  									$nr = array();
											$nr[] = JHTML::_('select.option', '0', JText::_('COM_JEM_SETTINGS_SUNDAY') );
											$nr[] = JHTML::_('select.option', '1', JText::_('COM_JEM_SETTINGS_MONDAY') );
											$nrevents = JHTML::_('select.genericlist', $nr, 'weekdaystart', 'size="1" class="inputbox"', 'value', 'text', $this->elsettings->weekdaystart );
											echo $nrevents;
        									?>
       	 								
       	 								</td>
      								</tr>
      								
      								
      								
      								
      								
      								<tr valign="top">
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SETTINGS_ATTACHEMENT_PATH' ); ?>::<?php echo JText::_('COM_JEM_SETTINGS_ATTACHEMENT_PATH_DESC'); ?>">
												<?php echo JText::_( 'COM_JEM_SETTINGS_ATTACHEMENT_PATH' ); ?>
											</span>
										</td>
       									
		 									<td valign="top">
        									<input type="text" name="attachments_path" value="<?php echo $this->elsettings->attachments_path; ?>" size="40"  />
       	 								
       	 								</td>
      								</tr>
      								
      								<tr valign="top">
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SETTINGS_ATTACHEMENT_TYPES' ); ?>::<?php echo JText::_('COM_JEM_SETTINGS_ATTACHEMENT_TYPES_DESC'); ?>">
												<?php echo JText::_( 'COM_JEM_SETTINGS_ATTACHEMENT_TYPES' ); ?>
											</span>
										</td>
       									
		 									<td valign="top">
        									<input type="text" name="attachments_types" value="<?php echo $this->elsettings->attachments_types; ?>" size="40"  />
        									
       	 	
       	 								</td>
      								</tr>
      								
      								<tr valign="top">
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SETTINGS_ATTACHEMENT_MAXSIZE' ); ?>::<?php echo JText::_('COM_JEM_SETTINGS_ATTACHEMENT_MAXSIZE_DESC'); ?>">
												<?php echo JText::_( 'COM_JEM_SETTINGS_ATTACHEMENT_MAXSIZE' ); ?>
											</span>
										</td>
       									
		 									<td valign="top">
        									
		  									<input type="text" name="attachments_maxsize" value="<?php echo $this->elsettings->attachments_maxsize; ?>" size="40"  />
        									
       	 								
       	 								</td>
      								</tr>
      								
      								
      								
    <param name="attachments_path" type="text" size="100" default="media/com_jem/attachments" label="COM_JEM_SETTINGS_ATTACHEMENT_PATH" description="COM_JEM_SETTINGS_ATTACHEMENT_PATH_DESC" />
    <param name="attachments_maxsize" type="text" size="15" default="1000" label="COM_JEM_SETTINGS_ATTACHEMENT_MAXSIZE" description="COM_JEM_SETTINGS_ATTACHEMENT_MAXSIZE_DESC" />
    <param name="attachments_types" type="text" size="100" default="txt,csv,htm,html,xml,css,doc,xls,rtf,ppt,pdf,swf,flv,avi,wmv,mov,jpg,jpeg,gif,png,zip,tar.gz" label="COM_JEM_SETTINGS_ATTACHEMENT_TYPES" description="COM_JEM_SETTINGS_ATTACHEMENT_TYPES_DESC" />
 	</params>
      								
      								
      								
      								
      								
      								
      								
      								<!------  ------ ------- ------- ------- ------- --->
      								
      								
      								
      								
      								
      								
      							
								</tbody>
							</table>
						</fieldset>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>