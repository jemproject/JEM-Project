<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
// JEMHelper::headerDeclarations();
?>

<form action="index.php" method="post" name="adminForm" enctype="multipart/form-data" id="adminForm">
	<fieldset>
		<legend><?php echo JText::_('COM_JEM_EXPORT_EVENTS'); ?></legend>
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
					<label for="replace_events">
						<?php echo JText::_('').':'; ?>
					</label>
				</td>
				<td>
					<div class="button2-left">
						<div class="blank">
							<a title="<?php echo JText::_('COM_JEM_CSV_EXPORT'); ?>" onclick="window.open('index.php?option=com_jem&task=export&controller=export')"><?php echo JText::_('COM_JEM_EXPORT_EVENTS'); ?></a>
						</div>
					</div>
				</td>
			</tr>
		</table>
	</fieldset>

	<fieldset>
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
							<a title="<?php echo JText::_('COM_JEM_CSV_EXPORT'); ?>" onclick="window.open('index.php?option=com_jem&task=exportcats&controller=export')"><?php echo JText::_('COM_JEM_EXPORT_CATEGORIES'); ?></a>
						</div>
					</div>
				</td>
			</tr>
		</table>
	</fieldset>
	
	<fieldset>
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
							<a title="<?php echo JText::_('COM_JEM_CSV_EXPORT'); ?>" onclick="window.open('index.php?option=com_jem&task=exportvenues&controller=export')"><?php echo JText::_('COM_JEM_EXPORT_VENUES'); ?></a>
						</div>
					</div>
				</td>
			</tr>
		</table>
	</fieldset>
	
	<fieldset>
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
							<a title="<?php echo JText::_('COM_JEM_CSV_EXPORT'); ?>" onclick="window.open('index.php?option=com_jem&task=exportcatevents&controller=export')"><?php echo JText::_('COM_JEM_EXPORT_CAT_EVENTS'); ?></a>
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

<p class="copyright">
	<?php echo JEMAdmin::footer(); ?>
</p>