<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
// JEMHelper::headerDeclarations();
?>

<script type="text/javascript">
    function selectAll()
    {
        selectBox = document.getElementById("cid");

        for (var i = 0; i < selectBox.options.length; i++)
        {
             selectBox.options[i].selected = true;
        }
    }


    function unselectAll()
    {
        selectBox = document.getElementById("cid");

        for (var i = 0; i < selectBox.options.length; i++)
        {
             selectBox.options[i].selected = false;
        }
    }

</script>


<div id="jem" class="jem_jem">
<form action="index.php" method="post" name="adminForm" enctype="multipart/form-data" id="adminForm">
	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_EXPORT_EVENTS'); ?></legend>
		<table>
			<tr>
				<td>
				</td>
				<td></td>
			</tr>
			<tr>
				<td>
				<span class="editlinktip hasTip" title="<?php echo JText::_('COM_JEM_EXPORT_ADD_CATEGORYCOLUMN'); ?>::<?php echo JText::_('COM_JEM_EXPORT_ADD_CATEGORYCOLUMN'); ?>">
						<?php echo JText::_('COM_JEM_EXPORT_ADD_CATEGORYCOLUMN'); ?>
					</span>
				</td>
				<td>
				<?php
				$categorycolumn = array();
				$categorycolumn[] = JHTML::_('select.option', '0', JText::_( 'JNO' ) );
				$categorycolumn[] = JHTML::_('select.option', '1', JText::_( 'JYES' ) );
				$categorycolumn = JHTML::_('select.genericlist', $categorycolumn, 'categorycolumn', 'size="1" class="inputbox"', 'value', 'text', '1');
				echo $categorycolumn;
				?>
				</td>
			</tr>
			<tr>
				<td>
				<label for="dates">
					<?php echo JText::_( 'COM_JEM_DATE' ).':'; ?>
				</label>
				</td>
				<td>
				<?php echo JHTML::_('calendar', date("Y-m-d"), 'dates', 'dates', '%Y-%m-%d', array('class' => 'inputbox validate-date')); ?>
			</td>
			</tr>
			<tr>
				<td>
				<label for="enddates">
					<?php echo JText::_( 'COM_JEM_ENDDATE' ).':'; ?>
				</label>
				</td>
				<td>
				<?php echo JHTML::_('calendar', date("Y-m-d"), 'enddates', 'enddates', '%Y-%m-%d', array('class' => 'inputbox validate-date')); ?>
			</td>
			</tr>
			<tr>
				<td>
				<label for="cid">
					<?php echo JText::_( 'COM_JEM_CATEGORY' ).':'; ?>
				</label>
				</td>
				<td>
				<?php echo $this->categories; ?>
				<input class="button" name="selectall" value=selectallcategories onclick="selectAll();">
				<br>
				<input class="button" name="unselectall" value=unselectallcategories onclick="unselectAll();">
			</td>
			</tr>
			<tr>
				<td>
				</td>
				<td>
				<input class="button" type="submit" value="<?php echo JText::_('COM_JEM_CSV_EXPORT'); ?>" onclick="document.getElementsByName('task')[0].value='export.export';return true;" />
				</td>
			</tr>
		</table>
	</fieldset>

	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_EXPORT_CATEGORIES'); ?></legend>
		<table>
			<tr>
				<td>
					<label for="file">
						<?php echo JText::_('').':'; ?>
					</label>
				</td>
				<td></td>
			</tr>
			<tr>
				<td>
					<label for="replace_cats">
						<?php echo JText::_('').':'; ?>
					</label>
				</td>
				<td>
					<div class="button2-left">
						<div class="blank">
							<a title="<?php echo JText::_('COM_JEM_CSV_EXPORT'); ?>" onclick="window.open('index.php?option=com_jem&task=export.exportcats')"><?php echo JText::_('COM_JEM_EXPORT_BUTTON'); ?></a>
						</div>
					</div>
				</td>
			</tr>
		</table>
	</fieldset>

	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_EXPORT_VENUES'); ?></legend>
		<table>
			<tr>
				<td>
					<label for="file">
						<?php echo JText::_('').':'; ?>
					</label>
				</td>
				<td></td>
			</tr>
			<tr>
				<td>
					<label for="replace_venues">
						<?php echo JText::_('').':'; ?>
					</label>
				</td>
				<td>
					<div class="button2-left">
						<div class="blank">
							<a title="<?php echo JText::_('COM_JEM_CSV_EXPORT'); ?>" onclick="window.open('index.php?option=com_jem&task=export.exportvenues')"><?php echo JText::_('COM_JEM_EXPORT_BUTTON'); ?></a>
						</div>
					</div>
				</td>
			</tr>
		</table>
	</fieldset>

	<fieldset class="adminform">
		<legend><?php echo JText::_('COM_JEM_EXPORT_CAT_EVENTS'); ?></legend>
		<table>
			<tr>
				<td>
					<label for="file">
						<?php echo JText::_('').':'; ?>
					</label>
				</td>
				<td></td>
			</tr>
			<tr>
				<td>
					<label for="replace_catevents">
						<?php echo JText::_('').':'; ?>
					</label>
				</td>
				<td>
					<div class="button2-left">
						<div class="blank">
							<a title="<?php echo JText::_('COM_JEM_CSV_EXPORT'); ?>" onclick="window.open('index.php?option=com_jem&task=export.exportcatevents')"><?php echo JText::_('COM_JEM_EXPORT_BUTTON'); ?></a>
						</div>
					</div>
				</td>
			</tr>
		</table>
	</fieldset>

	<input type="hidden" name="option" value="com_jem" />
	<input type="hidden" name="view" value="export" />
	<input type="hidden" name="controller" value="export" />
	<input type="hidden" name="task" value="" />
</form>
</div>
