<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
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

<div id="jem_filter" class="floattext">
	<div class="jem_fleft">
		<table>
			<tr>
				<td>
					<label for="filter_type"><?php echo Text::_('COM_JEM_FILTER');  ?></label>
				</td>
				<td>
					<?php echo $this->lists['filter_types']; ?>
					<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['filter'];?>" class="inputbox" onchange="document.getElementById('adminForm').submit();" />
					<button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
					<button class="btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
				</td>
			</tr>
			<tr>
				<td>
					<?php echo '<label for="category">'.Text::_('COM_JEM_CATEGORY').'</label>&nbsp;'; ?>
				</td>
				<td>
					<?php echo $this->lists['categories']; ?>
				</td>
			</tr>
			<tr>
				<td>
					<?php echo '<label for="date">'.Text::_('COM_JEM_SEARCH_DATE').'</label>&nbsp;'; ?>
				</td>
				<td>
					<div class="nowrap"><?php echo text::_('COM_JEM_SEARCH_FROM'); ?><?php echo $this->lists['date_from'];?></div>
					<div class="nowrap"><?php echo text::_('COM_JEM_SEARCH_TO'); ?><?php echo $this->lists['date_to'];?></div>
				</td>
			</tr>
			<tr>
				<td>
					<?php echo '<label for="continent">'.Text::_('COM_JEM_CONTINENT').'</label>&nbsp;'; ?>
				</td>
				<td>
					<?php echo $this->lists['continents'];?>
				</td>
			</tr>
			<?php if ($this->filter_continent): ?>
			<tr>
				<td>
					<?php echo '<label for="country">'.Text::_('COM_JEM_COUNTRY').'</label>&nbsp;'; ?>
				</td>
				<td>
					<?php echo $this->lists['countries'];?>
				</td>
			</tr>
			<?php endif; ?>
			<?php if ($this->filter_continent && $this->filter_country): ?>
			<tr>
				<td>
					<?php echo '<label for="city">'.Text::_('COM_JEM_CITY').'</label>&nbsp;';?>
				</td>
				<td>
					<?php echo $this->lists['cities'];?>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<td colspan="2">
					<input class="btn btn-primary" type="submit" value="<?php echo Text::_('COM_JEM_SEARCH_SUBMIT'); ?>"/>
				</td>
			</tr>
		</table>
			<div class="jem_fright">
		<label for="limit"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>
		<?php echo $this->pagination->getLimitBox(); ?>
	</div>
	</div>


</div>

<div class="table-responsive">
	<table class="eventtable table table-striped" style="width:<?php echo $this->jemsettings->tablewidth; ?>;" summary="jem">
		<colgroup>
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
		</colgroup>

		<thead>
			<tr>
				<th id="jem_date" class="sectiontableheader" align="left"><?php echo JHtml::_('grid.sort', 'COM_JEM_TABLE_DATE', 'a.dates', $this->lists['order_Dir'], $this->lists['order']); ?></th>
				<?php if ($this->jemsettings->showtitle == 1) : ?>
				<th id="jem_title" class="sectiontableheader" align="left"><?php echo JHtml::_('grid.sort', 'COM_JEM_TABLE_TITLE', 'a.title', $this->lists['order_Dir'], $this->lists['order']); ?></th>
				<?php endif; ?>
				<?php if ($this->jemsettings->showlocate == 1) : ?>
				<th id="jem_location" class="sectiontableheader" align="left"><?php echo JHtml::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'l.venue', $this->lists['order_Dir'], $this->lists['order']); ?></th>
				<?php endif; ?>
				<?php if ($this->jemsettings->showcity == 1) : ?>
				<th id="jem_city" class="sectiontableheader" align="left"><?php echo JHtml::_('grid.sort', 'COM_JEM_TABLE_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order']); ?></th>
				<?php endif; ?>
				<?php if ($this->jemsettings->showstate == 1) : ?>
				<th id="jem_state" class="sectiontableheader" align="left"><?php echo JHtml::_('grid.sort', 'COM_JEM_TABLE_STATE', 'l.state', $this->lists['order_Dir'], $this->lists['order']); ?></th>
				<?php endif; ?>
				<?php if ($this->jemsettings->showcat == 1) : ?>
				<th id="jem_category" class="sectiontableheader" align="left"><?php echo JHtml::_('grid.sort', 'COM_JEM_TABLE_CATEGORY', 'c.catname', $this->lists['order_Dir'], $this->lists['order']); ?></th>
				<?php endif; ?>
			</tr>
		</thead>

		<tbody>
			<?php if (empty($this->rows)) : ?>
				<tr class="no_events"><td colspan="20"><?php echo Text::_('COM_JEM_NO_EVENTS_FOUND'); ?></td></tr>
			<?php else : ?>
				<?php $odd = 0; ?>
				<?php foreach ($this->rows as $row) : ?>
					<?php $odd = 1 - $odd; ?>
					<?php if (!empty($row->featured)) : ?>
					<tr class="featured featured<?php echo $row->id.$this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
					<?php else : ?>
					<tr class="sectiontableentry<?php echo ($odd + 1) . $this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
					<?php endif; ?>

						<td headers="jem_date" align="left">
							<?php
							echo JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $this->jemsettings->showtime);
							echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes);
							?>
						</td>

						<?php if (($this->jemsettings->showtitle == 1) && ($this->jemsettings->showdetails == 1)) : ?>
						<td headers="jem_title" align="left" valign="top">
							<a href="<?php echo JRoute::_(JemHelperRoute::getEventRoute($row->slug)); ?>" itemprop="url">
								<span itemprop="name"><?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row); ?></span>
							</a><?php echo JemOutput::publishstateicon($row); ?>
						</td>
						<?php endif; ?>

						<?php if (($this->jemsettings->showtitle == 1) && ($this->jemsettings->showdetails == 0)) : ?>
						<td headers="jem_title" align="left" valign="top" itemprop="name">
							<?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row) . JemOutput::publishstateicon($row); ?>
						</td>
						<?php endif; ?>

						<?php if ($this->jemsettings->showlocate == 1) : ?>
						<td headers="jem_location" align="left" valign="top">
							<?php
							if (!empty($row->venue)) :
								if (($this->jemsettings->showlinkvenue == 1) && !empty($row->venueslug)) :
									echo "<a href='".JRoute::_(JemHelperRoute::getVenueRoute($row->venueslug))."'>".$this->escape($row->venue)."</a>";
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
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
