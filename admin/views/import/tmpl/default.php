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
<?php if($this->progress->step > 0) : ?>
	<meta http-equiv="refresh" content="1; url=index.php?option=com_jem&amp;view=import&amp;task=import.eventlistimport&amp;step=<?php
		echo $this->progress->step; ?>&amp;table=<?php echo $this->progress->table; ?>&amp;current=<?php
		echo $this->progress->current; ?>&amp;total=<?php echo $this->progress->total; ?>&amp;copyImages=<?php
		echo $this->progress->copyImages; ?>">
<?php endif; ?>
<?php echo JHtml::_('tabs.start', 'det-pane', array('useCookie'=>1)); ?>


<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_IMPORT_EL_TAB'), 'el-import' ); ?>

<?php if(!$this->eventlistVersion) : ?>
	<p><?php echo JText::_('COM_JEM_IMPORT_EL_NO_VERSION_DETECTED'); ?></p>
<?php elseif($this->existingJemData && $this->progress->table == '') : ?>
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
		<a href="index.php?option=com_jem&amp;view=cleanup"><?php echo JText::_('COM_JEM_CLEANUP'); ?></a>
	</p>
<?php else : ?>
	<p><?php echo JText::_('COM_JEM_IMPORT_EL_VERSION_DETECTED'); ?></p>
	<p><?php echo JText::_('COM_JEM_IMPORT_EL_TRY_IMPORT'); ?></p>

	<hr/>
	<p><?php echo JText::_('COM_JEM_IMPORT_EL_DETECTED_VERSION'); ?>: <?php echo $this->eventlistVersion; ?></p>
	<p><?php echo JText::_('COM_JEM_IMPORT_EL_DETECTED_TABLES'); ?>:</p>
	<ul>
		<?php
			foreach($this->eventlistTables as $table => $rows) {
				if(!is_null($rows)) {
					echo "<li>".JText::sprintf('COM_JEM_IMPORT_EL_DETECTED_TABLES_NUM_ROWS', $table, $rows)."</li>";
				}
			}
		?>
	</ul>
	<p><?php echo JText::_('COM_JEM_IMPORT_EL_MISSING_TABLES'); ?>:</p>
	<ul>
		<?php
			$tableCount = 0;
			foreach($this->eventlistTables as $table => $rows) {
				if(is_null($rows)) {
					$tableCount++;
					echo "<li>".$table."</li>";
				}
			}
			if($tableCount == 0) {
				echo "<li>".JText::_('COM_JEM_IMPORT_EL_MISSING_TABLES_NONE')."</li>";
			}
		?>
	</ul>

	<form action="index.php" method="post" name="adminForm-el-import" id="adminForm-el-import">
		<div class="width-100">
			<fieldset class="adminform">
				<legend><?php echo JText::_('COM_JEM_IMPORT_EL_IMPORT_FROM_EL'); ?></legend>
				<p><?php echo JText::_('COM_JEM_IMPORT_EL_ATTENTION'); ?>:</p>
				<p><?php echo JText::_('COM_JEM_IMPORT_EL_ATTENTION_DURATION'); ?></p>
				<p>
					<?php if($this->progress->copyImages || $this->progress->step == 0) :?>
						<input type="checkbox" class="inputbox" id="eventlist-copy-images" name="copyImages" value="1" checked="checked" />
					<?php else : ?>
						<input type="checkbox" class="inputbox" id="eventlist-copy-images" name="copyImages" value="1" />
					<?php endif; ?>
					<?php echo JText::_('COM_JEM_IMPORT_EL_COPY_IMAGES'); ?>
				</p>
				<input type="submit" id="eventlist-import-submit" value="<?php echo JText::_('COM_JEM_IMPORT_START'); ?>"
					onclick="document.getElementsByName('task')[0].value='import.eventlistImport';return true;"/>
			</fieldset>
		</div>
		<input type="hidden" name="startToken" value="1" />
		<input type="hidden" name="step" value="1" />
		<input type="hidden" name="option" value="com_jem" />
		<input type="hidden" name="view" value="import" />
		<input type="hidden" name="controller" value="import" />
		<input type="hidden" name="task" value="" />
	</form>
