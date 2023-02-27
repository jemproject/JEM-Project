<?php
/**
 * @version 2.3.9
 * @package JEM
 * @copyright (C) 2013-2021 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>

<?php
	// calculate span of columns to show, summary must be 12
	$default_span = array('date' => 2, 'title' => 3, 'venue' => 3, 'category' => 2, 'attendees' => 2);
	$a_span = array('date' => $default_span['date']); // always shown
	if ($this->jemsettings->showtitle == 1) {
		$a_span['title'] = $default_span['title'];
	}
	if (($this->jemsettings->showlocate == 1) || ($this->jemsettings->showcity == 1) || ($this->jemsettings->showstate == 1)) {
		$a_span['venue'] = $default_span['venue'];
	}
	if (0 && $this->jemsettings->showcat == 1) { // doesn't make sense
		$a_span['category'] = $default_span['category'];
	}
	if (0 && $this->jemsettings->showatte == 1) { // never shown here
		$a_span['attendees'] = $default_span['attendees'];
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
		<?php if (array_key_exists('attendees', $a_span)) : ?>
		<div class="span<?php echo $a_span['attendees']; ?>"><?php echo JText::_('COM_JEM_TABLE_ATTENDEES'); ?></div>
		<?php endif; ?>
	</div>

	<?php if (empty($this->catrow->events)) : ?>
		<div class="row-fluid sectiontableentry<?php echo $this->params->get('pageclass_sfx'); ?>" >
			<div class="span12">
				<strong><i><?php echo JText::_('COM_JEM_NO_EVENTS'); ?></i></strong>
			</div>
		</div>
	<?php else : ?>
		<?php foreach ($this->catrow->events as $row) : ?>
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

				<?php if (array_key_exists('attendees', $a_span)) : ?>
				<div class="span<?php echo $a_span['attendees']; ?> users">
					<?php echo !empty($row->regCount) ? $this->escape($row->regCount) : '-'; ?>
				</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	<?php endif; /* noevents */ ?>
</div>

