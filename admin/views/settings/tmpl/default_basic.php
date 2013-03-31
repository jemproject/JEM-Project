	<table class="noshow">
      <tr>
        <td width="50%" valign="top">
		<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_EVENTLIST_DISPLAY_SETTINGS' ); ?></legend>
				<table class="admintable" cellspacing="1">
				<tbody>
	 				<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_SHOW_DETAILS' ); ?>::<?php echo JText::_('COM_EVENTLIST_SHOW_DETAILS_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_SHOW_DETAILS' ); ?>
							</span>
						</td>
       					<td valign="top">
        					<?php
		  						$showdets = array();
								$showdets[] = JHTML::_('select.option', '0', JText::_( 'COM_EVENTLIST_DETAILS_OFF' ) );
								$showdets[] = JHTML::_('select.option', '1', JText::_( 'COM_EVENTLIST_LINK_ON_TITLE' ) );
								$showdet = JHTML::_('select.genericlist', $showdets, 'showdetails', 'size="1" class="inputbox"', 'value', 'text', $this->elsettings->showdetails );
								echo $showdet;
        					?>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_DATE_STRFTIME' ); ?>::<?php echo JText::_('COM_EVENTLIST_DATE_STRFTIME_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_DATE_STRFTIME' ); ?>
							</span>
						</td>
       					<td valign="top">
							<input type="text" name="formatdate" value="<?php echo $this->elsettings->formatdate; ?>" size="15" maxlength="15" />
							&nbsp;<a href="http://www.php.net/strftime" target="_blank"><?php echo JText::_( 'COM_EVENTLIST_PHP_STRFTIME_MANUAL' ); ?></a>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_TIME_STRFTIME' ); ?>::<?php echo JText::_('COM_EVENTLIST_TIME_STRFTIME_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_TIME_STRFTIME' ); ?>
							</span>
						</td>
       					<td valign="top">
							<input type="text" name="formattime" value="<?php echo $this->elsettings->formattime; ?>" size="15" maxlength="15" />
							&nbsp;<a href="http://www.php.net/strftime" target="_blank"><?php echo JText::_( 'COM_EVENTLIST_PHP_STRFTIME_MANUAL' ); ?></a>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_TIME_NAME' ); ?>::<?php echo JText::_('COM_EVENTLIST_TIME_NAME_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_TIME_NAME' ); ?>
							</span>
						</td>
       					<td valign="top">
							<input type="text" name="timename" value="<?php echo $this->elsettings->timename; ?>" size="15" maxlength="10" />
       	 				</td>
      				</tr>
      				<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_STORE_IP' ); ?>::<?php echo JText::_('COM_EVENTLIST_STORE_IP_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_STORE_IP' ); ?>
							</span>
						</td>
       					<td valign="top">
							<?php
								echo JHTML::_('select.booleanlist', 'storeip', 'class="inputbox"', $this->elsettings->storeip );
        					?>
       	 				</td>
      				</tr>
				</tbody>
			</table>
		  </fieldset>

		  <fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_EVENTLIST_EVENT_HANDLING' ); ?></legend>
				<table class="admintable" cellspacing="1">
				<tbody>
					<tr>
        				<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_OLD_EVENTS' ); ?>::<?php echo JText::_('COM_EVENTLIST_OLD_EVENTS_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_OLD_EVENTS' ); ?>
							</span>
						</td>
      					<td valign="top">
							<?php
							$mode = 0;
							if ($this->elsettings->oldevent >= 1) {
								$mode = 1;
							} // if
							?>
							<select name="oldevent" size="1" class="inputbox" onChange="changeoldMode()">
  								<option value="0"<?php if ($this->elsettings->oldevent == 0) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_DO_NOTHING' ); ?></option>
  								<option value="1"<?php if ($this->elsettings->oldevent == 1) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_DELETE_OLD_EVENTS' ); ?></option>
  								<option value="2"<?php if ($this->elsettings->oldevent == 2) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_ARCHIVE_OLD_EVENTS' ); ?></option>
							</select>&nbsp;
							<span class="error hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_WARNING' ); ?>::<?php echo JText::_( 'COM_EVENTLIST_OLD_EVENTS_WARN' ); ?>">
								<?php echo $this->WarningIcon(); ?>
							</span>
      					</td>
      				</tr>
	 				<tr id="old"<?php if (!$mode) echo ' style="display:none"'; ?>>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_NUMBER_DELETE_DAYS' ); ?>::<?php echo JText::_('COM_EVENTLIST_NUMBER_DELETE_DAYS_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_NUMBER_DELETE_DAYS' ); ?>
							</span>
						</td>
       					<td valign="top">
							<input type="text" name="minus" value="<?php echo $this->elsettings->minus; ?>" size="3" maxlength="2" />
       	 				</td>
      				</tr>
				</tbody>
				</table>
		  </fieldset>

		  <fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_EVENTLIST_MAIL_HANDLING' ); ?></legend>
				<table class="admintable" cellspacing="1">
				<tbody>
	  				<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_MAIL_NEW_SUBMISSION' ); ?>::<?php echo JText::_('COM_EVENTLIST_MAIL_NEW_SUBMISSION_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_MAIL_NEW_SUBMISSION' ); ?>
							</span>
						</td>
       					<td valign="top">
							<?php
							$mode = 0;
							if ($this->elsettings->mailinform >= 1) {
							$mode = 1;
							} // if
							?>
							<select name="mailinform" size="1" class="inputbox" onChange="changemailMode()">
  								<option value="0"<?php if ($this->elsettings->mailinform == 0) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_DISABLED' ); ?></option>
  								<option value="1"<?php if ($this->elsettings->mailinform == 1) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_ONLY_NEW_EVENT' ); ?></option>
  								<option value="2"<?php if ($this->elsettings->mailinform == 2) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_ONLY_NEW_VENUE' ); ?></option>
		  						<option value="3"<?php if ($this->elsettings->mailinform == 3) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_BOTH' ); ?></option>
							</select>
       	 				</td>
      				</tr>
					<tr id="mail1"<?php if (!$mode) echo ' style="display:none"'; ?>>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_MAIL_RECIPIENT' ); ?>::<?php echo JText::_('COM_EVENTLIST_MAIL_RECIPIENT_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_MAIL_RECIPIENT' ); ?>
							</span>
						</td>
       					<td valign="top">
							<input type="text" name="mailinformrec" value="<?php echo $this->elsettings->mailinformrec; ?>" size="40" maxlength="220" />
       	 				</td>
      				</tr>
      				<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_MAIL_NEW_USER_SUBMISSION' ); ?>::<?php echo JText::_('COM_EVENTLIST_MAIL_NEW_USER_SUBMISSION_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_MAIL_NEW_USER_SUBMISSION' ); ?>
							</span>
						</td>
       					<td valign="top">
							<select name="mailinformuser" size="1" class="inputbox">
  								<option value="0"<?php if ($this->elsettings->mailinformuser == 0) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_DISABLED' ); ?></option>
  								<option value="1"<?php if ($this->elsettings->mailinformuser == 1) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_ONLY_NEW_EVENT' ); ?></option>
  								<option value="2"<?php if ($this->elsettings->mailinformuser == 2) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_ONLY_NEW_VENUE' ); ?></option>
		  						<option value="3"<?php if ($this->elsettings->mailinformuser == 3) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_EVENTLIST_BOTH' ); ?></option>
							</select>
       	 				</td>
      				</tr>
				</tbody>
			</table>
		</fieldset>

		</td>
        <td width="50%" valign="top">

       	<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_EVENTLIST_IMAGE_HANDLING' ); ?></legend>
				<table class="admintable" cellspacing="1">
				<tbody>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_IMAGE_FILESIZE' ); ?>::<?php echo JText::_('COM_EVENTLIST_IMAGE_FILESIZE_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_IMAGE_FILESIZE' ); ?>
							</span>
						</td>
       					<td valign="top">
							<input type="text" name="sizelimit" value="<?php echo $this->elsettings->sizelimit; ?>" size="10" maxlength="10" />
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_IMAGE_HEIGHT' ); ?>::<?php echo JText::_('COM_EVENTLIST_IMAGE_HEIGHT_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_IMAGE_HEIGHT' ); ?>
							</span>
						</td>
       					<td valign="top">
         					<input type="text" name="imagehight" value="<?php echo $this->elsettings->imagehight; ?>" size="10" maxlength="10" />
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_IMAGE_WIDTH' ); ?>::<?php echo JText::_('COM_EVENTLIST_IMAGE_WIDTH_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_IMAGE_WIDTH' ); ?>
							</span>
						</td>
       					<td valign="top">
         					<input type="text" name="imagewidth" value="<?php echo $this->elsettings->imagewidth; ?>" size="10" maxlength="10" />
         					<span class="error hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_WARNING' );?>::<?php echo JText::_( 'WARNING MAX IMAGEWIDTH' ); ?>">
								<?php echo $this->WarningIcon(); ?>
							</span>
       	 				</td>
      				</tr>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_GD_LIBRARY' ); ?>::<?php echo JText::_('COM_EVENTLIST_GD_LIBRARY_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_GD_LIBRARY' ); ?>
							</span>
						</td>
       					<td valign="top">
         					<?php
							$mode = 0;
							if ($this->elsettings->gddisabled == 1) {
								$mode = 1;
							} // if

							//is the gd library installed on the server running EventList?
							if ($gdv = ELImage::gdVersion()) {

								//is it Version two or higher? If yes let the user the choice
   								if ($gdv >= 2) {
   								?>
       								<input type="radio" id="gddisabled0" name="gddisabled" value="0" onclick="changegdMode(0)"<?php if (!$mode) echo ' checked="checked"'; ?>/><?php echo JText::_( 'COM_EVENTLIST_NO' ); ?>
									<input type="radio" id="gddisabled1" name="gddisabled" value="1" onclick="changegdMode(1)"<?php if ($mode) echo ' checked="checked"'; ?>/><?php echo JText::_( 'COM_EVENTLIST_YES' ); ?>
       							<?php
       								$note	= JText::_( 'COM_EVENTLIST_GD_VERSION_TWO' );
       								$color	= 'green';

       							//No it is version one...disable thumbnailing
   								} else {
   								?>
   								<input type="hidden" name="gddisabled" value="0" />
   								<?php
   								$note	= JText::_( 'COM_EVENTLIST_GD_VERSION_ONE' );
   								$color	= 'red';
   								}

   							//the gd library is not available on this server...disable thumbnailing
							} else {
							?>
								<input type="hidden" name="gddisabled" value="0" />
   							<?php
   								$note	= JText::_( 'COM_EVENTLIST_NO_GD_LIBRARY' );
   								$color	= 'red';
							}
							?>
							<br />
							<strong><?php echo JText::_( 'COM_EVENTLIST_STATUS' ).':'; ?></strong>
							<font color="<?php echo $color; ?>"><?php echo $note; ?></font>
						</td>
      				</tr>
      				<tr id="gd1"<?php if (!$mode) echo ' style="display:none"'; ?>>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_IMAGE_LIGHTBOX' ); ?>::<?php echo JText::_('COM_EVENTLIST_IMAGE_LIGHTBOX_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_IMAGE_LIGHTBOX' ); ?>
							</span>
						</td>
       					<td valign="top">
         					<?php
							$html = JHTML::_('select.booleanlist', 'lightbox', 'class="inputbox"', $this->elsettings->lightbox );
							echo $html;
							?>
       	 				</td>
      				</tr>
				</tbody>
			</table>
		</fieldset>

		<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_EVENTLIST_META_HANDLING' ); ?></legend>
				<table class="admintable" cellspacing="1">
				<tbody>
					<tr>
	          			<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_META_KEYWORDS' ); ?>::<?php echo JText::_('COM_EVENTLIST_META_KEYWORDS_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_META_KEYWORDS' ); ?>
							</span>
						</td>
       					<td valign="top">
							<?php
								$meta_key = explode(", ", $this->elsettings->meta_keywords);
							?>
							<select name="meta_keywords[]" multiple="multiple" size="5" class="inputbox">
								<option value="[title]" <?php if(in_array("[title]",$meta_key)) { echo "selected=\"selected\""; } ?>>
								<?php echo JText::_( 'COM_EVENTLIST_EVENT_TITLE' ); ?></option>
								<option value="[a_name]" <?php if(in_array("[a_name]",$meta_key)) { echo "selected=\"selected\""; } ?>>
								<?php echo JText::_( 'COM_EVENTLIST_VENUE' ); ?></option>
								<!-- <option value="[locid]" <?php if(in_array("[locid]",$meta_key)) { echo "selected=\"selected\""; } ?>>
								<?php echo JText::_( 'COM_EVENTLIST_CITY
                                ' ); ?></option> -->
								<option value="[catsid]" <?php if(in_array("[catsid]",$meta_key)) { echo "selected=\"selected\""; } ?>>
								<?php echo JText::_( 'COM_EVENTLIST_CATEGORY' ); ?></option>
								<option value="[dates]" <?php if(in_array("[dates]",$meta_key)) { echo "selected=\"selected\""; } ?>>
								<?php echo JText::_( 'COM_EVENTLIST_DATE' ); ?></option>
								<option value="[times]" <?php if(in_array("[times]",$meta_key)) { echo "selected=\"selected\""; } ?>>
								<?php echo JText::_( 'COM_EVENTLIST_EVENT_TIME' ); ?></option>
								<option value="[enddates]" <?php if(in_array("[enddates]",$meta_key)) { echo "selected=\"selected\""; } ?>>
								<?php echo JText::_( 'COM_EVENTLIST_ENDDATE' ); ?></option>
								<option value="[endtimes]" <?php if(in_array("[endtimes]",$meta_key)) { echo "selected=\"selected\""; } ?>>
								<?php echo JText::_( 'COM_EVENTLIST_END_TIME' ); ?></option>
							</select>
       	 				</td>
      				</tr>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_META_DESCRIPTION' ); ?>::<?php echo JText::_('COM_EVENTLIST_META_DESCRIPTION_TIP'); ?>">
								<?php echo JText::_( 'COM_EVENTLIST_META_DESCRIPTION' ); ?>
							</span>
						</td>
						<td>
							<script type="text/javascript">
							<!--
								function insert_keyword($keyword) {
									var meta_description = $("meta_description").value;
									meta_description += " "+$keyword;
									$("meta_description").value = meta_description;
								}

								function include_description() {
									$("meta_description").value = "<?php echo JText::_( 'COM_EVENTLIST_META_DESCRIPTION_STANDARD' ); ?>";
								}
							-->
							</script>

							<input class="inputbox" type="button" onclick="insert_keyword('[title]')" value="<?php echo JText::_( 'COM_EVENTLIST_EVENT_TITLE' ); ?>" />
							<input class="inputbox" type="button" onclick="insert_keyword('[a_name]')" value="<?php echo JText::_( 'COM_EVENTLIST_VENUE' ); ?>" />
							<input class="inputbox" type="button" onclick="insert_keyword('[catsid]')" value="<?php echo JText::_( 'COM_EVENTLIST_CATEGORY' ); ?>" />
							<input class="inputbox" type="button" onclick="insert_keyword('[dates]')" value="<?php echo JText::_( 'COM_EVENTLIST_DATE' ); ?>" />
							<p>
								<input class="inputbox" type="button" onclick="insert_keyword('[times]')" value="<?php echo JText::_( 'COM_EVENTLIST_EVENT_TIME' ); ?>" />
								<input class="inputbox" type="button" onclick="insert_keyword('[enddates]')" value="<?php echo JText::_( 'COM_EVENTLIST_ENDDATE' ); ?>" />
								<input class="inputbox" type="button" onclick="insert_keyword('[endtimes]')" value="<?php echo JText::_( 'COM_EVENTLIST_END_TIME' ); ?>" />
							</p>
							<textarea name="meta_description" id="meta_description" cols="35" rows="3" class="inputbox"><?php echo $this->elsettings->meta_description; ?></textarea>
							<br/>
							<input type="button" value="<?php echo JText::_( 'COM_EVENTLIST_META_DESCRIPTION_BUTTON' ); ?>" onclick="include_description()" />
							&nbsp;
							<span class="error hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_WARNING' );?>::<?php echo JText::_( 'COM_EVENTLIST_META_DESCRIPTION_WARN' ); ?>">
								<?php echo $this->WarningIcon(); ?>
							</span>
						</td>
					</tr>
				</tbody>
			</table>
		</fieldset>

		</td>
      </tr>
	</table>