<?php endif; ?>

<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_IMPORT_CSV_TAB'), 'csv-import' ); ?>

<form action="index.php" method="post" name="adminForm" enctype="multipart/form-data" id="adminForm">
	<fieldset>
		<legend><?php echo JText::_('COM_JEM_IMPORT_EVENTS'); ?></legend>
		<?php echo JText::_('COM_JEM_IMPORT_INSTRUCTIONS') ?>
		<ul>
			<li>
				<?php echo JText::_("COM_JEM_IMPORT_COLUMNNAMESEVENTS"); ?>
			</li>
			<li>
				<?php echo JText::_("COM_JEM_IMPORT_FIRSTROW"); ?>
			</li>
			<li>
				<?php echo JText::_("COM_JEM_IMPORT_CSVFORMAT"); ?>
			</li>
			<li>
				<?php echo JText::_("COM_JEM_IMPORT_CATEGORIES_DESC"); ?>
			</li>
			<li>
				<?php echo JText::_( "COM_JEM_IMPORT_POSSIBLECOLUMNS" ).'categories, ' . implode(", ",$this->eventfields); ?>
			</li>
		</ul>
		<table>
			<tr>
				<td>
					<label for="file">
						<?php echo JText::_('COM_JEM_IMPORT_SELECTCSV').':'; ?>
					</label>
				</td>
				<td>
					<input type="file" id="event-file-upload" accept="text/*" name="Fileevents" />
					<input type="submit" id="event-file-upload-submit" value="<?php echo JText::_('COM_JEM_IMPORT_START'); ?>" onclick="document.getElementsByName('task')[0].value='import.csveventimport';return true;"/>
					<span id="upload-clear"></span>
				</td>
			</tr>
			<tr>
				<td>
					<label for="replace_events">
						<?php echo JText::_('COM_JEM_IMPORT_REPLACEIFEXISTS').':'; ?>
					</label>
				</td>
				<td>
					<?php echo JHTML::_('select.booleanlist', 'replace_events', 'class="inputbox"', 0); ?>
				</td>
			</tr>
		</table>
	</fieldset>

	<fieldset>
		<legend><?php echo JText::_('COM_JEM_IMPORT_VENUES'); ?></legend>
		<?php echo JText::_('COM_JEM_IMPORT_INSTRUCTIONS') ?>
		<ul>
			<li>
				<?php echo JText::_("COM_JEM_IMPORT_COLUMNNAMESCATEGORIES"); ?>
			</li>
			<li>
				<?php echo JText::_("COM_JEM_IMPORT_FIRSTROW"); ?>
			</li>
			<li>
				<?php echo JText::_("COM_JEM_IMPORT_CSVFORMAT"); ?>
			</li>
			<li>
				<?php echo JText::_("COM_JEM_IMPORT_POSSIBLECOLUMNS").implode(", ",$this->venuefields); ?>
			</li>
		</ul>
		<table>
			<tr>
				<td>
					<label for="file">
						<?php echo JText::_('COM_JEM_IMPORT_SELECTCSV').':'; ?>
					</label>
				</td>
				<td>
					<input type="file" id="venue-file-upload" accept="text/*" name="Filevenues" />
					<input type="submit" id="venue-file-upload-submit" value="<?php echo JText::_('COM_JEM_IMPORT_START'); ?>" onclick="document.getElementsByName('task')[0].value='import.csvvenuesimport';return true;"/>
					<span id="upload-clear"></span>
				</td>
			</tr>
			<tr>
				<td>
					<label for="replace_venues">
						<?php echo JText::_('COM_JEM_IMPORT_REPLACEIFEXISTS').':'; ?>
					</label>
				</td>
				<td>
					<?php echo JHTML::_('select.booleanlist', 'replace_venues', 'class="inputbox"', 0); ?>
				</td>
			</tr>
		</table>
	</fieldset>

	<fieldset>
		<legend><?php echo JText::_('COM_JEM_IMPORT_CATEGORIES'); ?></legend>
		<?php echo JText::_('COM_JEM_IMPORT_INSTRUCTIONS') ?>
		<ul>
			<li>
				<?php echo JText::_("COM_JEM_IMPORT_COLUMNNAMESCATEGORIES"); ?>
			</li>
			<li>
				<?php echo JText::_("COM_JEM_IMPORT_FIRSTROW"); ?>
			</li>
			<li>
				<?php echo JText::_("COM_JEM_IMPORT_CSVFORMAT"); ?>
			</li>
			<li>
				<?php echo JText::_("COM_JEM_IMPORT_POSSIBLECOLUMNS").implode(", ",$this->catfields); ?>
			</li>
		</ul>
		<table>
			<tr>
				<td>
					<label for="file">
						<?php echo JText::_('COM_JEM_IMPORT_SELECTCSV').':'; ?>
					</label>
				</td>
				<td>
					<input type="file" id="cat-file-upload" accept="text/*" name="Filecategories" />
					<input type="submit" id="cat-file-upload-submit" value="<?php echo JText::_('COM_JEM_IMPORT_START'); ?>" onclick="document.getElementsByName('task')[0].value='import.csvcategoriesimport';return true;"/>
					<span id="upload-clear"></span>
				</td>
			</tr>
			<tr>
				<td>
					<label for="replace_categories">
						<?php echo JText::_('COM_JEM_IMPORT_REPLACEIFEXISTS').':'; ?>
					</label>
				</td>
				<td>
					<?php echo JHTML::_('select.booleanlist', 'replace_categories', 'class="inputbox"', 0); ?>
				</td>
			</tr>
		</table>
	</fieldset>

	<fieldset>
		<legend><?php echo JText::_('COM_JEM_IMPORT_CAT_EVENTS'); ?></legend>
		<?php echo JText::_('COM_JEM_IMPORT_INSTRUCTIONS') ?>
		<ul>
			<li>
				<?php echo JText::_("COM_JEM_IMPORT_COLUMNNAMESCATEGORIES"); ?>
			</li>
			<li>
				<?php echo JText::_("COM_JEM_IMPORT_FIRSTROW"); ?>
			</li>
			<li>
				<?php echo JText::_("COM_JEM_IMPORT_CSVFORMAT"); ?>
			</li>
			<li>
				<?php echo JText::_("COM_JEM_IMPORT_POSSIBLECOLUMNS").implode(", ",$this->cateventsfields); ?>
			</li>
		</ul>
		<table>
			<tr>
				<td>
					<label for="file">
						<?php echo JText::_('COM_JEM_IMPORT_SELECTCSV').':'; ?>
					</label>
				</td>
				<td>
					<input type="file" id="catevents-file-upload" accept="text/*" name="Filecatevents" />
					<input type="submit" id="catevents-file-upload-submit" value="<?php echo JText::_('COM_JEM_IMPORT_START'); ?>" onclick="document.getElementsByName('task')[0].value='import.csvcateventsimport';return true;"/>
					<span id="upload-clear"></span>
				</td>
			</tr>
			<tr>
				<td>
					<label for="replace_catevents">
						<?php echo JText::_('COM_JEM_IMPORT_REPLACEIFEXISTS').':'; ?>
					</label>
				</td>
				<td>
					<?php echo JHTML::_('select.booleanlist', 'replace_catevents', 'class="inputbox"', 0); ?>
				</td>
			</tr>
		</table>
	</fieldset>

	<input type="hidden" name="option" value="com_jem" />
	<input type="hidden" name="view" value="import" />
	<input type="hidden" name="controller" value="import" />
	<input type="hidden" name="task" value="" />
</form>



<?php echo JHtml::_('tabs.end'); ?>

