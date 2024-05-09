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
use Joomla\CMS\Router\Route;

?>

<script type="text/javascript">
	function tableOrdering(order, dir, view)
	{
		var form = document.getElementById("adminForm");

		form.filter_order.value     = order;
		form.filter_order_Dir.value = dir;
		form.submit(view);
	}
</script>

<?php if (!$this->params->get('show_page_heading', 1)) : /* hide this if page heading is shown */ ?>
<h2><?php echo Text::_('COM_JEM_MY_VENUES'); ?></h2>
<?php endif; ?>

<form action="<?php echo htmlspecialchars($this->action); ?>" method="post" id="adminForm" name="adminForm">
	<?php if ($this->settings->get('global_show_filter',1) || $this->settings->get('global_display',1)) : ?>
	<div id="jem_filter" class="floattext">
		<?php if ($this->settings->get('global_show_filter',1)) : ?>
		<div class="jem_fleft">
			<label for="filter"><?php echo Text::_('COM_JEM_FILTER'); ?></label>
			<?php echo $this->lists['filter'].'&nbsp;'; ?>
			<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search'];?>" class="inputbox" onchange="document.adminForm.submit();" />
			<button class="buttonfilter btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
			<button class="buttonfilter btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
		</div>
		<?php endif; ?>

		<?php if ($this->settings->get('global_display',1)) : ?>
		<div class="jem_fright">
			<label for="limit"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>
			<?php echo $this->venues_pagination->getLimitBox(); ?>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="table-responsive">
		<table class="eventtable" style="width:<?php echo $this->jemsettings->tablewidth; ?>;" summary="Venues">
			<colgroup>
				<?php if (empty($this->print) && !empty($this->permissions->canPublishVenue)) : ?>
				<col width="1%" class="jem_col_checkall" />
				<?php endif; ?>
				<?php if (/*$this->jemsettings->showlocate ==*/ 1) : ?>
				<col width="<?php echo $this->jemsettings->locationwidth; ?>" class="jem_col_venue" />
				<?php endif; ?>
				<?php if ($this->jemsettings->showcity == 1) : ?>
				<col width="<?php echo $this->jemsettings->citywidth; ?>" class="jem_col_city" />
				<?php endif; ?>
				<?php if ($this->jemsettings->showstate == 1) : ?>
				<col width="<?php echo $this->jemsettings->statewidth; ?>" class="jem_col_state" />
				<?php endif; ?>
				<col width="1%" class="jem_col_status" />
			</colgroup>

			<thead>
				<tr>
					<?php if (empty($this->print) && !empty($this->permissions->canPublishVenue)) : ?>
					<th class="sectiontableheader center"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
					<?php endif; ?>
					<?php if (/*$this->jemsettings->showlocate ==*/ 1) : ?>
					<th id="jem_location" class="sectiontableheader" align="left"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'l.venue', $this->lists['order_Dir'], $this->lists['order']); ?></th>
					<?php endif; ?>
					<?php if ($this->jemsettings->showcity == 1) : ?>
					<th id="jem_city" class="sectiontableheader" align="left"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order']); ?></th>
					<?php endif; ?>
					<?php if ($this->jemsettings->showstate == 1) : ?>
					<th id="jem_state" class="sectiontableheader" align="left"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_STATE', 'l.state', $this->lists['order_Dir'], $this->lists['order']); ?></th>
					<?php endif; ?>
					<th id="jem_status" class="sectiontableheader center" nowrap="nowrap"><?php echo Text::_('JSTATUS'); ?></th>
				</tr>
			</thead>

			<tbody>
				<?php if (empty($this->venues)) : ?>
					<tr class="no_events"><td colspan="20"><?php echo Text::_('COM_JEM_NO_VENUES'); ?></td></tr>
				<?php else : ?>
					<?php foreach ($this->venues as $i => $row) : ?>
						<tr class="row<?php echo $i % 2; ?>">

							<?php if (empty($this->print) && !empty($this->permissions->canPublishVenue)) : ?>
							<td class="center">
								<?php
								if (!empty($row->params) && $row->params->get('access-change', false)) :
									echo HTMLHelper::_('grid.id', $i, $row->id);
								endif;
								?>
							</td>
							<?php endif; ?>

							<?php if (/*$this->jemsettings->showlocate ==*/ 1) : ?>
							<td headers="jem_location" align="left" valign="top">
								<?php
								if (!empty($row->venue)) :
									if (($this->jemsettings->showlinkvenue == 1) && !empty($row->venueslug)) :
										echo "<a href='".Route::_(JemHelperRoute::getVenueRoute($row->venueslug))."'>".$this->escape($row->venue)."</a>";
									else :
										echo $this->escape($row->venue);
									endif;
								else :
									echo '-';
								endif;
								?>
							</td>
							<?php endif; ?>

							<?php if ($this->jemsettings->showcity == 1) : ?>
							<td headers="jem_city" align="left" valign="top">
								<?php echo !empty($row->city) ? $this->escape($row->city) : '-'; ?>
							</td>
							<?php endif; ?>

							<?php if ($this->jemsettings->showstate == 1) : ?>
							<td headers="jem_state" align="left" valign="top">
								<?php echo !empty($row->state) ? $this->escape($row->state) : '-'; ?>
							</td>
							<?php endif; ?>

							<td class="center">
								<?php // Ensure icon is not clickable if user isn't allowed to change state!
								$enabled = empty($this->print) && !empty($row->params) && $row->params->get('access-change', false);
								echo HTMLHelper::_('jgrid.published', $row->published, $i, 'myvenues.', $enabled);
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="task" value="<?php echo $this->task; ?>" />
	<input type="hidden" name="option" value="com_jem" />
	<?php echo HTMLHelper::_('form.token'); ?>
</form>

<div class="pagination">
	<?php echo $this->venues_pagination->getPagesLinks(); ?>
</div>
