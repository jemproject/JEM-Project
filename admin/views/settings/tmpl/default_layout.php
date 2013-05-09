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
		<td width="50%">
			<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_GENERAL_LAYOUT_SETTINGS'); ?></legend>
				<table class="admintable">
				<tbody>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DISPLAY_TIME' ); ?>::<?php echo JText::_('COM_JEM_DISPLAY_TIME_FRONT_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_DISPLAY_TIME' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
								echo JHTML::_('select.booleanlist', 'showtime', 'class="inputbox"', $this->jemsettings->showtime );
							?>
						</td>
					</tr>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_FRONT_TABLE_WIDTH' ); ?>::<?php echo JText::_('COM_JEM_FRONT_TABLE_WIDTH_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_FRONT_TABLE_WIDTH' ); ?>
							</span>
						</td>
						<td valign="top">
							<input type="text" name="tablewidth" value="<?php echo $this->jemsettings->tablewidth; ?>" size="5" maxlength="4" />
						</td>
					</tr>
				</tbody>
				</table>
			</fieldset>
			<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_DATE_COLUMN'); ?></legend>
				<table class="admintable">
				<tbody>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_WIDTH_DATE_COLUMN' ); ?>::<?php echo JText::_('COM_JEM_WIDTH_DATE_COLUMN_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_WIDTH_DATE_COLUMN' ); ?>
							</span>
						</td>
						<td valign="top">
							<input type="text" name="datewidth" value="<?php echo $this->jemsettings->datewidth; ?>" size="5" maxlength="4" />
						</td>
					</tr>
				</tbody>
				</table>
			</fieldset>

			<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_CITY_COLUMN' ); ?></legend>
				<table class="admintable">
				<tbody>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DISPLAY_CITY_FRONT' ); ?>::<?php echo JText::_('COM_JEM_DISPLAY_CITY_FRONT_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_DISPLAY_CITY_FRONT' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$mode = ($this->jemsettings->showcity == 1) ? 1 : 0;
							?>
							<input type="radio" id="showcity0" class="inputbox" name="showcity" value="0" onclick="changecityMode(0)"<?php if (!$mode) echo ' checked="checked"'; ?>/><?php echo JText::_( 'JNO' ); ?>
							<input type="radio" id="showcity1" class="inputbox" name="showcity" value="1" onclick="changecityMode(1)"<?php if ($mode) echo ' checked="checked"'; ?>/><?php echo JText::_( 'JYES' ); ?>
						</td>
					</tr>
					<tr id="city1"<?php if (!$mode) echo ' style="display:none"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_WIDTH_CITY_COLUMN' ); ?>::<?php echo JText::_('COM_JEM_WIDTH_CITY_COLUMN_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_WIDTH_CITY_COLUMN' ); ?>
							</span>
						</td>
						<td valign="top">
							<input type="text" name="citywidth" value="<?php echo $this->jemsettings->citywidth; ?>" size="5" maxlength="4" />
						</td>
					</tr>
				</tbody>
				</table>
			</fieldset>

			<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_ATTENDEE_COLUMN' ); ?></legend>
				<table class="admintable">
				<tbody>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DISPLAY_ATTENDEE_FRONT' ); ?>::<?php echo JText::_('COM_JEM_DISPLAY_ATTENDEE_FRONT_DESC'); ?>">
								<?php echo JText::_( 'COM_JEM_DISPLAY_ATTENDEE_FRONT' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php echo JHTML::_('select.booleanlist', 'showatte', 'class="inputbox"', $this->jemsettings->showatte ); ?>
						</td>
					</tr>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_WIDTH_ATTENDEE_COLUMN' ); ?>::<?php echo JText::_('COM_JEM_WIDTH_ATTENDEE_COLUMN_DESC'); ?>">
								<?php echo JText::_( 'COM_JEM_WIDTH_ATTENDEE_COLUMN' ); ?>
							</span>
						</td>
						<td valign="top">
							<input type="text" name="attewidth" value="<?php echo $this->jemsettings->attewidth; ?>" size="5" maxlength="4" />
						</td>
					</tr>
				</tbody>
				</table>
			</fieldset>

			<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_TITLE_COLUMN' ); ?></legend>
				<table class="admintable">
				<tbody>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DISPLAY_TITLE_FRONT' ); ?>::<?php echo JText::_('COM_JEM_DISPLAY_TITLE_FRONT_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_DISPLAY_TITLE_FRONT' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$mode = ($this->jemsettings->showtitle == 1) ? 1 : 0;
							?>
							<input type="radio" id="showtitle0" class="inputbox" name="showtitle" value="0" onclick="changetitleMode(0)"<?php if (!$mode) echo ' checked="checked"'; ?>/><?php echo JText::_( 'JNO' ); ?>
							<input type="radio" id="showtitle1" class="inputbox" name="showtitle" value="1" onclick="changetitleMode(1)"<?php if ($mode) echo ' checked="checked"'; ?>/><?php echo JText::_( 'JYES' ); ?>
						</td>
					</tr>
					<tr id="title1"<?php if (!$mode) echo ' style="display:none"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_WIDTH_TITLE_COLUMN' ); ?>::<?php echo JText::_('COM_JEM_WIDTH_TITLE_COLUMN_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_WIDTH_TITLE_COLUMN' ); ?>
							</span>
						</td>
						<td valign="top">
							<input type="text" name="titlewidth" value="<?php echo $this->jemsettings->titlewidth; ?>" size="5" maxlength="4" />
						</td>
					</tr>
				</tbody>
				</table>
			</fieldset>
		</td>


		<td width="50%">
			<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_VENUE_COLUMN' ); ?></legend>
				<table class="admintable">
				<tbody>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DISPLAY_VENUE_FRONT' ); ?>::<?php echo JText::_('COM_JEM_DISPLAY_VENUE_FRONT_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_DISPLAY_VENUE_FRONT' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$mode = ($this->jemsettings->showlocate == 1) ? 1 : 0;
							?>
							<input type="radio" id="showlocate0" class="inputbox" name="showlocate" value="0" onclick="changelocateMode(0)"<?php if (!$mode) echo ' checked="checked"'; ?>/><?php echo JText::_( 'JNO' ); ?>
							<input type="radio" id="showlocate1" class="inputbox" name="showlocate" value="1" onclick="changelocateMode(1)"<?php if ($mode) echo ' checked="checked"'; ?>/><?php echo JText::_( 'JYES' ); ?>
						</td>
					</tr>
					<tr id="locate1"<?php if (!$mode) echo ' style="display:none"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_WIDTH_VENUE_COLUMN' ); ?>::<?php echo JText::_('COM_JEM_WIDTH_VENUE_COLUMN_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_WIDTH_VENUE_COLUMN' ); ?>
							</span>
						</td>
						<td valign="top">
							<input type="text" name="locationwidth" value="<?php echo $this->jemsettings->locationwidth; ?>" size="5" maxlength="4" />
						</td>
					</tr>
					<tr id="locate2"<?php if (!$mode) echo ' style="display:none"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DISPLAY_LINK_TO_VENUE_VIEW' ); ?>::<?php echo JText::_('COM_JEM_DISPLAY_LINK_TO_VENUE_VIEW_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_DISPLAY_LINK_TO_VENUE_VIEW' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							echo JHTML::_('select.booleanlist', 'showlinkvenue', 'class="inputbox"', $this->jemsettings->showlinkvenue );
							?>
						</td>
					</tr>
				</tbody>
				</table>
			</fieldset>

			<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_STATE_COLUMN' ); ?></legend>
				<table class="admintable">
				<tbody>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DISPLAY_STATE_FRONT' ); ?>::<?php echo JText::_('COM_JEM_DISPLAY_STATE_FRONT_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_DISPLAY_STATE_FRONT' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$mode = ($this->jemsettings->showstate == 1) ? 1 : 0;
							?>
							<input type="radio" id="showstate0" class="inputbox" name="showstate" value="0" onclick="changestateMode(0)"<?php if (!$mode) echo ' checked="checked"'; ?>/><?php echo JText::_( 'JNO' ); ?>
							<input type="radio" id="showstate1" class="inputbox" name="showstate" value="1" onclick="changestateMode(1)"<?php if ($mode) echo ' checked="checked"'; ?>/><?php echo JText::_( 'JYES' ); ?>
						</td>
					</tr>
					<tr id="state1"<?php if (!$mode) echo ' style="display:none"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_WIDTH_STATE_COLUMN' ); ?>::<?php echo JText::_('COM_JEM_WIDTH_STATE_COLUMN_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_WIDTH_STATE_COLUMN' ); ?>
							</span>
						</td>
						<td valign="top">
							<input type="text" name="statewidth" value="<?php echo $this->jemsettings->statewidth; ?>" size="5" maxlength="4" />
						</td>
					</tr>
				</tbody>
				</table>
			</fieldset>

			<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_CATEGORY_COLUMN'); ?></legend>
				<table class="admintable">
				<tbody>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DISPLAY_CATEGORY_FRONT' ); ?>::<?php echo JText::_('COM_JEM_DISPLAY_CATEGORY_FRONT_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_DISPLAY_CATEGORY_FRONT' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$mode = ($this->jemsettings->showcat == 1) ? 1 : 0;
							?>
							<input type="radio" id="showcat0" class="inputbox" name="showcat" value="0" onclick="changecatMode(0)"<?php if (!$mode) echo ' checked="checked"'; ?>/><?php echo JText::_( 'JNO' ); ?>
							<input type="radio" id="showcat1" class="inputbox" name="showcat" value="1" onclick="changecatMode(1)"<?php if ($mode) echo ' checked="checked"'; ?>/><?php echo JText::_( 'JYES' ); ?>
						</td>
					</tr>
					<tr id="cat1"<?php if (!$mode) echo ' style="display:none"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_WIDTH_CATEGORY_COLUMN' ); ?>::<?php echo JText::_('COM_JEM_WIDTH_CATEGORY_COLUMN_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_WIDTH_CATEGORY_COLUMN' ); ?>
							</span>
						</td>
						<td valign="top">
							<input type="text" name="catfrowidth" value="<?php echo $this->jemsettings->catfrowidth; ?>" size="5" maxlength="4" />
						</td>
					</tr>
					<tr id="cat2"<?php if (!$mode) echo ' style="display:none"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_DISPLAY_LINK_TO_CATEGORY_VIEW' ); ?>::<?php echo JText::_('COM_JEM_DISPLAY_LINK_TO_CATEGORY_VIEW_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_DISPLAY_LINK_TO_CATEGORY_VIEW' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
								echo JHTML::_('select.booleanlist', 'catlinklist', 'class="inputbox"', $this->jemsettings->catlinklist );
							?>
						</td>
					</tr>
				</tbody>
				</table>
			</fieldset>


			<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_LAYOUT_TABLE_EVENTIMAGE'); ?></legend>
				<table class="admintable">
				<tbody>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_LAYOUT_DISPLAY_EVENTIMAGE' ); ?>::<?php echo JText::_('COM_JEM_LAYOUT_DISPLAY_EVENTIMAGE_DESC'); ?>">
								<?php echo JText::_( 'COM_JEM_LAYOUT_DISPLAY_EVENTIMAGE' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
								echo JHTML::_('select.booleanlist', 'showeventimage', 'class="inputbox"', $this->jemsettings->showeventimage );
							?>
						</td>
					</tr>
					<tr id="eventimage1"<?php if (!$mode) echo ' style="display:none"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_LAYOUT_TABLE_EVENTIMAGE_WIDTH' ); ?>::<?php echo JText::_('COM_JEM_LAYOUT_TABLE_EVENTIMAGE_WIDTH_DESC'); ?>">
								<?php echo JText::_( 'COM_JEM_LAYOUT_TABLE_EVENTIMAGE_WIDTH' ); ?>
							</span>
						</td>
						<td valign="top">
							<input type="text" name="tableeventimagewidth" value="<?php echo $this->jemsettings->tableeventimagewidth; ?>" size="5" maxlength="4" />
						</td>
					</tr>
				</tbody>
				</table>
			</fieldset>

		</td>
	</tr>
</table>
