<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>

<script type="text/javascript">
<!--
	function insert_keyword($keyword) {
		document.getElementById("jform_meta_description").value += " " + $keyword;
	}

	function include_description() {
		document.getElementById("jform_meta_description").value = "<?php echo Text::_( 'COM_JEM_META_DESCRIPTION_STANDARD' ); ?>";
	}
-->
</script>

<div class="width-100" style="padding: 10px 1vw;">
    <fieldset class="options-form">
		<legend><?php echo Text::_( 'COM_JEM_META_HANDLING' ); ?></legend>
		<ul class="adminformlist">
			<li><label id="jform_meta_keywords-lbl" <?php echo JEMOutput::tooltip(Text::_('COM_JEM_META_KEYWORDS'), Text::_('COM_JEM_META_KEYWORDS_DESC')); ?>>
					<?php echo Text::_( 'COM_JEM_META_KEYWORDS' ); ?>
				</label>
				<div style="display: block;">
					<?php
						// TODO use jforms here
						$meta_key = explode(", ", $this->data->meta_keywords);
					?>
					<select name="meta_keywords[]" multiple="multiple" size="6" class="inputbox form-control" id="jform_meta_keywords">
						<option value="[title]" <?php if(in_array("[title]",$meta_key)) { echo "selected=\"selected\""; } ?>>
						<?php echo Text::_( 'COM_JEM_EVENT_TITLE' ); ?></option>
						<option value="[a_name]" <?php if(in_array("[a_name]",$meta_key)) { echo "selected=\"selected\""; } ?>>
						<?php echo Text::_( 'COM_JEM_VENUE' ); ?></option>
						<!-- <option value="[locid]" <?php if(in_array("[locid]",$meta_key)) { echo "selected=\"selected\""; } ?>>
						<?php echo Text::_( 'COM_JEM_CITY' ); ?></option> -->
						<option value="[dates]" <?php if(in_array("[dates]",$meta_key)) { echo "selected=\"selected\""; } ?>>
						<?php echo Text::_( 'COM_JEM_STARTDATE' ); ?></option>
						<option value="[times]" <?php if(in_array("[times]",$meta_key)) { echo "selected=\"selected\""; } ?>>
						<?php echo Text::_( 'COM_JEM_STARTTIME' ); ?></option>
						<option value="[enddates]" <?php if(in_array("[enddates]",$meta_key)) { echo "selected=\"selected\""; } ?>>
						<?php echo Text::_( 'COM_JEM_ENDDATE' ); ?></option>
						<option value="[endtimes]" <?php if(in_array("[endtimes]",$meta_key)) { echo "selected=\"selected\""; } ?>>
						<?php echo Text::_( 'COM_JEM_ENDTIME' ); ?></option>
					</select>
				</div>
			</li>

			<li><?php echo $this->form->getLabel('meta_description'); ?>
				<div style="display: block;">
					<input class="inputbox" type="button" onclick="insert_keyword('[title]')" value="<?php echo Text::_( 'COM_JEM_EVENT_TITLE' ); ?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[a_name]')" value="<?php echo Text::_( 'COM_JEM_VENUE' ); ?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[dates]')" value="<?php echo Text::_( 'COM_JEM_STARTDATE' ); ?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[times]')" value="<?php echo Text::_( 'COM_JEM_STARTTIME' ); ?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[enddates]')" value="<?php echo Text::_( 'COM_JEM_ENDDATE' ); ?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[endtimes]')" value="<?php echo Text::_( 'COM_JEM_ENDTIME' ); ?>" />
					<br/>
					<?php echo $this->form->getInput('meta_description'); ?>
					<br/>
					<input type="button" value="<?php echo Text::_( 'COM_JEM_META_DESCRIPTION_BUTTON' ); ?>" onclick="include_description()" />
					&nbsp;
					<span <?php echo JEMOutput::tooltip(Text::_('COM_JEM_WARNING'), Text::_('COM_JEM_META_DESCRIPTION_WARN'), 'error'); ?>>
						<?php echo $this->WarningIcon(); ?>
					</span>
				</div>
			</li>

		</ul>
	</fieldset>
</div>
