<?php
/**
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

?>
<!-- Meta-refresh -->
<!-- EL-Import progress -->
<?php if($this->progress->step > 1) : ?>
	<meta http-equiv="refresh" content="1; url=index.php?option=com_jem&amp;view=import&amp;task=import.eventlistimport&amp;step=<?php
		echo $this->progress->step; ?>&amp;table=<?php echo $this->progress->table; ?>&amp;prefix=<?php
        echo $this->progress->prefix; ?>&amp;current=<?php echo $this->progress->current; ?>&amp;total=<?php
		echo $this->progress->total; ?>&amp;copyImages=<?php echo $this->progress->copyImages; ?>&amp;copyAttachments=<?php echo $this->progress->copyAttachments; ?>" />
<?php endif; ?>

<!-- Tabs -->
<?php 
echo JHtml::_('tabs.start', 'import');
?>


<!-- TAB1 -->
<!-- EL-IMPORT -->
<?php 
	echo JHtml::_('tabs.panel',JText::_('COM_JEM_IMPORT_EL_TAB'), 'tab1' );
?>

<!-- Determine the progress-step -->
<!-- We're in step 0 -->

<?php if($this->progress->step == 0 && $this->existingJemData) : ?>
<!-- here we're in step 0 and we did find existing JEM-data -->

	<p><span class="jemnote"><?php echo JText::_('COM_JEM_IMPORT_EL_EXISTING_JEM_DATA'); ?></span></p>
	<br>
	<b><?php echo JText::_('COM_JEM_IMPORT_EL_DETECTED_JEM_TABLES'); ?></b>
	<ul>
	<?php
		foreach ($this->jemTables as $table => $rows) {
			if (!is_null($rows)) {
				echo "<li>".JText::sprintf('COM_JEM_IMPORT_EL_DETECTED_TABLES_NUM_ROWS', $table, $rows)."</li>";
			}
		}
	?>
	</ul>
	<p><?php echo JText::_('COM_JEM_IMPORT_EL_HOUSEKEEPING'); ?>:
		<a href="index.php?option=com_jem&amp;view=housekeeping"><?php echo JText::_('COM_JEM_HOUSEKEEPING'); ?></a>
	</p>
<?php elseif($this->progress->step == 0) : ?>
<!-- 
step0 and here we don't have existing jemData. 
As we don't have it we can import the EL-data, so we'll check for a version.
-->

	<?php if(!$this->eventlistVersion) : ?>
	<!-- no EL-version is detected -->
		<p><?php echo JText::_('COM_JEM_IMPORT_EL_NO_VERSION_DETECTED'); ?></p>
	<?php else: ?>
	<!-- here we did detect an EL version -->
		<p><?php echo JText::_('COM_JEM_IMPORT_EL_VERSION_DETECTED'); ?></p>
		<p><?php echo JText::_('COM_JEM_IMPORT_EL_DETECTED_VERSION'); ?>: <?php echo $this->eventlistVersion; ?></p>
	<?php endif; ?>

	<p><?php echo JText::_('COM_JEM_IMPORT_EL_DETECTED_TABLES'); ?>:</p>
	<!-- show the tables according to the version -->
	
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
	
	<form action="<?php echo JRoute::_('index.php?option=com_jem&view=import'); ?>" method="post" name="adminForm-el-import-prefix" id="adminForm-el-import-prefix">
			<fieldset class="adminform">
				<legend><?php echo JText::_('COM_JEM_IMPORT_EL_IMPORT_FROM_EL'); ?></legend>
				<p><?php echo JText::_('COM_JEM_IMPORT_EL_PREFIX'); ?></p>
				
				<!-- hidden fields -->
				<input type="hidden" name="eltask" id="el-task0" value="" />
				<input type="hidden" name="step" id="el-step0" value="0" />
				<input type="hidden" name="option" value="com_jem" />
				<input type="hidden" name="view" value="import" />
				<input type="hidden" name="controller" value="import" />
				
				<div class="input-append">
					<input type="text" name="prefix" value="<?php echo $this->progress->prefix; ?>" />
					<input type="submit" class="pointer" value="<?php echo JText::_('COM_JEM_IMPORT_CHECK'); ?>" onclick="document.getElementById('el-task0').value='import.eventlistImport';return true;"/>
				</div>
				
				<?php if($tableFoundCount > 0) : ?>
					<div class="clr"></div>
					<p></p>
					<p><?php echo JText::_('COM_JEM_IMPORT_EL_TABLES_DETECTED_PROCEED'); ?></p>
					<input type="submit" class="pointer" value="<?php echo JText::_('COM_JEM_IMPORT_PROCEED'); ?>" onclick="document.getElementById('el-step0').value='1'; document.getElementById('el-task0').value='import.eventlistImport';return true;"/>
				<?php endif; ?>
				
			</fieldset>
	</form>
	
<!-- We're now in the second step -->	
	<?php elseif($this->progress->step == 1): ?>
	<form action="<?php echo JRoute::_('index.php?option=com_jem&view=import'); ?>"  method="post" name="adminForm-el-import" id="adminForm-el-import">
		<div class="form-horizontal">
		<div class="span12">
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
				
				<?php if ($this->eventlistVersion == '1.1.x') { ?>
				<p>
					<?php if($this->progress->copyAttachments || $this->progress->step == 1) :?>
						<input type="checkbox" class="inputbox" id="eventlist-copy-attachments" name="copyAttachments" value="1" checked="checked" />
					<?php else : ?>
						<input type="checkbox" class="inputbox" id="eventlist-copy-attachments" name="copyAttachments" value="1" />
					<?php endif; ?>
					<?php echo JText::_('COM_JEM_IMPORT_EL_COPY_ATTACHMENTS'); ?>
				</p>
				<?php } ?>
				
				<input type="hidden" name="startToken" value="1" />
				<input type="hidden" name="step" value="2" />
				<input type="hidden" name="option" value="com_jem" />
				<input type="hidden" name="view" value="import" />
				<input type="hidden" name="controller" value="import" />
				<input type="hidden" name="task" id="el-task1" value="" />
				<input type="hidden" name="prefix" id="el-task1" value="<?php echo $this->progress->prefix; ?>" />
				<input type="submit" class="pointer" id="eventlist-import-submit" value="<?php echo JText::_('COM_JEM_IMPORT_START'); ?>" onclick="document.getElementById('el-task1').value='import.eventlistImport';return true;"/>
			</fieldset>
		</div></div>
	</form>
<?php else :?>
	<p><?php echo JText::_('COM_JEM_IMPORT_EL_IMPORT_WORK_IN_PROGRESS'); ?></p>
<?php endif; ?>

<!-- Tab2 -->
<!-- CSV_IMPORT -->
<?php  
	echo JHtml::_('tabs.panel',JText::_('COM_JEM_IMPORT_CSV_TAB'), 'tab2' );
?>	
<form action="<?php echo JRoute::_('index.php?option=com_jem&view=import'); ?>" method="post" name="adminForm" enctype="multipart/form-data" id="adminForm">
<div class="row-fluid">	
	<div class="span6">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_IMPORT_EVENTS');?></legend>
	<?php echo JText::_('COM_JEM_IMPORT_INSTRUCTIONS') ?><br /><br />
	<?php echo JText::_("COM_JEM_IMPORT_COLUMNNAMESEVENTS"); ?><br />
	<?php echo JText::_("COM_JEM_IMPORT_FIRSTROW"); ?><br />

	<?php echo JText::_("COM_JEM_IMPORT_CATEGORIES_DESC"); ?><br /><br />
	<?php echo JText::_("COM_JEM_IMPORT_POSSIBLECOLUMNS");?><br />
	<div style="background-color:silver;border:1px solid #808080"><?php echo 'categories, ' . implode(", ",$this->eventfields); ?></div><br />

	<fieldset>
		<label for="file"><?php echo JText::_('COM_JEM_IMPORT_SELECTCSV').':'; ?></label>
		<input type="file" id="event-file-upload" accept="text/*" name="Fileevents" />
		<input class="pointer" type="submit" id="event-file-upload-submit" value="<?php echo JText::_('COM_JEM_IMPORT_START'); ?>" onclick="document.getElementById('task4').value='import.csveventimport';return true;"/>
	</fieldset>
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
	<input class="pointer" type="submit" id="catevents-file-upload-submit" value="<?php echo JText::_('COM_JEM_IMPORT_START'); ?>" onclick="document.getElementById('task4').value='import.csvcateventsimport';return true;"/>
	<span id="upload-clear"></span><br /><br/>

	<label for="replace_catevents"><?php echo JText::_('COM_JEM_IMPORT_REPLACEIFEXISTS').':'; ?></label>
	<?php echo JHtml::_('select.booleanlist', 'replace_catevents', 'class="inputbox"', 0); ?>
	</fieldset>
	
</div><div class="span6">

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
	<input class="pointer" type="submit" id="venue-file-upload-submit" value="<?php echo JText::_('COM_JEM_IMPORT_START'); ?>" onclick="document.getElementById('task4').value='import.csvvenuesimport';return true;"/>
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
	<input class="pointer" type="submit" id="cat-file-upload-submit" value="<?php echo JText::_('COM_JEM_IMPORT_START'); ?>" onclick="document.getElementById('task4').value='import.csvcategoriesimport';return true;"/>
	<span id="upload-clear"></span><br /><br/>

	<label for="replace_categories"><?php echo JText::_('COM_JEM_IMPORT_REPLACEIFEXISTS').':'; ?></label>
	<?php echo JHtml::_('select.booleanlist', 'replace_categories', 'class="inputbox"', 0); ?>
	</fieldset>
	</div></div>

	<input type="hidden" name="option" value="com_jem" />
	<input type="hidden" name="view" value="import" />
	<input type="hidden" name="controller" value="import" />
	<input type="hidden" name="task" id="task4" value="" />
	</form>
<?php echo JHtml::_('tabs.end'); ?>
