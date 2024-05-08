<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;
?>
<form name="adminForm" method="post" id="adminForm">
	<?php if (isset($this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo $this->sidebar; ?>
	</div>
	<div id="j-main-container" class="span10">
	<?php endif; ?>
		<table class="table table-striped">
			<tbody>
				<!-- CLEAN EVENT IMG -->
				<tr>
					<td width="60px;">
						<div class="linkicon">
							<a href="index.php?option=com_jem&amp;task=housekeeping.cleaneventimg&amp;<?php echo Session::getFormToken(); ?>=1">
								<?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleaneventimg.png', Text::_('COM_JEM_HOUSEKEEPING_EVENT_IMG'), NULL, true); ?>
							</a>
						</div>
					</td>
					<td>
					<h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_EVENT_IMG'); ?></h3>
						<?php echo Text::_('COM_JEM_HOUSEKEEPING_EVENT_IMG_DESC'); ?>
					</td>
				</tr>
			<!-- CLEAN VENUE IMG -->
				<tr>
					<td width="60px;">
						<div class="linkicon">
							<a href="index.php?option=com_jem&amp;task=housekeeping.cleanvenueimg&amp;<?php echo Session::getFormToken(); ?>=1">
								<?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleanvenueimg.png', Text::_('COM_JEM_HOUSEKEEPING_VENUE_IMG'), NULL, true); ?>
							</a>
						</div>
					</td>
					<td>
					<h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_VENUE_IMG'); ?></h3>
						<?php echo Text::_('COM_JEM_HOUSEKEEPING_VENUE_IMG_DESC'); ?>
					</td>
				</tr>
			<!-- CLEAN CATEGORY IMG -->
				<tr>
					<td width="60px;">
						<div class="linkicon">
							<a href="index.php?option=com_jem&amp;task=housekeeping.cleancategoryimg&amp;<?php echo Session::getFormToken(); ?>=1">
								<?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleancategoryimg.png', Text::_('COM_JEM_HOUSEKEEPING_CATEGORY_IMG'), NULL, true); ?>
							</a>
						</div>
					</td>
					<td>
					<h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_CATEGORY_IMG'); ?></h3>
						<?php echo Text::_('COM_JEM_HOUSEKEEPING_CATEGORY_IMG_DESC'); ?>
					</td>
				</tr>
			<!-- CLEAN TRIGGER ARCHIVE -->
				<tr>
					<td width="60px;">
						<div class="linkicon">
							<a href="index.php?option=com_jem&amp;task=housekeeping.triggerarchive&amp;<?php echo Session::getFormToken(); ?>=1">
								<?php echo HTMLHelper::_('image', 'com_jem/icon-48-archive.png', Text::_('COM_JEM_HOUSEKEEPING_TRIGGER_AUTOARCHIVE'), NULL, true); ?>
							</a>
						</div>
					</td>
					<td>
					<h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_TRIGGER_AUTOARCHIVE'); ?></h3>
						<?php echo Text::_('COM_JEM_HOUSEKEEPING_TRIGGER_AUTOARCHIVE_DESC'); ?>
					</td>
				</tr>
			<!-- TRUNCATE CATEGORY/EVENT REFERENCES -->
				<tr>
					<td width="60px;">
						<div class="linkicon">
							<a href="index.php?option=com_jem&amp;task=housekeeping.cleanupCatsEventRelations&amp;<?php echo Session::getFormToken(); ?>=1">
								<?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleancategoryimg.png', Text::_('COM_JEM_HOUSEKEEPING_CATSEVENT_RELS'), NULL, true); ?>
							</a>
						</div>
					</td>
					<td>
					<h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_CLEANUP_CATSEVENT_RELS'); ?></h3>
						<?php echo Text::_('COM_JEM_HOUSEKEEPING_CLEANUP_CATSEVENT_RELS_DESC'); ?><br/>
						<?php echo Text::sprintf('COM_JEM_HOUSEKEEPING_TOTAL_CATSEVENT_RELS', $this->totalcats) ?>
					</td>
				</tr>
			<!-- TRUNCATE ALL DATA -->
				<tr>
					<td width="60px;">
						<div class="linkicon">
							<a href="index.php?option=com_jem&amp;task=housekeeping.truncateAllData&amp;<?php echo Session::getFormToken(); ?>=1" onclick="javascript:return confirm('<?php echo Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA_CONFIRM'); ?>');">
								<?php echo HTMLHelper::_('image', 'com_jem/icon-48-truncatealldata.png', Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA'), NULL, true); ?>
							</a>
						</div>
					</td>
					<td>
					<h3><?php echo Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA'); ?></h3>
						<?php echo Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA_DESC'); ?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php if (isset($this->sidebar)) : ?>
			</div>
		<?php endif; ?>
</form>
