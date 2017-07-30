<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>
<?php if($this->progress->step > 1) : ?>
	<meta http-equiv="refresh" content="1; url=index.php?option=com_jem&amp;view=import&amp;task=import.eventlistimport&amp;step=<?php
		echo $this->progress->step; ?>&amp;table=<?php echo $this->progress->table; ?>&amp;current=<?php
		echo $this->progress->current; ?>&amp;total=<?php echo $this->progress->total; ?>" />
<?php endif; ?>

<?php if (isset($this->sidebar)) : ?>
<div id="j-sidebar-container" class="span2">
	<?php echo $this->sidebar; ?>
</div>
<div id="j-main-container" class="span10">
<?php endif; ?>

<?php echo JHtml::_('tabs.start', 'det-pane', array('useCookie'=>1)); ?>

<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_IMPORT_EL_TAB'), 'el-import' ); ?>

<?php if($this->progress->step == 0 && $this->existingJemData) : ?>
	<p><?php echo JText::_('COM_JEM_IMPORT_EL_EXISTING_JEM_DATA'); ?></p>
	<p><?php echo JText::_('COM_JEM_IMPORT_EL_DETECTED_JEM_TABLES'); ?>:</p>
	<ul>
	<?php
		foreach($this->jemTables as $table => $rows) {
			if(!is_null($rows)) {
				echo "<li>".JText::sprintf('COM_JEM_IMPORT_EL_DETECTED_TABLES_NUM_ROWS', $table, $rows)."</li>";
			}
		}
	?>
	</ul>
	<p><?php echo JText::_('COM_JEM_IMPORT_EL_HOUSEKEEPING'); ?>:
		<a href="index.php?option=com_jem&amp;view=housekeeping"><?php echo JText::_('COM_JEM_HOUSEKEEPING'); ?></a>
	</p>
<?php elseif($this->progress->step == 0) : ?>
	<?php if(!$this->eventlistVersion) : ?>
		<p><?php echo JText::_('COM_JEM_IMPORT_EL_NO_VERSION_DETECTED'); ?></p>
	<?php else: ?>
		<p><?php echo JText::_('COM_JEM_IMPORT_EL_VERSION_DETECTED'); ?></p>
		<p><?php echo JText::_('COM_JEM_IMPORT_EL_DETECTED_VERSION'); ?>: <?php echo $this->eventlistVersion; ?></p>
	<?php endif; ?>

	<p><?php echo JText::_('COM_JEM_IMPORT_EL_DETECTED_TABLES'); ?>:</p>
	<ul>
		<?php
			$tableFoundCount = 0;
			foreach($this->eventlistTables as $table => $rows) {
				if(!is_null($rows)) {
					$tableFoundCount++;
					echo "<li>".JText::sprintf('COM_JEM_IMPORT_EL_DETECTED_TABLES_NUM_ROWS', $this->prefixToShow.$table, $rows)."</li>";
				}
			}
			if($tableFoundCount == 0) {
				echo "<li><em>".JText::_('COM_JEM_IMPORT_EL_MISSING_TABLES_NONE')."</em></li>";
			}
		?>
	</ul>
	<p><?php echo JText::_('COM_JEM_IMPORT_EL_MISSING_TABLES'); ?>:</p>
	<ul>
		<?php
			$missedTables = array();
			foreach($this->eventlistTables as $table => $rows) {
				if (is_null($rows)) {
					$missedTables[] = $table;
					echo "<li>".$this->prefixToShow.$table."</li>";
				}
			}
			if (count($missedTables) == 0) {
				echo "<li><em>".JText::_('COM_JEM_IMPORT_EL_MISSING_TABLES_NONE')."</em></li>";
			}
		?>
	</ul>
	<?php
	if ((count($missedTables) == 2) && !count(array_diff($missedTables, array('eventlist_attachments', 'eventlist_cats_event_relations')))) {
		echo "<p>".JText::_('COM_JEM_IMPORT_EL_MISSING_TABLES_V11')."</p>";
	}
	?>
	<form action="index.php?option=com_jem&amp;view=import" method="post" name="adminForm-el-import-prefix" id="adminForm-el-import-prefix">
		<div class="width-100">
			<fieldset class="adminform">
				<legend><?php echo JText::_('COM_JEM_IMPORT_EL_IMPORT_FROM_EL'); ?></legend>
				<p><?php echo JText::_('COM_JEM_IMPORT_EL_PREFIX'); ?></p>
				<p><?php echo JText::_('COM_JEM_IMPORT_EL_PREFIX_ATTENTION'); ?></p>
				<?php echo JHtml::_('form.token'); ?>
				<input type="hidden" name="task" id="el-task0" value="" />
				<input type="hidden" name="option" value="com_jem" />
				<input type="hidden" name="view" value="import" />
				<input type="hidden" name="step" id="el-step0" value="0" />
				<input type="text" name="prefix" value="<?php echo $this->progress->prefix; ?>" />
				<input type="submit" value="<?php echo JText::_('COM_JEM_IMPORT_CHECK'); ?>"
					onclick="document.getElementById('el-task0').value='import.eventlistImport';return true;"/>
				<?php if($tableFoundCount > 0) : ?>
					<div class="clr"></div>
					<p></p>
					<p><?php echo JText::_('COM_JEM_IMPORT_EL_TABLES_DETECTED_PROCEED'); ?></p>
					<input type="submit" value="<?php echo JText::_('COM_JEM_IMPORT_PROCEED'); ?>"
						onclick="document.getElementById('el-step0').value='1'; document.getElementById('el-task0').value='import.eventlistImport';return true;"/>
				<?php endif; ?>
			</fieldset>
		</div>
	</form>
