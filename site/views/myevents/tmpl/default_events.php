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
<h2><?php echo Text::_('COM_JEM_MY_EVENTS'); ?></h2>
<?php endif; ?>

<form action="<?php echo htmlspecialchars($this->action); ?>" method="post" id="adminForm" name="adminForm">
	<?php if ($this->settings->get('global_show_filter',1) || $this->settings->get('global_display',1)) : ?>
	<div id="jem_filter" class="floattext">
		<?php if ($this->settings->get('global_show_filter',1)) : ?>
		<div class="jem_fleft">
			<label for="filter"><?php echo Text::_('COM_JEM_FILTER'); ?></label>
			<?php echo $this->lists['filter'].'&nbsp;'; ?>
			<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search'];?>" class="inputbox" onchange="document.adminForm.submit();" />
			<button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
			<button class="btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
		</div>
		<?php endif; ?>

		<?php if ($this->settings->get('global_display',1)) : ?>
		<div class="jem_fright">
			<label for="limit"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>
			<?php echo $this->events_pagination->getLimitBox(); ?>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="table-responsive">
		<table class="eventtable table table-striped" style="width:<?php echo $this->jemsettings->tablewidth; ?>;" summary="jem">
			<colgroup>
				<?php if (empty($this->print) && !empty($this->permissions->canPublishEvent)) : ?>
				<col width="1%" class="jem_col_checkall" />
				<?php endif; ?>
				<col width="<?php echo $this->jemsettings->datewidth; ?>" class="jem_col_date" />
				<?php if ($this->jemsettings->showtitle == 1) : ?>
				<col width="<?php echo $this->jemsettings->titlewidth; ?>" class="jem_col_title" />
				<?php endif; ?>
				<?php if ($this->jemsettings->showlocate == 1) : ?>
				<col width="<?php echo $this->jemsettings->locationwidth; ?>" class="jem_col_venue" />
				<?php endif; ?>
				<?php if ($this->jemsettings->showcity == 1) : ?>
				<col width="<?php echo $this->jemsettings->citywidth; ?>" class="jem_col_city" />
				<?php endif; ?>
				<?php if ($this->jemsettings->showstate == 1) : ?>
				<col width="<?php echo $this->jemsettings->statewidth; ?>" class="jem_col_state" />
				<?php endif; ?>
				<?php if ($this->jemsettings->showcat == 1) : ?>
				<col width="<?php echo $this->jemsettings->catfrowidth; ?>" class="jem_col_category" />
				<?php endif; ?>
				<?php if ($this->params->get('displayattendeecolumn') == 1) : ?>
				<col width="<?php echo $this->jemsettings->attewidth; ?>" class="jem_col_atte" />
				<?php endif; ?>
				<col width="1%" class="jem_col_status" />
			</colgroup>

			<thead>
				<tr>
					<?php if (empty($this->print) && !empty($this->permissions->canPublishEvent)) : ?>
					<th class="sectiontableheader center"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
					<?php endif; ?>
					<th id="jem_date" class="sectiontableheader" align="left"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_DATE', 'a.dates', $this->lists['order_Dir'], $this->lists['order']); ?></th>
					<?php if ($this->jemsettings->showtitle == 1) : ?>
					<th id="jem_title" class="sectiontableheader" align="left"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_TITLE', 'a.title', $this->lists['order_Dir'], $this->lists['order']); ?></th>
					<?php endif; ?>
					<?php if ($this->jemsettings->showlocate == 1) : ?>
					<th id="jem_location" class="sectiontableheader" align="left"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'l.venue', $this->lists['order_Dir'], $this->lists['order']); ?></th>
					<?php endif; ?>
					<?php if ($this->jemsettings->showcity == 1) : ?>
					<th id="jem_city" class="sectiontableheader" align="left"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order']); ?></th>
					<?php endif; ?>
					<?php if ($this->jemsettings->showstate == 1) : ?>
					<th id="jem_state" class="sectiontableheader" align="left"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_STATE', 'l.state', $this->lists['order_Dir'], $this->lists['order']); ?></th>
					<?php endif; ?>
					<?php if ($this->jemsettings->showcat == 1) : ?>
					<th id="jem_category" class="sectiontableheader" align="left"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CATEGORY', 'c.catname', $this->lists['order_Dir'], $this->lists['order']); ?></th>
					<?php endif; ?>
					<?php if ($this->params->get('displayattendeecolumn') == 1) : ?>
					<th id="jem_atte" class="sectiontableheader" align="center"><?php echo Text::_('COM_JEM_TABLE_ATTENDEES'); ?></th>
					<?php endif; ?>
					<th id="jem_status" class="sectiontableheader center" nowrap="nowrap"><?php echo Text::_('JSTATUS'); ?></th>
				</tr>
			</thead>

			<tbody>
				<?php if (empty($this->events)) : ?>
					<tr class="no_events"><td colspan="20"><?php echo Text::_('COM_JEM_NO_EVENTS'); ?></td></tr>
				<?php else : ?>
					<?php foreach ($this->events as $i => $row) : ?>
						<tr class="row<?php echo $i % 2; ?>">

							<?php if (empty($this->print) && !empty($this->permissions->canPublishEvent)) : ?>
							<td class="center">
								<?php
								if (!empty($row->params) && $row->params->get('access-change', false)) :
									echo HTMLHelper::_('grid.id', $i, $row->eventid);
								endif;
								?>
							</td>
							<?php endif; ?>

							<td headers="jem_date" align="left">
								<?php echo JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $this->jemsettings->showtime); ?>
							</td>

							<?php if (($this->jemsettings->showtitle == 1) && ($this->jemsettings->showdetails == 1)) : ?>
							<td headers="jem_title" align="left" valign="top">
								<a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>">
									<?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row); ?>
								</a>
							</td>
							<?php endif; ?>

							<?php if (($this->jemsettings->showtitle == 1) && ($this->jemsettings->showdetails == 0)) : ?>
							<td headers="jem_title" align="left" valign="top">
								<?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row); ?>
							</td>
							<?php endif; ?>

							<?php if ($this->jemsettings->showlocate == 1) : ?>
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

							<?php if ($this->jemsettings->showcat == 1) : ?>
							<td headers="jem_category" align="left" valign="top">
								<?php echo implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist)); ?>
							</td>
							<?php endif; ?>

							<?php if ($this->params->get('displayattendeecolumn') == 1) : ?>
							<td headers="jem_atte" align="center" valign="top">
								<?php
								if ($this->jemsettings->showfroregistra || ($row->registra & 1)) {
									$linkreg  = 'index.php?option=com_jem&amp;view=attendees&amp;id='.$row->id.'&Itemid='.$this->itemid;
									$count = $row->regCount;
									if ($row->maxplaces)
									{
										$count .= '/'.$row->maxplaces;
										if ($row->waitinglist && $row->waiting) {
											$count .= ' + '.$row->waiting;
										}
									}
									if (!empty($row->unregCount)) {
										$count .= ' - '.(int)$row->unregCount;
									}
									if (!empty($row->invited)) {
										$count .= ', '.(int)$row->invited .' ?';
									}

									if (!empty($row->regTotal) || empty($row->finished)) {
									?>
									<a href="<?php echo $linkreg; ?>" title="<?php echo Text::_('COM_JEM_MYEVENT_MANAGEATTENDEES'); ?>">
										<?php echo $count; ?>
									</a>
									<?php
									} else {
										echo $count;
									}
								} else {
									echo HTMLHelper::_('image', 'com_jem/publish_r.png',NULL,NULL,true);
								}
								?>
							</td>
							<?php endif; ?>

							<td class="center">
								<?php // Ensure icon is not clickable if user isn't allowed to change state!
								$enabled = empty($this->print) && !empty($row->params) && $row->params->get('access-change', false);
								echo HTMLHelper::_('jgrid.published', $row->published, $i, 'myevents.', $enabled);
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
	<input type="hidden" name="enableemailaddress" value="<?php echo $this->enableemailaddress; ?>" />
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="task" value="<?php echo $this->task; ?>" />
	<input type="hidden" name="option" value="com_jem" />
	<?php echo HTMLHelper::_('form.token'); ?>
</form>

<div class="pagination">
	<?php echo $this->events_pagination->getPagesLinks(); ?>
</div>
