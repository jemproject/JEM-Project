<?php
/**
 * @version 2.1.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>

<script type="text/javascript">
<!--
	function insert_keyword($keyword) {
		$("jform_meta_description").value += " " + $keyword;
	}

	function include_description() {
		$("jform_meta_description").value = "<?php echo JText::_( 'COM_JEM_META_DESCRIPTION_STANDARD' ); ?>";
	}
-->
</script>

<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_( 'COM_JEM_META_HANDLING' ); ?></legend>
		<ul class="adminformlist">
			<li><label id="jform_meta_keywords-lbl" <?php echo JEMOutput::tooltip(JText::_('COM_JEM_META_KEYWORDS'), JText::_('COM_JEM_META_KEYWORDS_DESC')); ?>>
					<?php echo JText::_( 'COM_JEM_META_KEYWORDS' ); ?>
				</label>
				<div style="display: inline-block;">
					<?php
						// TODO use jforms here
						$meta_key = explode(", ", $this->data->meta_keywords);
					?>
					<select name="meta_keywords[]" multiple="multiple" size="5" class="inputbox" id="jform_meta_keywords">
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
				</div>
			</li>

			<li><?php echo $this->form->getLabel('meta_description'); ?>
				<div style="display: inline-block;">
					<input class="inputbox" type="button" onclick="insert_keyword('[title]')" value="<?php echo JText::_( 'COM_JEM_EVENT_TITLE' ); ?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[a_name]')" value="<?php echo JText::_( 'COM_JEM_VENUE' ); ?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[dates]')" value="<?php echo JText::_( 'COM_JEM_DATE' ); ?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[times]')" value="<?php echo JText::_( 'COM_JEM_EVENT_TIME' ); ?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[enddates]')" value="<?php echo JText::_( 'COM_JEM_ENDDATE' ); ?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[endtimes]')" value="<?php echo JText::_( 'COM_JEM_END_TIME' ); ?>" />
					<br/>
					<?php echo $this->form->getInput('meta_description'); ?>
					<br/>
					<input type="button" value="<?php echo JText::_( 'COM_JEM_META_DESCRIPTION_BUTTON' ); ?>" onclick="include_description()" />
					&nbsp;
					<span <?php echo JEMOutput::tooltip(JText::_('COM_JEM_WARNING'), JText::_('COM_JEM_META_DESCRIPTION_WARN'), 'error'); ?>>
						<?php echo $this->WarningIcon(); ?>
					</span>
				</div>
			</li>

		</ul>
	</fieldset>
</div>