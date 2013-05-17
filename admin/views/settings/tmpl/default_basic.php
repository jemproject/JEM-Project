<?php
/**
 * @version 1.9 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 *
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

defined('_JEXEC') or die;
?>
<table class="noshow">
	<tr>
		<td width="50%" valign="top">
		<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_DISPLAY_SETTINGS' ); ?></legend>
				<table class="admintable">
				<tbody>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SHOW_DETAILS' ); ?>::<?php echo JText::_('COM_JEM_SHOW_DETAILS_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_SHOW_DETAILS' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
								$showdets = array();
								$showdets[] = JHTML::_('select.option', '0', JText::_( 'COM_JEM_DETAILS_OFF' ) );
								$showdets[] = JHTML::_('select.option', '1', JText::_( 'COM_JEM_LINK_ON_TITLE' ) );
								$showdet = JHTML::_('select.genericlist', $showdets, 'showdetails', 'size="1" class="inputbox"', 'value', 'text', $this->jemsettings->showdetails );
								echo $showdet;
							?>
						</td>
					</tr>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DATE_DATE' ); ?>::<?php echo JText::_('COM_JEM_DATE_DATE_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_DATE_DATE' ); ?>
							</span>
						</td>
						<td valign="top">
							<input type="text" name="formatdate" value="<?php echo $this->jemsettings->formatdate; ?>" size="15" maxlength="15" />
							&nbsp;<a href="http://php.net/manual/en/function.date.php" target="_blank"><?php echo JText::_( 'COM_JEM_PHP_DATE_MANUAL' ); ?></a>
						</td>
					</tr>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_TIME_STRFTIME' ); ?>::<?php echo JText::_('COM_JEM_TIME_STRFTIME_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_TIME_STRFTIME' ); ?>
							</span>
						</td>
						<td valign="top">
							<input type="text" name="formattime" value="<?php echo $this->jemsettings->formattime; ?>" size="15" maxlength="15" />
							&nbsp;<a href="http://www.php.net/strftime" target="_blank"><?php echo JText::_( 'COM_JEM_PHP_STRFTIME_MANUAL' ); ?></a>
						</td>
					</tr>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_TIME_NAME' ); ?>::<?php echo JText::_('COM_JEM_TIME_NAME_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_TIME_NAME' ); ?>
							</span>
						</td>
						<td valign="top">
							<input type="text" name="timename" value="<?php echo $this->jemsettings->timename; ?>" size="15" maxlength="10" />
						</td>
					</tr>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_STORE_IP' ); ?>::<?php echo JText::_('COM_JEM_STORE_IP_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_STORE_IP' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
								echo JHTML::_('select.booleanlist', 'storeip', 'class="inputbox"', $this->jemsettings->storeip );
							?>
						</td>
					</tr>
				</tbody>
			</table>
		</fieldset>

		<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_EVENT_HANDLING' ); ?></legend>
				<table class="admintable">
				<tbody>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_OLD_EVENTS' ); ?>::<?php echo JText::_('COM_JEM_OLD_EVENTS_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_OLD_EVENTS' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$mode = 0;
							if ($this->jemsettings->oldevent >= 1) {
								$mode = 1;
							} // if
							?>
							<select name="oldevent" size="1" class="inputbox" onChange="changeoldMode()">
								<option value="0"<?php if ($this->jemsettings->oldevent == 0) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_JEM_DO_NOTHING' ); ?></option>
								<option value="1"<?php if ($this->jemsettings->oldevent == 1) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_JEM_DELETE_OLD_EVENTS' ); ?></option>
								<option value="2"<?php if ($this->jemsettings->oldevent == 2) { ?> selected="selected"<?php } ?>><?php echo JText::_( 'COM_JEM_ARCHIVE_OLD_EVENTS' ); ?></option>
							</select>&nbsp;
							<span class="error hasTip" title="<?php echo JText::_( 'COM_JEM_WARNING' ); ?>::<?php echo JText::_( 'COM_JEM_OLD_EVENTS_WARN' ); ?>">
								<?php echo $this->WarningIcon(); ?>
							</span>
						</td>
					</tr>
					<tr id="old"<?php if (!$mode) echo ' style="display:none"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_NUMBER_DELETE_DAYS' ); ?>::<?php echo JText::_('COM_JEM_NUMBER_DELETE_DAYS_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_NUMBER_DELETE_DAYS' ); ?>
							</span>
						</td>
						<td valign="top">
							<input type="text" name="minus" value="<?php echo $this->jemsettings->minus; ?>" size="3" maxlength="2" />
						</td>
					</tr>
				</tbody>
			</table>
		</fieldset>


		</td>
		<td width="50%" valign="top">

		<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_IMAGE_HANDLING' ); ?></legend>
				<table class="admintable">
				<tbody>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_IMAGE_FILESIZE' ); ?>::<?php echo JText::_('COM_JEM_IMAGE_FILESIZE_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_IMAGE_FILESIZE' ); ?>
							</span>
						</td>
						<td valign="top">
							<input type="text" name="sizelimit" value="<?php echo $this->jemsettings->sizelimit; ?>" size="10" maxlength="10" />
						</td>
					</tr>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_IMAGE_HEIGHT' ); ?>::<?php echo JText::_('COM_JEM_IMAGE_HEIGHT_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_IMAGE_HEIGHT' ); ?>
							</span>
						</td>
						<td valign="top">
							<input type="text" name="imagehight" value="<?php echo $this->jemsettings->imagehight; ?>" size="10" maxlength="10" />
						</td>
					</tr>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_IMAGE_WIDTH' ); ?>::<?php echo JText::_('COM_JEM_IMAGE_WIDTH_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_IMAGE_WIDTH' ); ?>
							</span>
						</td>
						<td valign="top">
							<input type="text" name="imagewidth" value="<?php echo $this->jemsettings->imagewidth; ?>" size="10" maxlength="10" />
							<span class="error hasTip" title="<?php echo JText::_('COM_JEM_WARNING');?>::<?php echo JText::_('COM_JEM_WARNING_MAX_IMAGEWIDTH'); ?>">
								<?php echo $this->WarningIcon(); ?>
							</span>
						</td>
					</tr>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_GD_LIBRARY' ); ?>::<?php echo JText::_('COM_JEM_GD_LIBRARY_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_GD_LIBRARY' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$mode = 0;
							if ($this->jemsettings->gddisabled == 1) {
								$mode = 1;
							} // if

							//is the gd library installed on the server running JEM?
							if ($gdv = JEMImage::gdVersion()) {

								//is it Version two or higher? If yes let the user the choice
								if ($gdv >= 2) {
								?>
									<input type="radio" id="gddisabled0" name="gddisabled" value="0" onclick="changegdMode(0)"<?php if (!$mode) echo ' checked="checked"'; ?>/><?php echo JText::_( 'JNO' ); ?>
									<input type="radio" id="gddisabled1" name="gddisabled" value="1" onclick="changegdMode(1)"<?php if ($mode) echo ' checked="checked"'; ?>/><?php echo JText::_( 'JYES' ); ?>
								<?php
									$note	= JText::_( 'COM_JEM_GD_VERSION_TWO' );
									$color	= 'green';

								//No it is version one...disable thumbnailing
								} else {
								?>
								<input type="hidden" name="gddisabled" value="0" />
								<?php
								$note	= JText::_( 'COM_JEM_GD_VERSION_ONE' );
								$color	= 'red';
								}

							//the gd library is not available on this server...disable thumbnailing
							} else {
							?>
								<input type="hidden" name="gddisabled" value="0" />
							<?php
								$note	= JText::_( 'COM_JEM_NO_GD_LIBRARY' );
								$color	= 'red';
							}
							?>
							<br />
							<strong><?php echo JText::_( 'COM_JEM_STATUS' ).':'; ?></strong>
							<font color="<?php echo $color; ?>"><?php echo $note; ?></font>
						</td>
					</tr>
					<tr id="gd1"<?php if (!$mode) echo ' style="display:none"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_IMAGE_LIGHTBOX' ); ?>::<?php echo JText::_('COM_JEM_IMAGE_LIGHTBOX_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_IMAGE_LIGHTBOX' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$html = JHTML::_('select.booleanlist', 'lightbox', 'class="inputbox"', $this->jemsettings->lightbox );
							echo $html;
							?>
						</td>
					</tr>
				</tbody>
			</table>
		</fieldset>

		<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_META_HANDLING' ); ?></legend>
				<table class="admintable">
				<tbody>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_META_KEYWORDS' ); ?>::<?php echo JText::_('COM_JEM_META_KEYWORDS_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_META_KEYWORDS' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
								$meta_key = explode(", ", $this->jemsettings->meta_keywords);
							?>
							<select name="meta_keywords[]" multiple="multiple" size="5" class="inputbox">
								<option value="[title]" <?php if(in_array("[title]",$meta_key)) { echo "selected=\"selected\""; } ?>>
								<?php echo JText::_( 'COM_JEM_EVENT_TITLE' ); ?></option>
								<option value="[a_name]" <?php if(in_array("[a_name]",$meta_key)) { echo "selected=\"selected\""; } ?>>
								<?php echo JText::_( 'COM_JEM_VENUE' ); ?></option>
								<!-- <option value="[locid]" <?php if(in_array("[locid]",$meta_key)) { echo "selected=\"selected\""; } ?>>
								<?php echo JText::_( 'COM_JEM_CITY
								' ); ?></option> -->
								<option value="[dates]" <?php if(in_array("[dates]",$meta_key)) { echo "selected=\"selected\""; } ?>>
								<?php echo JText::_( 'COM_JEM_DATE' ); ?></option>
								<option value="[times]" <?php if(in_array("[times]",$meta_key)) { echo "selected=\"selected\""; } ?>>
								<?php echo JText::_( 'COM_JEM_EVENT_TIME' ); ?></option>
								<option value="[enddates]" <?php if(in_array("[enddates]",$meta_key)) { echo "selected=\"selected\""; } ?>>
								<?php echo JText::_( 'COM_JEM_ENDDATE' ); ?></option>
								<option value="[endtimes]" <?php if(in_array("[endtimes]",$meta_key)) { echo "selected=\"selected\""; } ?>>
								<?php echo JText::_( 'COM_JEM_END_TIME' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_META_DESCRIPTION' ); ?>::<?php echo JText::_('COM_JEM_META_DESCRIPTION_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_META_DESCRIPTION' ); ?>
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
									$("meta_description").value = "<?php echo JText::_( 'COM_JEM_META_DESCRIPTION_STANDARD' ); ?>";
								}
							-->
							</script>

							<input class="inputbox" type="button" onclick="insert_keyword('[title]')" value="<?php echo JText::_( 'COM_JEM_EVENT_TITLE' ); ?>" />
							<input class="inputbox" type="button" onclick="insert_keyword('[a_name]')" value="<?php echo JText::_( 'COM_JEM_VENUE' ); ?>" />
							<input class="inputbox" type="button" onclick="insert_keyword('[dates]')" value="<?php echo JText::_( 'COM_JEM_DATE' ); ?>" />
							<p>
								<input class="inputbox" type="button" onclick="insert_keyword('[times]')" value="<?php echo JText::_( 'COM_JEM_EVENT_TIME' ); ?>" />
								<input class="inputbox" type="button" onclick="insert_keyword('[enddates]')" value="<?php echo JText::_( 'COM_JEM_ENDDATE' ); ?>" />
								<input class="inputbox" type="button" onclick="insert_keyword('[endtimes]')" value="<?php echo JText::_( 'COM_JEM_END_TIME' ); ?>" />
							</p>
							<textarea name="meta_description" id="meta_description" cols="35" rows="3" class="inputbox"><?php echo $this->jemsettings->meta_description; ?></textarea>
							<br/>
							<input type="button" value="<?php echo JText::_( 'COM_JEM_META_DESCRIPTION_BUTTON' ); ?>" onclick="include_description()" />
							&nbsp;
							<span class="error hasTip" title="<?php echo JText::_( 'COM_JEM_WARNING' );?>::<?php echo JText::_( 'COM_JEM_META_DESCRIPTION_WARN' ); ?>">
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