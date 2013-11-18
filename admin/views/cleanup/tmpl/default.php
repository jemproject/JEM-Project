<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>
<form name="adminForm" method="post" id="adminForm">

<table style="width:100%">
	<tr>
		<!-- CLEAN EVENT IMG -->
		<td width="10%">
			<div class="linkicon">
				<a href="index.php?option=com_jem&amp;task=cleanup.cleaneventimg">
					<?php echo JHtml::_('image', 'com_jem/icon-48-cleaneventimg.png', JText::_('COM_JEM_CLEANUP_EVENT_IMG'), NULL, true); ?>
					<span><?php echo JText::_('COM_JEM_CLEANUP_EVENT_IMG'); ?></span>
				</a>
			</div>
		</td>
		<td width="40%" valign="middle">
			<?php echo JText::_('COM_JEM_CLEANUP_EVENT_IMG_DESC'); ?>
		</td>

		<!-- CLEAN VENUE IMG -->
		<td width="10%">
			<div class="linkicon">
				<a href="index.php?option=com_jem&amp;task=cleanup.cleanvenueimg">
					<?php echo JHtml::_('image', 'com_jem/icon-48-cleanvenueimg.png', JText::_('COM_JEM_CLEANUP_VENUE_IMG'), NULL, true); ?>
					<span><?php echo JText::_('COM_JEM_CLEANUP_VENUE_IMG'); ?></span>
				</a>
			</div>
		</td>
		<td width="40%" valign="middle">
			<?php echo JText::_('COM_JEM_CLEANUP_VENUE_IMG_DESC'); ?>
		</td>
	</tr>
	<tr>
		<!-- CLEAN CATEGORY IMG -->
		<td width="10%">
			<div class="linkicon">
				<a href="index.php?option=com_jem&amp;task=cleanup.cleancategoryimg">
					<?php echo JHtml::_('image', 'com_jem/icon-48-cleancategoryimg.png', JText::_('COM_JEM_CLEANUP_CATEGORY_IMG'), NULL, true); ?>
					<span><?php echo JText::_('COM_JEM_CLEANUP_CATEGORY_IMG'); ?></span>
				</a>
			</div>
		</td>
		<td width="40%" valign="middle">
			<?php echo JText::_('COM_JEM_CLEANUP_CATEGORY_IMG_DESC'); ?>
		</td>

		<!-- CLEAN TRIGGER ARCHIVE -->
		<td width="10%">
			<div class="linkicon">
				<a href="index.php?option=com_jem&amp;task=cleanup.triggerarchive">
					<?php echo JHtml::_('image', 'com_jem/icon-48-archive.png', JText::_('COM_JEM_CLEANUP_TRIGGER_AUTOARCHIVE'), NULL, true); ?>
					<span><?php echo JText::_('COM_JEM_CLEANUP_TRIGGER_AUTOARCHIVE'); ?></span>
				</a>
			</div>
		</td>
		<td width="40%" valign="middle">
			<?php echo JText::_('COM_JEM_CLEANUP_TRIGGER_AUTOARCHIVE_DESC'); ?>
		</td>
	</tr>
	<tr>
		<!-- TRUNCATE CATEGORY/EVENT REFERENCES -->
		<td width="10%">
			<div class="linkicon">
				<a href="index.php?option=com_jem&amp;task=cleanup.cleanupCatsEventRelations">
					<?php echo JHtml::_('image', 'com_jem/icon-48-cleancategoryimg.png', JText::_('COM_JEM_CLEANUP_CATSEVENT_RELS'), NULL, true); ?>
					<span><?php echo JText::_('COM_JEM_CLEANUP_CLEANUP_CATSEVENT_RELS'); ?></span>
				</a>
			</div>
		</td>
		<td width="40%" valign="middle">
			<?php echo JText::_('COM_JEM_CLEANUP_CLEANUP_CATSEVENT_RELS_DESC'); ?><br/>
			<?php echo JText::sprintf('COM_JEM_CLEANUP_TOTAL_CATSEVENT_RELS', $this->totalcats) ?>
		</td>

		<!-- TRUNCATE ALL DATA -->
		<td width="10%">
			<div class="linkicon">
				<a href="index.php?option=com_jem&amp;task=cleanup.truncateAllData" onclick="javascript:return confirm('<?php echo JText::_('COM_JEM_CLEANUP_TRUNCATE_ALL_DATA_CONFIRM'); ?>');">
					<?php echo JHtml::_('image', 'com_jem/icon-48-truncatealldata.png', JText::_('COM_JEM_CLEANUP_TRUNCATE_ALL_DATA'), NULL, true); ?>
					<span><?php echo JText::_('COM_JEM_CLEANUP_TRUNCATE_ALL_DATA'); ?></span>
				</a>
			</div>
		</td>
		<td width="40%" valign="middle">
			<?php echo JText::_('COM_JEM_CLEANUP_TRUNCATE_ALL_DATA_DESC'); ?>
		</td>
	</tr>
</table>
</form>