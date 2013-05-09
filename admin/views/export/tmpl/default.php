<?php
/**
 * @version 1.9 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
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