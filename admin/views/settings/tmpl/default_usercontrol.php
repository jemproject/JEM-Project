	<table class="noshow">
      <tr>
        <td width="50%">

		<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_EVENTLIST_IMAGE_UPLOAD_OPTIONS' ); ?></legend>
			<table class="admintable" cellspacing="1">
				<tbody>
	  				<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_IMAGE_UPLOAD_OPTIONS' ); ?>::<?php echo JText::_('COM_EVENTLIST_IMAGE_UPLOAD_OPTIONS_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_IMAGE_UPLOAD_OPTIONS' ); ?>
							</span>
						</td>
       					<td valign="top">
							<select name="imageenabled" size="1" class="inputbox">
  								<option value="0"<?php if ($this->elsettings->imageenabled == 0) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_DISABLED' ); ?></option>
  								<option value="1"<?php if ($this->elsettings->imageenabled == 1) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_OPTIONAL' ); ?></option>
  								<option value="2"<?php if ($this->elsettings->imageenabled == 2) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_REQUIRED' ); ?></option>
							</select>
       	 				</td>
      				</tr>
			</table>
		</fieldset>

		<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_EVENTLIST_DESCRIPTION' ); ?></legend>
			<table class="admintable" cellspacing="1">
				<tbody>
	  				<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_DESCRIPTION_LIMIT' ); ?>::<?php echo JText::_('COM_EVENTLIST_DESCRIPTION_LIMIT_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_DESCRIPTION_LIMIT' ); ?>
							</span>
						</td>
       					<td valign="top">
							<input type="text" name="datdesclimit" value="<?php echo $this->elsettings->datdesclimit; ?>" size="5" maxlength="4" />
       	 				</td>
      				</tr>
				</tbody>
			</table>
		</fieldset>

		<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_EVENTLIST_AC_EVENTS' ); ?></legend>
				<table class="admintable" cellspacing="1">
				<tbody>
	  				<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_AC_EVENT_SUBMIT' ); ?>::<?php echo JText::_('COM_EVENTLIST_AC_EVENT_SUBMIT_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_AC_EVENT_SUBMIT' ); ?>
							</span>
						</td>
       					<td valign="top">
        					<?php
								echo $this->accessLists['evdel_access'];
							?>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_RECURSIV' ); ?>::<?php echo JText::_('COM_EVENTLIST_RECURSIV_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_RECURSIV' ); ?>
							</span>
						</td>
       					<td valign="top">
							<?php
							$html = JHTML::_('select.booleanlist', 'evdelrec', 'class="inputbox"', $this->elsettings->evdelrec );
							echo $html;
							?>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_AUTOPUBLISH' ); ?>::<?php echo JText::_('COM_EVENTLIST_AUTOPUBLISH_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_AUTOPUBLISH' ); ?>
							</span>
						</td>
       					<td valign="top">
        					<?php
         					echo $this->accessLists['evpub_access'];
							?>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_RECURSIV' ); ?>::<?php echo JText::_('COM_EVENTLIST_RECURSIV_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_RECURSIV' ); ?>
							</span>
						</td>
       					<td valign="top">
							<?php
							$html = JHTML::_('select.booleanlist', 'evpubrec', 'class="inputbox"', $this->elsettings->evpubrec );
							echo $html;
							?>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_AC_EVENT_EDIT' ); ?>::<?php echo JText::_('COM_EVENTLIST_AC_EVENT_EDIT_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_AC_EVENT_EDIT' ); ?>
							</span>
						</td>
       					<td valign="top">
        					<?php
								echo $this->accessLists['ev_edit'];
							?>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_RECURSIV' ); ?>::<?php echo JText::_('COM_EVENTLIST_RECURSIV_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_RECURSIV' ); ?>
							</span>
						</td>
       					<td valign="top">
							<?php
							$html = JHTML::_('select.booleanlist', 'eventeditrec', 'class="inputbox"', $this->elsettings->eventeditrec );
							echo $html;
							?>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_EDIT_EVENT_FRONTEND_USER' ); ?>::<?php echo JText::_('COM_EVENTLIST_EDIT_EVENT_FRONTEND_USER_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_EDIT_EVENT_FRONTEND_USER' ); ?>
							</span>
						</td>
       					<td valign="top">
							<?php
							$html = JHTML::_('select.booleanlist', 'eventowner', 'class="inputbox"', $this->elsettings->eventowner );
							echo $html;
							?>
       	 				</td>
      				</tr>
				</tbody>
			</table>
		</fieldset>

		</td>
        <td width="50%">

        <fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_EVENTLIST_REGISTRATION' ); ?></legend>
				<table class="admintable" cellspacing="1">
				<tbody>
	  				<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_SUBMIT_REGISTER' ); ?>::<?php echo JText::_('COM_EVENTLIST_SUBMIT_REGISTER_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_SUBMIT_REGISTER' ); ?>
							</span>
						</td>
       					<td valign="top">
							<?php
							$mode = 0;
							if ($this->elsettings->showfroregistra == 1) {
								$mode = 1;
							} // if
							if ($this->elsettings->showfroregistra == 2) {
								$mode = 2;
							} // if
							?>
							<select name="showfroregistra" size="1" class="inputbox" onChange="changeregMode()">
  								<option value="0"<?php if ($this->elsettings->showfroregistra == 0) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_NO' ); ?></option>
  								<option value="1"<?php if ($this->elsettings->showfroregistra == 1) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_YES' ); ?></option>
  								<option value="2"<?php if ($this->elsettings->showfroregistra == 2) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_OPTIONAL' ); ?></option>
							</select>
       	 				</td>
      				</tr>
					<tr id="froreg"<?php if (!$mode) echo ' style="display:none"'; ?>>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_SUBMIT_UNREGISTER' ); ?>::<?php echo JText::_('COM_EVENTLIST_SUBMIT_UNREGISTER_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_SUBMIT_UNREGISTER' ); ?>
							</span>
						</td>
       					<td valign="top">
							<?php
		    				$showfrounreg = array();
							$showfrounreg[] = JHTML::_('select.option', '0', JText::_( 'COM_EVENTLIST_NO' ) );
							$showfrounreg[] = JHTML::_('select.option', '1', JText::_( 'COM_EVENTLIST_YES' ) );
							$showfrounreg[] = JHTML::_('select.option', '2', JText::_( 'COM_EVENTLIST_OPTIONAL' ) );
							$showfrounregist = JHTML::_('select.genericlist', $showfrounreg, 'showfrounregistra', 'size="1" class="inputbox"', 'value', 'text', $this->elsettings->showfrounregistra );
							echo $showfrounregist;
        					?>
       	 				</td>
      				</tr>
				</tbody>
			</table>
		</fieldset>

		<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_EVENTLIST_AC_VENUES' ); ?></legend>
				<table class="admintable" cellspacing="1">
				<tbody>
	  					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_AC_VENUE_SUBMIT' ); ?>::<?php echo JText::_('COM_EVENTLIST_AC_VENUE_SUBMIT_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_AC_VENUE_SUBMIT' ); ?>
							</span>
						</td>
       					<td valign="top">
        					<?php
         					echo $this->accessLists['locdel_access'];
							?>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_RECURSIV' ); ?>::<?php echo JText::_('COM_EVENTLIST_RECURSIV_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_RECURSIV' ); ?>
							</span>
						</td>
       					<td valign="top">
							<?php
							$html = JHTML::_('select.booleanlist', 'locdelrec', 'class="inputbox"', $this->elsettings->locdelrec );
							echo $html;
							?>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_AUTOPUBLISH' ); ?>::<?php echo JText::_('COM_EVENTLIST_AUTOPUBLISH_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_AUTOPUBLISH' ); ?>
							</span>
						</td>
       					<td valign="top">
        					<?php
         					echo $this->accessLists['locpub_access'];
							?>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_RECURSIV' ); ?>::<?php echo JText::_('COM_EVENTLIST_RECURSIV_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_RECURSIV' ); ?>
							</span>
						</td>
       					<td valign="top">
							<?php
							$html = JHTML::_('select.booleanlist', 'locpubrec', 'class="inputbox"', $this->elsettings->locpubrec );
							echo $html;
							?>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_AC_VENUE_EDIT' ); ?>::<?php echo JText::_('COM_EVENTLIST_AC_VENUE_EDIT_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_AC_VENUE_EDIT' ); ?>
							</span>
						</td>
       					<td valign="top">
        					<?php
								echo $this->accessLists['venue_edit'];
							?>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_RECURSIV' ); ?>::<?php echo JText::_('COM_EVENTLIST_RECURSIV_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_RECURSIV' ); ?>
							</span>
						</td>
       					<td valign="top">
							<?php
							$html = JHTML::_('select.booleanlist', 'venueeditrec', 'class="inputbox"', $this->elsettings->venueeditrec );
							echo $html;
							?>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_EDIT_VENUE_FRONTEND_USER' ); ?>::<?php echo JText::_('COM_EVENTLIST_EDIT_VENUE_FRONTEND_USER_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_EDIT_VENUE_FRONTEND_USER' ); ?>
							</span>
						</td>
       					<td valign="top">
							<?php
							$html = JHTML::_('select.booleanlist', 'venueowner', 'class="inputbox"', $this->elsettings->venueowner );
							echo $html;
							?>
       	 				</td>
      				</tr>
				</tbody>
			</table>
			</fieldset>
		</td>
      </tr>
    </table>