<?php elseif($this->progress->step == 1): ?>
	<form action="index.php" method="post" name="adminForm-el-import" id="adminForm-el-import">
		<div class="width-100">
			<fieldset class="adminform">
				<legend><?php echo JText::_('COM_JEM_IMPORT_EL_IMPORT_FROM_EL'); ?></legend>
				<p><?php echo JText::_('COM_JEM_IMPORT_EL_TRY_IMPORT'); ?></p>
				<p><?php echo JText::_('COM_JEM_IMPORT_EL_ATTENTION'); ?>:<br/>
					<?php echo JText::_('COM_JEM_IMPORT_EL_ATTENTION_DURATION'); ?></p>
				<p>
					<?php if($this->progress->copyImages || $this->progress->step == 1) :?>
						<input type="checkbox" class="inputbox" id="eventlist-copy-images" name="copyImages" value="1" checked="checked" />
					<?php else : ?>
						<input type="checkbox" class="inputbox" id="eventlist-copy-images" name="copyImages" value="1" />
					<?php endif; ?>
					<?php echo JText::_('COM_JEM_IMPORT_EL_COPY_IMAGES'); ?>
				</p>
				<?php if (!empty($this->attachmentsPossible)) : ?>
				<p>
					<?php if($this->progress->copyAttachments || $this->progress->step == 1) :?>
						<input type="checkbox" class="inputbox" id="eventlist-copy-attachments" name="copyAttachments" value="1" checked="checked" />
					<?php else : ?>
						<input type="checkbox" class="inputbox" id="eventlist-copy-attachments" name="copyAttachments" value="1" />
					<?php endif; ?>
					<?php echo JText::_('COM_JEM_IMPORT_EL_COPY_ATTACHMENTS'); ?>
				</p>
				<?php endif; ?>
				<p>
					<?php if($this->progress->fromJ15) :?>
						<input type="checkbox" class="inputbox" id="eventlist-from-j15" name="fromJ15" value="1" checked="checked" />
					<?php else : ?>
						<input type="checkbox" class="inputbox" id="eventlist-from-j15" name="fromJ15" value="1" />
					<?php endif; ?>
					<?php echo JText::_('COM_JEM_IMPORT_EL_IMPORT_FROM_JOOMLA15'); ?>
				</p>
				<?php echo JHtml::_('form.token'); ?>
				<input type="hidden" name="startToken" value="1" />
				<input type="hidden" name="step" value="2" />
				<input type="hidden" name="option" value="com_jem" />
				<input type="hidden" name="view" value="import" />
				<input type="hidden" name="controller" value="import" />
				<input type="hidden" name="task" id="el-task1" value="" />
				<input type="submit" id="eventlist-import-submit" value="<?php echo JText::_('COM_JEM_IMPORT_START'); ?>"
					onclick="document.getElementById('el-task1').value='import.eventlistImport';return true;"/>
			</fieldset>
		</div>
	</form>
