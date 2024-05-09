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
use Joomla\CMS\Router\Route;
?>

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
				<th id="jem_date_cat<?php echo $this->catrow->id; ?>" class="sectiontableheader" align="left"><?php echo Text::_('COM_JEM_TABLE_DATE'); ?></th>
				<?php if ($this->jemsettings->showtitle == 1) : ?>
				<th id="jem_title_cat<?php echo $this->catrow->id; ?>" class="sectiontableheader" align="left"><?php echo Text::_('COM_JEM_TABLE_TITLE'); ?></th>
				<?php endif; ?>
				<?php if ($this->jemsettings->showlocate == 1) : ?>
				<th id="jem_location_cat<?php echo $this->catrow->id; ?>" class="sectiontableheader" align="left"><?php echo Text::_('COM_JEM_TABLE_LOCATION'); ?></th>
				<?php endif; ?>
				<?php if ($this->jemsettings->showcity == 1) : ?>
				<th id="jem_city_cat<?php echo $this->catrow->id; ?>" class="sectiontableheader" align="left"><?php echo Text::_('COM_JEM_TABLE_CITY'); ?></th>
				<?php endif; ?>
				<?php if ($this->jemsettings->showstate == 1) : ?>
				<th id="jem_state_cat<?php echo $this->catrow->id; ?>" class="sectiontableheader" align="left"><?php echo Text::_('COM_JEM_TABLE_STATE'); ?></th>
				<?php endif; ?>
				<?php if ($this->jemsettings->showcat == 1) : ?>
				<th id="jem_category_cat<?php echo $this->catrow->id; ?>" class="sectiontableheader" align="left"><?php echo Text::_('COM_JEM_TABLE_CATEGORY'); ?></th>
				<?php endif; ?>
			</tr>
		</thead>

		<tbody>
			<?php if (empty($this->catrow->events)) : ?>
				<tr class="no_events"><td colspan="20"><?php echo Text::_('COM_JEM_NO_EVENTS'); ?></td></tr>
			<?php else : ?>
				<?php $odd = 0; ?>
				<?php foreach ($this->catrow->events as $row) : ?>
					<?php if (!empty($row->featured)) : ?>
					<tr class="featured featured<?php echo $row->id.$this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
					<?php else : ?>
					<tr class="sectiontableentry<?php echo ($odd + 1) . $this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
					<?php endif; ?>

						<td headers="jem_date_cat<?php echo $this->catrow->id; ?>" align="left">
							<?php
								echo JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $this->jemsettings->showtime);
								echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes);
							?>
						</td>

						<?php if (($this->jemsettings->showtitle == 1) && ($this->jemsettings->showdetails == 1)) : ?>
						<td headers="jem_title_cat<?php echo $this->catrow->id; ?>" align="left" valign="top">
							<a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" itemprop="url">
								<span itemprop="name"><?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row); ?></span>
							</a><?php echo JemOutput::publishstateicon($row); ?>
						</td>
						<?php endif; ?>

						<?php if (($this->jemsettings->showtitle == 1) && ($this->jemsettings->showdetails == 0)) : ?>
						<td headers="jem_title_cat<?php echo $this->catrow->id; ?>" align="left" valign="top" itemprop="name">
							<?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row) . JemOutput::publishstateicon($row); ?>
						</td>
						<?php endif; ?>

						<?php if ($this->jemsettings->showlocate == 1) : ?>
						<td headers="jem_location_cat<?php echo $this->catrow->id; ?>" align="left" valign="top">
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
						<td headers="jem_city_cat<?php echo $this->catrow->id; ?>" align="left" valign="top">
							<?php echo !empty($row->city) ? $this->escape($row->city) : '-'; ?>
						</td>
						<?php endif; ?>

						<?php if ($this->jemsettings->showstate == 1) : ?>
						<td headers="jem_state_cat<?php echo $this->catrow->id; ?>" align="left" valign="top">
							<?php echo !empty($row->state) ? $this->escape($row->state) : '-'; ?>
						</td>
						<?php endif; ?>

						<?php if ($this->jemsettings->showcat == 1) : ?>
						<td headers="jem_category_cat<?php echo $this->catrow->id; ?>" align="left" valign="top">
							<?php echo implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist)); ?>
						</td>
						<?php endif; ?>
					</tr>
					<?php $odd = 1 - $odd; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
