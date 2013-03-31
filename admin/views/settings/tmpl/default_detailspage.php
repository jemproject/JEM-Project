	<table class="noshow">
      <tr>
        <td width="50%">
        
        	<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_EVENTLIST_EVENTS' ); ?></legend>
				<table class="admintable" cellspacing="1">
				<tbody>
	  				<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_DISPLAY_TIME' ); ?>::<?php echo JText::_('COM_EVENTLIST_DISPLAY_TIME_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_DISPLAY_TIME' ); ?>
							</span>
						</td>
       					<td valign="top">
							<?php
          						echo JHTML::_('select.booleanlist', 'showtimedetails', 'class="inputbox"', $this->elsettings->showtimedetails );
       						?>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_DISPLAY_EVENT_DESCRIPT' ); ?>::<?php echo JText::_('COM_EVENTLIST_DISPLAY_EVENT_DESCRIPT_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_DISPLAY_EVENT_DESCRIPT' ); ?>
							</span>
						</td>
       					<td valign="top">
		 					<?php
          						echo JHTML::_('select.booleanlist', 'showevdescription', 'class="inputbox"', $this->elsettings->showevdescription );
       						?>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_DISPLAY_EVENT_TITLE' ); ?>::<?php echo JText::_('COM_EVENTLIST_DISPLAY_EVENT_TITLE_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_DISPLAY_EVENT_TITLE' ); ?>
							</span>
						</td>
       					<td valign="top">
		 					<?php
          						echo JHTML::_('select.booleanlist', 'showdetailstitle', 'class="inputbox"', $this->elsettings->showdetailstitle );
       						?>
       	 				</td>
      				</tr>
				</tbody>
			</table>
			</fieldset>
        
			<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_EVENTLIST_VENUES' ); ?></legend>
				<table class="admintable" cellspacing="1">
				<tbody>
					<tr valign="top">
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_DISPLAY_VENUE_DESCRIPT' ); ?>::<?php echo JText::_('COM_EVENTLIST_DISPLAY_VENUE_DESCRIPT_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_DISPLAY_VENUE_DESCRIPT' ); ?>
							</span>
						</td>
       					<td valign="top">
		 					<?php
          						echo JHTML::_('select.booleanlist', 'showlocdescription', 'class="inputbox"', $this->elsettings->showlocdescription );
       						?>
       	 				</td>
      				</tr>
					<tr valign="top">
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_DISPLAY_ADDRESS' ); ?>::<?php echo JText::_('COM_EVENTLIST_DISPLAY_ADDRESS_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_DISPLAY_ADDRESS' ); ?>
							</span>
						</td>
       					<td valign="top">
		 					<?php
          						echo JHTML::_('select.booleanlist', 'showdetailsadress', 'class="inputbox"', $this->elsettings->showdetailsadress );
       						?>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_DISPLAY_LINK_TO_VENUE' ); ?>::<?php echo JText::_('COM_EVENTLIST_DISPLAY_LINK_TO_VENUE_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_DISPLAY_LINK_TO_VENUE' ); ?>
							</span>
						</td>
       					<td valign="top">
		 					<?php
          					$showlink = array();
							$showlink[] = JHTML::_('select.option', '0', JText::_( 'COM_EVENTLIST_NO_LINK' ) );
							$showlink[] = JHTML::_('select.option', '1', JText::_( 'COM_EVENTLIST_LINK_TO_URL' ) );
							$showlink[] = JHTML::_('select.option', '2', JText::_( 'COM_EVENTLIST_LINK_TO_VENUEVIEW' ) );
							$show = JHTML::_('select.genericlist', $showlink, 'showdetlinkvenue', 'size="1" class="inputbox"', 'value', 'text', $this->elsettings->showdetlinkvenue );
							echo $show;
          					?>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_DISPLAY_LINK_TO_MAP' ); ?>::<?php echo JText::_('COM_EVENTLIST_DISPLAY_LINK_TO_MAP_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_DISPLAY_LINK_TO_MAP' ); ?>
							</span>
						</td>
       					<td valign="top">
							<?php
							$mode = 0;
							if ($this->elsettings->showmapserv == 1) {
								$mode = 1;
							} elseif ($this->elsettings->showmapserv == 2) {
								$mode = 2;
							}
							?>
							<select name="showmapserv" size="1" class="inputbox" onChange="changemapMode()">
  								<option value="0"<?php if ($this->elsettings->showmapserv == 0) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_NO_MAP_SERVICE' ); ?></option>
  								<option value="1"<?php if ($this->elsettings->showmapserv == 1) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_GOOGLE_MAP_LINK' ); ?></option>
  								<option value="2"<?php if ($this->elsettings->showmapserv == 2) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_GOOGLE_MAP_DISP' ); ?></option>
							</select>
       	 				</td>
      				</tr>
					<tr id="tld"<?php if ($mode > 0)  echo ' style="display:"'; ?>>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_GOOGLE_MAP_TLD' ); ?>::<?php echo JText::_('COM_EVENTLIST_GOOGLE_MAP_TLD_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_GOOGLE_MAP_TLD' ); ?>
							</span>
						</td>
						    <td valign="top">
          					<input type="text" name="tld" value="<?php echo $this->elsettings->tld; ?>" size="3" maxlength="3" />
						</td>
					</tr>	
					<tr id="lg"<?php if ($mode > 0) echo ' style="display:"'; ?>>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_GOOGLE_MAP_LG' ); ?>::<?php echo JText::_('COM_EVENTLIST_GOOGLE_MAP_LG_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_GOOGLE_MAP_LG' ); ?>
							</span>
						</td>
						    <td valign="top">
          					<input type="text" name="lg" value="<?php echo $this->elsettings->lg; ?>" size="3" maxlength="3" />
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
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_TYPE_REG_NAME' ); ?>::<?php echo JText::_('COM_EVENTLIST_TYPE_REG_NAME_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_TYPE_REG_NAME' ); ?>
							</span>
						</td>
       					<td valign="top">
       						<?php
		   					$regname = array();
							$regname[] = JHTML::_('select.option', '0', JText::_( 'COM_EVENTLIST_USERNAME' ) );
							$regname[] = JHTML::_('select.option', '1', JText::_( 'COM_EVENTLIST_NAME' ) );
							$nametype = JHTML::_('select.genericlist', $regname, 'regname', 'size="1" class="inputbox"', 'value', 'text', $this->elsettings->regname );
							echo $nametype;
        					?>
       	 				</td>
      				</tr>
	  				<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_COM_SOL' ); ?>::<?php echo JText::_('COM_EVENTLIST_COM_SOL_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_COM_SOL' ); ?>
							</span>
						</td>
       					<td valign="top">
       		 				<?php
							$mode = 0;
							if ($this->elsettings->comunsolution == 1) {
								$mode = 1;
							} // if
							?>
       		 				<select name="comunsolution" size="1" class="inputbox" onChange="changeintegrateMode()">
  								<option value="0"<?php if ($this->elsettings->comunsolution == 0) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_DONT_USE_COM_SOL' ); ?></option>
  								<option value="1"<?php if ($this->elsettings->comunsolution == 1) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_COMBUILDER' ); ?></option>
							</select>
       	 				</td>
      				</tr>
	  				<tr id="integrate"<?php if (!$mode) echo ' style="display:none"'; ?>>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_TYPE_COM_INTEGRATION' ); ?>::<?php echo JText::_('COM_EVENTLIST_TYPE_COM_INTEGRATION_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_TYPE_COM_INTEGRATION' ); ?>
							</span>
						</td>
       					<td valign="top">
       						<?php
		   					$comoption = array();
							$comoption[] = JHTML::_('select.option', '0', JText::_( 'COM_EVENTLIST_LINK_PROFILE' ) );
							$comoption[] = JHTML::_('select.option', '1', JText::_( 'COM_EVENTLIST_LINK_AVATAR' ) );
							$comoptions = JHTML::_('select.genericlist', $comoption, 'comunoption', 'size="1" class="inputbox"', 'value', 'text', $this->elsettings->comunoption );
							echo $comoptions;
        					?>
       	 				</td>
      				</tr>
				</tbody>
			</table>
		</fieldset>
		
		<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_EVENTLIST_COMMENTS' ); ?></legend>
				<table class="admintable" cellspacing="1">
				<tbody>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_SUPPORTED_COMMENT_SOLUTIONS' ); ?>::<?php echo JText::_('COM_EVENTLIST_SUPPORTED_COMMENT_SOLUTIONS_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_SUPPORTED_COMMENT_SOLUTIONS' ); ?>
							</span>
						</td>
       					<td valign="top">
       		 				<?php
							$checkjcomment 	= file_exists(JPATH_SITE.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_jcomments'.DIRECTORY_SEPARATOR.'jcomments.php');
							$checkjomcomment= file_exists(JPATH_SITE.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'content'.DIRECTORY_SEPARATOR.'jom_comment_bot.php');
							
							if ( $checkjcomment){
								echo '<strong>'.JText::_( 'COM_EVENTLIST_JCOMMENTS' ).': </strong><font color="green">'.JText::_( 'COM_EVENTLIST_AVAILABLE' ).'</font><br />';
							} else {
								echo '<strong>'.JText::_( 'COM_EVENTLIST_JCOMMENTS' ).': </strong><font color="red">'.JText::_( 'COM_EVENTLIST_UNAVAILABLE' ).'</font><br />';
							}
							
							if ( $checkjomcomment){
								echo '<strong>'.JText::_( 'COM_EVENTLIST_JOMCOMMENT' ).': </strong><font color="green">'.JText::_( 'COM_EVENTLIST_AVAILABLE' ).'</font><br />';
							} else {
								echo '<strong>'.JText::_( 'COM_EVENTLIST_JOMCOMMENT' ).': </strong><font color="red">'.JText::_( 'COM_EVENTLIST_UNAVAILABLE' ).'</font><br />';
							}
							?>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_SELECT_3PD_COMMENT_COMP' ); ?>::<?php echo JText::_('COM_EVENTLIST_SELECT_3PD_COMMENT_COMP_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_SELECT_3PD_COMMENT_COMP' ); ?>
							</span>
						</td>
       					<td valign="top">
       						<?php
		   					$commentsystem = array();
							$commentsystem[] = JHTML::_('select.option', '0', JText::_( 'COM_EVENTLIST_NO_COMMENTS' ) );
							
							if ( $checkjcomment ){
								$commentsystem[] = JHTML::_('select.option', '1', JText::_( 'COM_EVENTLIST_JCOMMENTS' ) );
							}
							if ( $checkjomcomment ){
								$commentsystem[] = JHTML::_('select.option', '2', JText::_( 'COM_EVENTLIST_JOMCOMMENT' ) );
							}
							$commentoptions = JHTML::_('select.genericlist', $commentsystem, 'commentsystem', 'size="1" class="inputbox"', 'value', 'text', $this->elsettings->commentsystem );
							echo $commentoptions;
        					?>
       	 				</td>
      				</tr>
				</tbody>
			</table>
		</fieldset>

	</td>
  </tr>
</table>