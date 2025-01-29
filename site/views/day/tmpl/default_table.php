<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
?>

<script>
	function tableOrdering(order, dir, view)
	{
		var form = document.getElementById("adminForm");

		form.filter_order.value     = order;
		form.filter_order_Dir.value = dir;
		form.submit(view);
	}
</script>

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
		<?php echo $this->pagination->getLimitBox(); ?>
	</div>
	<?php endif; ?>
</div>
<?php endif; ?>

<div class="table-responsive">
	<table class="eventtable table table-striped" style="width:<?php echo $this->jemsettings->tablewidth; ?>;" summary="jem">
		<colgroup>
			<col style="width: <?php echo $this->jemsettings->datewidth; ?>" class="jem_col_date" />
			<?php if ($this->jemsettings->showtitle == 1) : ?>
			<col style="width: <?php echo $this->jemsettings->titlewidth; ?>" class="jem_col_title" />
			<?php endif; ?>
			<?php if ($this->jemsettings->showlocate == 1) : ?>
			<col style="width: <?php echo $this->jemsettings->locationwidth; ?>" class="jem_col_venue" />
			<?php endif; ?>
			<?php if ($this->jemsettings->showcity == 1) : ?>
			<col style="width: <?php echo $this->jemsettings->citywidth; ?>" class="jem_col_city" />
			<?php endif; ?>
			<?php if ($this->jemsettings->showstate == 1) : ?>
			<col style="width: <?php echo $this->jemsettings->statewidth; ?>" class="jem_col_state" />
			<?php endif; ?>
			<?php if ($this->jemsettings->showcat == 1) : ?>
			<col style="width: <?php echo $this->jemsettings->catfrowidth; ?>" class="jem_col_category" />
			<?php endif; ?>
		</colgroup>

		<thead>
			<tr>
				<th id="jem_date" class="sectiontableheader" style="text-align: left;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_DATE', 'a.dates', $this->lists['order_Dir'], $this->lists['order']); ?></th>
				<?php if ($this->jemsettings->showtitle == 1) : ?>
				<th id="jem_title" class="sectiontableheader" style="text-align: left;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_TITLE', 'a.title', $this->lists['order_Dir'], $this->lists['order']); ?></th>
				<?php endif; ?>
				<?php if ($this->jemsettings->showlocate == 1) : ?>
				<th id="jem_location" class="sectiontableheader" style="text-align: left;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'l.venue', $this->lists['order_Dir'], $this->lists['order']); ?></th>
				<?php endif; ?>
				<?php if ($this->jemsettings->showcity == 1) : ?>
				<th id="jem_city" class="sectiontableheader" style="text-align: left;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order']); ?></th>
				<?php endif; ?>
				<?php if ($this->jemsettings->showstate == 1) : ?>
				<th id="jem_state" class="sectiontableheader" style="text-align: left;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_STATE', 'l.state', $this->lists['order_Dir'], $this->lists['order']); ?></th>
				<?php endif; ?>
				<?php if ($this->jemsettings->showcat == 1) : ?>
				<th id="jem_category" class="sectiontableheader" style="text-align: left;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CATEGORY', 'c.catname', $this->lists['order_Dir'], $this->lists['order']); ?></th>
				<?php endif; ?>
			</tr>
		</thead>

		<tbody>
			<?php if (empty($this->rows)) : ?>
				<tr class="no_events"><td colspan="20"><?php echo Text::_('COM_JEM_NO_EVENTS'); ?></td></tr>
			<?php else : ?>
				<?php $odd = 0; ?>
				<?php foreach ($this->rows as $row) : ?>
					<?php $odd = 1 - $odd; ?>
					<?php if (!empty($row->featured)) : ?>
					<tr class="featured featured<?php echo $row->id.$this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
					<?php else : ?>
					<tr class="sectiontableentry<?php echo ($odd + 1) . $this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
					<?php endif; ?>

						<td headers="jem_date" style="text-align: left;">
							<?php
								echo JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $this->jemsettings->showtime);
								echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes);
							?>
						</td>

						<?php if (($this->jemsettings->showtitle == 1) && ($this->jemsettings->showdetails == 1)) : ?>
						<td headers="jem_title" style="text-align: left; vertical-align: top;">
							<a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" itemprop="url">
								<span itemprop="name"><?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row); ?></span>
							</a><?php echo JemOutput::publishstateicon($row); ?>
						</td>
						<?php endif; ?>

						<?php if (($this->jemsettings->showtitle == 1) && ($this->jemsettings->showdetails == 0)) : ?>
						<td headers="jem_title" style="text-align: left; vertical-align: top;" itemprop="name">
							<?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row) . JemOutput::publishstateicon($row); ?>
						</td>
						<?php endif; ?>

						<?php if ($this->jemsettings->showlocate == 1) : ?>
						<td headers="jem_location" style="text-align: left; vertical-align: top;">
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
						<td headers="jem_city" style="text-align: left; vertical-align: top;">
							<?php echo !empty($row->city) ? $this->escape($row->city) : '-'; ?>
						</td>
						<?php endif; ?>

						<?php if ($this->jemsettings->showstate == 1) : ?>
						<td headers="jem_state" style="text-align: left; vertical-align: top;">
							<?php echo !empty($row->state) ? $this->escape($row->state) : '-'; ?>
						</td>
						<?php endif; ?>

						<?php if ($this->jemsettings->showcat == 1) : ?>
						<td headers="jem_category" style="text-align: left; vertical-align: top;">
							<?php echo implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist)); ?>
						</td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
