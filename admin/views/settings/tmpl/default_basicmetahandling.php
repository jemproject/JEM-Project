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

		<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_META_HANDLING' ); ?></legend>
				<table class="admintable">
				<tbody>
					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_META_KEYWORDS' ); ?>::<?php echo JText::_('COM_JEM_META_KEYWORDS_DESC'); ?>">
								<?php echo JText::_( 'COM_JEM_META_KEYWORDS' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
								$meta_key = explode(", ", $this->data->meta_keywords);
							?>
							<select name="meta_keywords[]" multiple="multiple" size="5" class="inputbox">
								<option value="[title]" <?php if(in_array("[title]",$meta_key)) { echo "selected=\"selected\""; } ?>>
								<?php echo JText::_( 'COM_JEM_EVENT_TITLE' ); ?></option>
								<option value="[a_name]" <?php if(in_array("[a_name]",$meta_key)) { echo "selected=\"selected\""; } ?>>
								<?php echo JText::_( 'COM_JEM_VENUE' ); ?></option>
								<!-- <option value="[locid]" <?php if(in_array("[locid]",$meta_key)) { echo "selected=\"selected\""; } ?>>
								<?php echo JText::_( 'COM_JEM_CITY' ); ?></option> -->
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
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_META_DESCRIPTION' ); ?>::<?php echo JText::_('COM_JEM_META_DESCRIPTION_DESC'); ?>">
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
							<textarea name="meta_description" id="meta_description" cols="35" rows="3" class="inputbox"><?php echo $this->data->meta_description; ?></textarea>
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