<?php else :?>
	<p><?php echo JText::_('COM_JEM_IMPORT_EL_IMPORT_WORK_IN_PROGRESS'); ?></p>
<?php endif; ?>


<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_IMPORT_CSV_TAB'), 'csv-import' ); ?>

<form action="index.php" method="post" name="adminForm" enctype="multipart/form-data" id="adminForm">

	<table style="width:100%">
	<tr>
	<td>
	<div class="width-50 fltlft">

	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_IMPORT_EVENTS');?></legend>
	<?php echo JText::_('COM_JEM_IMPORT_INSTRUCTIONS') ?><br /><br />
	<?php echo JText::_("COM_JEM_IMPORT_COLUMNNAMESEVENTS"); ?><br />
	<?php echo JText::_("COM_JEM_IMPORT_FIRSTROW"); ?><br />

	<?php echo JText::_("COM_JEM_IMPORT_CATEGORIES_DESC"); ?><br /><br />
	<?php echo JText::_("COM_JEM_IMPORT_POSSIBLECOLUMNS");?><br />
	<div style="background-color:silver;border:1px solid #808080"><?php echo 'categories, ' . implode(", ",$this->eventfields); ?></div><br />

	<label for="file"><?php echo JText::_('COM_JEM_IMPORT_SELECTCSV').':'; ?></label>
	<input type="file" id="event-file-upload" accept="text/*" name="Fileevents" />
	<input type="submit" id="event-file-upload-submit" value="<?php echo JText::_('COM_JEM_IMPORT_START'); ?>" onclick="document.getElementById('task1').value='import.csveventimport';return true;"/>
	<span id="upload-clear"></span><br /><br/>

	<label for="replace_events"><?php echo JText::_('COM_JEM_IMPORT_REPLACEIFEXISTS').':'; ?></label>
	<?php echo JHtml::_('select.booleanlist', 'replace_events', 'class="inputbox"', 0); ?>
	</fieldset>


	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_IMPORT_CAT_EVENTS');?></legend>
	<?php echo JText::_('COM_JEM_IMPORT_INSTRUCTIONS') ?><br /><br />
	<?php echo JText::_("COM_JEM_IMPORT_COLUMNNAMESCATEVENTS"); ?><br />
	<?php echo JText::_("COM_JEM_IMPORT_FIRSTROW"); ?><br />

	<?php echo JText::_("COM_JEM_IMPORT_CATEGORIES_DESC"); ?><br /><br />
	<?php echo JText::_("COM_JEM_IMPORT_POSSIBLECOLUMNS");?><br />
	<div style="background-color:silver;border:1px solid #808080"><?php echo implode(", ",$this->cateventsfields); ?></div><br />

	<label for="file"><?php echo JText::_('COM_JEM_IMPORT_SELECTCSV').':'; ?></label>
	<input type="file" id="catevents-file-upload" accept="text/*" name="Filecatevents" />
	<input type="submit" id="catevents-file-upload-submit" value="<?php echo JText::_('COM_JEM_IMPORT_START'); ?>" onclick="document.getElementById('task1').value='import.csvcateventsimport';return true;"/>
	<span id="upload-clear"></span><br /><br/>

	<label for="replace_catevents"><?php echo JText::_('COM_JEM_IMPORT_REPLACEIFEXISTS').':'; ?></label>
	<?php echo JHtml::_('select.booleanlist', 'replace_catevents', 'class="inputbox"', 0); ?>
	</fieldset>

	<div class="clr"></div>
	</div>

	<div class="width-50 fltrt">

	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_IMPORT_VENUES');?></legend>
	<?php echo JText::_('COM_JEM_IMPORT_INSTRUCTIONS') ?><br /><br />
	<?php echo JText::_("COM_JEM_IMPORT_COLUMNNAMESVENUES"); ?><br />
	<?php echo JText::_("COM_JEM_IMPORT_FIRSTROW"); ?><br />

	<?php echo JText::_("COM_JEM_IMPORT_CATEGORIES_DESC"); ?><br /><br />
	<?php echo JText::_("COM_JEM_IMPORT_POSSIBLECOLUMNS");?><br />
	<div style="background-color:silver;border:1px solid #808080"><?php echo implode(", ",$this->venuefields); ?></div><br />

	<label for="file"><?php echo JText::_('COM_JEM_IMPORT_SELECTCSV').':'; ?></label>
	<input type="file" id="venue-file-upload" accept="text/*" name="Filevenues" />
	<input type="submit" id="venue-file-upload-submit" value="<?php echo JText::_('COM_JEM_IMPORT_START'); ?>" onclick="document.getElementById('task1').value='import.csvvenuesimport';return true;"/>
	<span id="upload-clear"></span><br /><br/>

	<label for="replace_venues"><?php echo JText::_('COM_JEM_IMPORT_REPLACEIFEXISTS').':'; ?></label>
	<?php echo JHtml::_('select.booleanlist', 'replace_venues', 'class="inputbox"', 0); ?>
	</fieldset>

	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_IMPORT_CATEGORIES');?></legend>
	<?php echo JText::_('COM_JEM_IMPORT_INSTRUCTIONS') ?><br /><br />
	<?php echo JText::_("COM_JEM_IMPORT_COLUMNNAMESCATEGORIES"); ?><br />
	<?php echo JText::_("COM_JEM_IMPORT_FIRSTROW"); ?><br />

	<?php echo JText::_("COM_JEM_IMPORT_CATEGORIES_DESC"); ?><br /><br />
	<?php echo JText::_("COM_JEM_IMPORT_POSSIBLECOLUMNS");?><br />
	<div style="background-color:silver;border:1px solid #808080"><?php echo implode(", ",$this->catfields); ?></div><br />

	<label for="file"><?php echo JText::_('COM_JEM_IMPORT_SELECTCSV').':'; ?></label>
	<input type="file" id="cat-file-upload" accept="text/*" name="Filecategories" />
	<input type="submit" id="cat-file-upload-submit" value="<?php echo JText::_('COM_JEM_IMPORT_START'); ?>" onclick="document.getElementById('task1').value='import.csvcategoriesimport';return true;"/>
	<span id="upload-clear"></span><br /><br/>

	<label for="replace_categories"><?php echo JText::_('COM_JEM_IMPORT_REPLACEIFEXISTS').':'; ?></label>
	<?php echo JHtml::_('select.booleanlist', 'replace_categories', 'class="inputbox"', 0); ?>
	</fieldset>

	<div class="clr"></div>
	</div>


	</td>
	</tr>
	</table>

	<?php echo JHtml::_('form.token'); ?>
	<input type="hidden" name="option" value="com_jem" />
	<input type="hidden" name="view" value="import" />
	<input type="hidden" name="controller" value="import" />
	<input type="hidden" name="task" id="task1" value="" />
</form>
<?php echo JHtml::_('tabs.end'); ?>

<?php if (isset($this->sidebar)) : ?>
</div>
<?php endif; ?>
