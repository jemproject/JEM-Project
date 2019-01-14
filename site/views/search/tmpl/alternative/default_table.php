<?php
/**
 * @version 2.3.0
 * @package JEM
 * @copyright (C) 2013-2019 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
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

<script type="text/javascript">
	function fullOrdering(id, view)
	{
		var form = document.getElementById("adminForm");
		var field = form.getElementById(id);
		var parts = field.value.split(' ');

		if (parts.length > 1) {
			form.filter_order.value     = parts[0];
			form.filter_order_Dir.value = parts[1];
		}
		form.submit(view);
	}
</script>

<?php
	$sort_by = array();

	$sort_by[] = JHtml::_('select.option', 'a.dates ASC', JText::_('COM_JEM_DATE') . ' ' . JText::_('COM_JEM_ORDER_ASCENDING'));
	$sort_by[] = JHtml::_('select.option', 'a.dates DESC', JText::_('COM_JEM_DATE') . ' ' . JText::_('COM_JEM_ORDER_DESCENDING'));

	if ($this->jemsettings->showtitle == 1) {
		$sort_by[] = JHtml::_('select.option', 'a.title ASC', JText::_('COM_JEM_TITLE') . ' ' . JText::_('COM_JEM_ORDER_ASCENDING'));
		$sort_by[] = JHtml::_('select.option', 'a.title DESC', JText::_('COM_JEM_TITLE') . ' ' . JText::_('COM_JEM_ORDER_DESCENDING'));
	}
	if ($this->jemsettings->showlocate == 1) {
		$sort_by[] = JHtml::_('select.option', 'l.venue ASC', JText::_('COM_JEM_VENUE') . ' ' . JText::_('COM_JEM_ORDER_ASCENDING'));
		$sort_by[] = JHtml::_('select.option', 'l.venue DESC', JText::_('COM_JEM_VENUE') . ' ' . JText::_('COM_JEM_ORDER_DESCENDING'));
	}
	if ($this->jemsettings->showcity == 1) {
		$sort_by[] = JHtml::_('select.option', 'l.city ASC', JText::_('COM_JEM_CITY') . ' ' . JText::_('COM_JEM_ORDER_ASCENDING'));
		$sort_by[] = JHtml::_('select.option', 'l.city DESC', JText::_('COM_JEM_CITY') . ' ' . JText::_('COM_JEM_ORDER_DESCENDING'));
	}
	if ($this->jemsettings->showstate == 1) {
		$sort_by[] = JHtml::_('select.option', 'l.state ASC', JText::_('COM_JEM_STATE') . ' ' . JText::_('COM_JEM_ORDER_ASCENDING'));
		$sort_by[] = JHtml::_('select.option', 'l.state DESC', JText::_('COM_JEM_STATE') . ' ' . JText::_('COM_JEM_ORDER_DESCENDING'));
	}
	if ($this->jemsettings->showcat == 1) {
		$sort_by[] = JHtml::_('select.option', 'c.catname ASC', JText::_('COM_JEM_CATEGORY') . ' ' . JText::_('COM_JEM_ORDER_ASCENDING'));
		$sort_by[] = JHtml::_('select.option', 'c.catname DESC', JText::_('COM_JEM_CATEGORY') . ' ' . JText::_('COM_JEM_ORDER_DESCENDING'));
	}
	$this->lists['sort_by'] = JHtml::_('select.genericlist', $sort_by, 'sort_by', array('size'=>'1','class'=>'inputbox','onchange'=>'fullOrdering(\'sort_by\', \'\');'), 'value', 'text', $this->lists['order'] . ' ' . $this->lists['order_Dir']);
?>

<div id="jem_filter" class="floattext">
	<div class="jem_fleft">
		<div class="row-fluid">
			<div class="span2">
				<label for="filter_type"><?php echo JText::_('COM_JEM_FILTER'); ?></label>
			</div>
			<div class="span10">
				<?php echo $this->lists['filter_types']; ?>
				<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['filter'];?>" class="inputbox" onchange="document.getElementById('adminForm').submit();" />
				<button class="buttonfilter" type="submit"><?php echo JText::_('JSEARCH_FILTER_SUBMIT'); ?></button>
				<button class="buttonfilter" type="button" onclick="document.id('filter_search').value='';this.form.submit();"><?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?></button>
			</div>
		</div>

		<div class="row-fluid">
			<div class="span2">
				<label for="category"><?php echo JText::_('COM_JEM_CATEGORY'); ?></label>
			</div>
			<div class="span10">
				<?php echo $this->lists['categories']; ?>
			</div>
		</div>

		<div class="row-fluid">
			<div class="span2">
				<label for="date"><?php echo JText::_('COM_JEM_SEARCH_DATE'); ?></label>
			</div>
			<div class="span10">
				<div class="nowrap"><?php echo JText::_('COM_JEM_SEARCH_FROM') .'&nbsp;'. $this->lists['date_from'];?></div>
				<div class="nowrap"><?php echo JText::_('COM_JEM_SEARCH_TO'); ?><?php echo $this->lists['date_to'];?></div>
			</div>
		</div>

		<div class="row-fluid">
			<div class="span2">
				<label for="continent"><?php echo ''.JText::_('COM_JEM_CONTINENT'); ?></label>
			</div>
			<div class="span10">
				<?php echo $this->lists['continents'];?>
			</div>
		</div>

		<?php if ($this->filter_continent): ?>
		<div class="row-fluid">
			<div class="span2">
				<label for="country"><?php echo ''.JText::_('COM_JEM_COUNTRY'); ?></label>
			</div>
			<div class="span10">
				<?php echo $this->lists['countries'];?>
			</div>
		</div>
		<?php endif; ?>

		<?php if ($this->filter_continent && $this->filter_country): ?>
		<div class="row-fluid">
			<div class="span2">
				<label for="city"><?php echo ''.JText::_('COM_JEM_CITY');?></label>
			</div>
			<div class="span10">
				<?php echo $this->lists['cities'];?>
			</div>
		</div>
		<?php endif; ?>

		<div class="row-fluid">
			<div class="offset2 span10">
				<input class="buttonfilter" type="submit" value="<?php echo JText::_('COM_JEM_SEARCH_SUBMIT'); ?>"/>
			</div>
		</div>
	</div>

	<?php if ($this->settings->get('global_display',1)) : ?>
	<div class="jem_fright">
		<label for="sort_by"><?php echo JText::_('COM_JEM_ORDERING'); ?></label>
		<?php echo $this->lists['sort_by'].' '; ?>
		<label for="limit"><?php echo JText::_('COM_JEM_DISPLAY_NUM'); ?></label>
		<?php echo $this->pagination->getLimitBox(); ?>
	</div>
	<?php endif; ?>
</div>

<?php
	// calculate span of columns to show, summary must be 12
	$default_span = array('date' => 3, 'title' => 3, 'venue' => 3, 'category' => 3);
	$a_span = array('date' => $default_span['date']); // always shown
	if ($this->jemsettings->showtitle == 1) {
		$a_span['title'] = $default_span['title'];
	}
	if (($this->jemsettings->showlocate == 1) || ($this->jemsettings->showcity == 1) || ($this->jemsettings->showstate == 1)) {
		$a_span['venue'] = $default_span['venue'];
	}
	if ($this->jemsettings->showcat == 1) {
		$a_span['category'] = $default_span['category'];
	}
	$total = array_sum($a_span);
	if (!array_key_exists('title', $a_span) && !array_key_exists('venue', $a_span) && !array_key_exists('category', $a_span)) {
		$a_span['date'] += 12 - $total;
	} else {
		while ($total < 12) {
			if (array_key_exists('title', $a_span)) {
				++$a_span['title'];
				++$total;
			}
			if ($total < 12 && ($a_span['date'] <= $default_span['date'])) {
				++$a_span['date'];
				++$total;
			}
			if (($total < 12) && array_key_exists('venue', $a_span)) {
				++$a_span['venue'];
				++$total;
			}
			if (($total < 12) && array_key_exists('category', $a_span)) {
				++$a_span['category'];
				++$total;
			}
		} // while
	}
?>
<div class="eventtable">
	<div class="row-fluid sectiontableheader">
		<div class="span<?php echo $a_span['date']; ?>"><?php echo JText::_('COM_JEM_TABLE_DATE'); ?></div>
		<?php if (array_key_exists('title', $a_span)) : ?>
		<div class="span<?php echo $a_span['title']; ?>"><?php echo JText::_('COM_JEM_TABLE_TITLE'); ?></div>
		<?php endif; ?>
		<?php if (array_key_exists('venue', $a_span)) : ?>
		<div class="span<?php echo $a_span['venue']; ?>"><?php echo JText::_('COM_JEM_TABLE_LOCATION'); ?></div>
		<?php endif; ?>
		<?php if (array_key_exists('category', $a_span)) : ?>
		<div class="span<?php echo $a_span['category']; ?>"><?php echo JText::_('COM_JEM_TABLE_CATEGORY'); ?></div>
		<?php endif; ?>
	</div>

	<?php if (empty($this->rows)) : ?>
		<div class="row-fluid sectiontableentry<?php echo $this->params->get('pageclass_sfx'); ?>">
			<div class="span12">
				<strong><i><?php echo JText::_('COM_JEM_NO_EVENTS_FOUND'); ?></i></strong>
			</div>
		</div>
	<?php else : ?>
		<?php foreach ($this->rows as $row) : ?>
			<?php if (!empty($row->featured)) : ?>
			<div class="row-fluid sectiontableentry featured featured<?php echo $row->id.$this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
			<?php else : ?>
			<div class="row-fluid sectiontableentry<?php echo $this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
			<?php endif; ?>

				<div class="span<?php echo $a_span['date']; ?> date">
					<?php
						echo JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $this->jemsettings->showtime);
						echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes);
					?>
				</div>

				<?php if (array_key_exists('title', $a_span)) : ?>
				<div class="span<?php echo $a_span['title']; ?>">
					<?php if (($this->jemsettings->showeventimage == 1) && !empty($row->datimage)) : ?>
					<div class="image">
						<?php echo JemOutput::flyer($row, JemImage::flyercreator($row->datimage, 'event'), 'event'); ?>
					</div>
					<?php endif; ?>
					<?php if ($this->jemsettings->showdetails == 1) : ?>
					<div class="event">
						<a href="<?php echo JRoute::_(JemHelperRoute::getEventRoute($row->slug)); ?>" itemprop="url">
							<span itemprop="name"><?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row); ?></span>
						</a><?php echo JemOutput::publishstateicon($row); ?>
					</div>
					<?php else : ?>
					<div class="event" itemprop="name">
						<?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row) . JemOutput::publishstateicon($row); ?>
					</div>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<?php if (array_key_exists('venue', $a_span)) : ?>
				<div class="span<?php echo $a_span['venue']; ?> venue">
					<?php
					$venue = array();
					if ($this->jemsettings->showlocate == 1) {
						if (!empty($row->venue)) {
							if (($this->jemsettings->showlinkvenue == 1) && !empty($row->venueslug)) {
								$venue[] = "<a href='".JRoute::_(JemHelperRoute::getVenueRoute($row->venueslug))."'>".$this->escape($row->venue)."</a>";
							} else {
								$venue[] = $this->escape($row->venue);
							}
						} else {
							$venue[] = '-';
						}
					}
					// if no city skip if also no state, else add hyphen
					if (($this->jemsettings->showcity == 1) && (!empty($row->city) || !empty($row->state))) {
						$venue[] = !empty($row->city) ? $this->escape($row->city) : '-';
					}
					if (($this->jemsettings->showstate == 1) && !empty($row->state)) {
						$venue[] = $this->escape($row->state);
					}
					echo implode(', ', $venue);
					?>
				</div>
				<?php endif; ?>

				<?php if (array_key_exists('category', $a_span)) : ?>
				<div class="span<?php echo $a_span['category']; ?> category">
					<?php echo implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist)); ?>
				</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	<?php endif; /* noevents */ ?>
</div>

