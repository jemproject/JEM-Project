<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>
<table class="noshow">
	<tr>
		<td width="50%">
		
			<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_EVENTS' ); ?></legend>
				<table class="admintable">
				<tbody>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DISPLAY_TIME' ); ?>::<?php echo JText::_('COM_JEM_DISPLAY_TIME_DESC'); ?>">
								<?php echo JText::_( 'COM_JEM_DISPLAY_TIME' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
								echo JHTML::_('select.booleanlist', 'showtimedetails', 'class="inputbox"', $this->jemsettings->showtimedetails );
							?>
						</td>
					</tr>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DISPLAY_EVENT_DESCRIPT' ); ?>::<?php echo JText::_('COM_JEM_DISPLAY_EVENT_DESCRIPT_DESC'); ?>">
								<?php echo JText::_( 'COM_JEM_DISPLAY_EVENT_DESCRIPT' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
								echo JHTML::_('select.booleanlist', 'showevdescription', 'class="inputbox"', $this->jemsettings->showevdescription );
							?>
						</td>
					</tr>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DISPLAY_EVENT_TITLE' ); ?>::<?php echo JText::_('COM_JEM_DISPLAY_EVENT_TITLE_DESC'); ?>">
								<?php echo JText::_( 'COM_JEM_DISPLAY_EVENT_TITLE' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
								echo JHTML::_('select.booleanlist', 'showdetailstitle', 'class="inputbox"', $this->jemsettings->showdetailstitle );
							?>
						</td>
					</tr>
				</tbody>
			</table>
			</fieldset>
		
			<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_VENUES' ); ?></legend>
				<table class="admintable">
				<tbody>
					<tr valign="top">
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DISPLAY_VENUE_DESCRIPT' ); ?>::<?php echo JText::_('COM_JEM_DISPLAY_VENUE_DESCRIPT_DESC'); ?>">
								<?php echo JText::_( 'COM_JEM_DISPLAY_VENUE_DESCRIPT' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
								echo JHTML::_('select.booleanlist', 'showlocdescription', 'class="inputbox"', $this->jemsettings->showlocdescription );
							?>
						</td>
					</tr>
					<tr valign="top">
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DISPLAY_ADDRESS' ); ?>::<?php echo JText::_('COM_JEM_DISPLAY_ADDRESS_DESC'); ?>">
								<?php echo JText::_( 'COM_JEM_DISPLAY_ADDRESS' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
								echo JHTML::_('select.booleanlist', 'showdetailsadress', 'class="inputbox"', $this->jemsettings->showdetailsadress );
							?>
						</td>
					</tr>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DISPLAY_LINK_TO_VENUE' ); ?>::<?php echo JText::_('COM_JEM_DISPLAY_LINK_TO_VENUE_DESC'); ?>">
								<?php echo JText::_( 'COM_JEM_DISPLAY_LINK_TO_VENUE' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$showlink = array();
							$showlink[] = JHTML::_('select.option', '0', JText::_( 'COM_JEM_NO_LINK' ) );
							$showlink[] = JHTML::_('select.option', '1', JText::_( 'COM_JEM_LINK_TO_URL' ) );
							$showlink[] = JHTML::_('select.option', '2', JText::_( 'COM_JEM_LINK_TO_VENUEVIEW' ) );
							$show = JHTML::_('select.genericlist', $showlink, 'showdetlinkvenue', 'size="1" class="inputbox"', 'value', 'text', $this->jemsettings->showdetlinkvenue );
							echo $show;
							?>
						</td>
					</tr>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DISPLAY_LINK_TO_MAP' ); ?>::<?php echo JText::_('COM_JEM_DISPLAY_LINK_TO_MAP_DESC'); ?>">
								<?php echo JText::_( 'COM_JEM_DISPLAY_LINK_TO_MAP' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$mode = 0;
							if ($this->jemsettings->showmapserv == 1) {
								$mode = 1;
							} elseif ($this->jemsettings->showmapserv == 2) {
								$mode = 2;
							}
							?>
							<select name="showmapserv" size="1" class="inputbox" onChange="changemapMode()">
								<option value="0"<?php if ($this->jemsettings->showmapserv == 0) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_JEM_NO_MAP_SERVICE' ); ?></option>
								<option value="1"<?php if ($this->jemsettings->showmapserv == 1) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_JEM_GOOGLE_MAP_LINK' ); ?></option>
								<option value="2"<?php if ($this->jemsettings->showmapserv == 2) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_JEM_GOOGLE_MAP_DISP' ); ?></option>
							</select>
						</td>
					</tr>
					<tr id="tld"<?php if ($mode == 2|| $mode == 1)  echo ' style="display:"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_GOOGLE_MAP_TLD' ); ?>::<?php echo JText::_('COM_JEM_GOOGLE_MAP_TLD_DESC'); ?>">
								<?php echo JText::_( 'COM_JEM_GOOGLE_MAP_TLD' ); ?>
							</span>
						</td>
							<td valign="top">
							<input type="text" name="tld" value="<?php echo $this->jemsettings->tld; ?>" size="3" maxlength="3" />
						</td>
					</tr>	
					<tr id="lg"<?php if ($mode == 2 || $mode == 1) echo ' style="display:"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_GOOGLE_MAP_LG' ); ?>::<?php echo JText::_('COM_JEM_GOOGLE_MAP_LG_DESC'); ?>">
								<?php echo JText::_( 'COM_JEM_GOOGLE_MAP_LG' ); ?>
							</span>
						</td>
							<td valign="top">
							<input type="text" name="lg" value="<?php echo $this->jemsettings->lg; ?>" size="3" maxlength="3" />
						</td>
					</tr>	
				</tbody>
			</table>
		</fieldset>

		</td>
		<td width="50%">

			<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_REGISTRATION' ); ?></legend>
				<table class="admintable">
				<tbody>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_TYPE_REG_NAME' ); ?>::<?php echo JText::_('COM_JEM_TYPE_REG_NAME_DESC'); ?>">
								<?php echo JText::_( 'COM_JEM_TYPE_REG_NAME' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$regname = array();
							$regname[] = JHTML::_('select.option', '0', JText::_( 'COM_JEM_USERNAME' ) );
							$regname[] = JHTML::_('select.option', '1', JText::_( 'COM_JEM_NAME' ) );
							$nametype = JHTML::_('select.genericlist', $regname, 'regname', 'size="1" class="inputbox"', 'value', 'text', $this->jemsettings->regname );
							echo $nametype;
							?>
						</td>
					</tr>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_COM_SOL' ); ?>::<?php echo JText::_('COM_JEM_COM_SOL_DESC'); ?>">
								<?php echo JText::_( 'COM_JEM_COM_SOL' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$mode = 0;
							if ($this->jemsettings->comunsolution == 1) {
								$mode = 1;
							} // if
							?>
							<select name="comunsolution" size="1" class="inputbox" onChange="changeintegrateMode()">
								<option value="0"<?php if ($this->jemsettings->comunsolution == 0) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_JEM_DONT_USE_COM_SOL' ); ?></option>
								<option value="1"<?php if ($this->jemsettings->comunsolution == 1) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_JEM_COMBUILDER' ); ?></option>
							</select>
						</td>
					</tr>
					<tr id="integrate"<?php if (!$mode) echo ' style="display:none"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_TYPE_COM_INTEGRATION' ); ?>::<?php echo JText::_('COM_JEM_TYPE_COM_INTEGRATION_DESC'); ?>">
								<?php echo JText::_( 'COM_JEM_TYPE_COM_INTEGRATION' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$comoption = array();
							$comoption[] = JHTML::_('select.option', '0', JText::_( 'COM_JEM_LINK_PROFILE' ) );
							$comoption[] = JHTML::_('select.option', '1', JText::_( 'COM_JEM_LINK_AVATAR' ) );
							$comoptions = JHTML::_('select.genericlist', $comoption, 'comunoption', 'size="1" class="inputbox"', 'value', 'text', $this->jemsettings->comunoption );
							echo $comoptions;
							?>
						</td>
					</tr>
				</tbody>
			</table>
		</fieldset>

	</td>
  </tr>
</table>