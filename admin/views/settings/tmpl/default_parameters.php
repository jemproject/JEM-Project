<table class="noshow">
	<tr>
   		<td width="50%" valign="top">
			<table class="noshow">
      			<tr>
        			<td width="50%" valign="top">
						<fieldset class="adminform">
							<legend><?php echo JText::_( 'COM_JEM_GLOBAL_PARAMETERS' ); ?></legend>
							<table class="admintable">
								<tbody>
      								<tr>
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_FILTER' ); ?>::<?php echo JText::_('COM_JEM_FILTER_DESC'); ?>">
												<?php echo JText::_( 'COM_JEM_FILTER' ); ?>
											</span>
										</td>
       									<td valign="top">
        									<?php
											echo JHTML::_('select.booleanlist', 'filter', 'class="inputbox"', $this->jemsettings->filter, 'JSHOW', 'JHIDE' );
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
											echo JHTML::_('select.booleanlist', 'display', 'class="inputbox"', $this->jemsettings->display, 'JSHOW', 'JHIDE' );
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
          									echo JHTML::_('select.booleanlist', 'icons', 'class="inputbox"', $this->jemsettings->icons, 'JSHOW', 'JHIDE' );
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
          									echo JHTML::_('select.booleanlist', 'show_print_icon', 'class="inputbox"', $this->jemsettings->show_print_icon, 'JSHOW', 'JHIDE' );
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
          									echo JHTML::_('select.booleanlist', 'show_archive_icon', 'class="inputbox"', $this->jemsettings->show_archive_icon, 'JSHOW', 'JHIDE' );
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
          									echo JHTML::_('select.booleanlist', 'show_email_icon', 'class="inputbox"', $this->jemsettings->show_email_icon, 'JSHOW', 'JHIDE' );
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
          									echo JHTML::_('select.booleanlist', 'events_ical', 'class="inputbox"', $this->jemsettings->events_ical, 'JSHOW', 'JHIDE' );
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
											<input type="text" name="ical_max_items" value="<?php echo $this->jemsettings->ical_max_items; ?>" size="3" maxlength="3" />
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
          									echo JHTML::_('select.booleanlist', 'discatheader', 'class="inputbox"', $this->jemsettings->discatheader, 'JSHOW', 'JHIDE' );
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
          									echo JHTML::_('select.booleanlist', 'displaymyevents', 'class="inputbox"', $this->jemsettings->displaymyevents, 'JSHOW', 'JHIDE' );
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
		 							<input type="text" name="recurrence_anticipation" value="<?php echo $this->jemsettings->recurrence_anticipation; ?>" size="5" maxlength="3" />
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
											//$nrevents = JHTML::_('select.genericlist', $nr, 'ical_tz', 'size="1" class="inputbox"', 'value', 'text', $this->jemsettings->ical_tz );
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
											$nrevents = JHTML::_('select.genericlist', $nr, 'weekdaystart', 'size="1" class="inputbox"', 'value', 'text', $this->jemsettings->weekdaystart );
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
        									<input type="text" name="attachments_path" value="<?php echo $this->jemsettings->attachments_path; ?>" size="40"  />
       	 								
       	 								</td>
      								</tr>
      								
      								<tr valign="top">
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SETTINGS_ATTACHEMENT_TYPES' ); ?>::<?php echo JText::_('COM_JEM_SETTINGS_ATTACHEMENT_TYPES_DESC'); ?>">
												<?php echo JText::_( 'COM_JEM_SETTINGS_ATTACHEMENT_TYPES' ); ?>
											</span>
										</td>
       									
		 									<td valign="top">
        									<input type="text" name="attachments_types" value="<?php echo $this->jemsettings->attachments_types; ?>" size="40"  />
        									
       	 	
       	 								</td>
      								</tr>
      								
      								<tr valign="top">
	          							<td width="300" class="key">
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SETTINGS_ATTACHEMENT_MAXSIZE' ); ?>::<?php echo JText::_('COM_JEM_SETTINGS_ATTACHEMENT_MAXSIZE_DESC'); ?>">
												<?php echo JText::_( 'COM_JEM_SETTINGS_ATTACHEMENT_MAXSIZE' ); ?>
											</span>
										</td>
       									
		 									<td valign="top">
        									
		  									<input type="text" name="attachments_maxsize" value="<?php echo $this->jemsettings->attachments_maxsize; ?>" size="40"  />
        									
       	 								
       	 								</td>
      								</tr>
      								
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