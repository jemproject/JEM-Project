<?php
/**
 * @version 2.3.9
 * @package JEM
 * @copyright (C) 2013-2021 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

// JHtml::_('behavior.tooltip');
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

<?php if (!$this->params->get('show_page_heading', 1)) : /* hide this if page heading is shown */ ?>
<h2><?php echo JText::_('COM_JEM_MY_EVENTS'); ?></h2>
<?php endif; ?>

<form action="<?php echo htmlspecialchars($this->action); ?>" method="post" id="adminForm" name="adminForm">
	<?php if ($this->settings->get('global_show_filter',1) || $this->settings->get('global_display',1)) : ?>
	<div id="jem_filter" class="floattext">
		<?php if ($this->settings->get('global_show_filter',1)) : ?>
		<div class="jem_fleft">
			<label for="filter"><?php echo JText::_('COM_JEM_FILTER'); ?></label>
			<?php echo $this->lists['filter'].'&nbsp;'; ?>
			<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search'];?>" class="inputbox" onchange="document.adminForm.submit();" />
			<button class="buttonfilter" type="submit"><?php echo JText::_('JSEARCH_FILTER_SUBMIT'); ?></button>
			<button class="buttonfilter" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?></button>
		</div>
		<?php endif; ?>

		<?php if ($this->settings->get('global_display',1)) : ?>
		<div class="jem_fright">
			<label for="sort_by"><?php echo JText::_('COM_JEM_ORDERING'); ?></label>
			<?php echo $this->lists['sort_by'].' '; ?>
			<label for="limit"><?php echo JText::_('COM_JEM_DISPLAY_NUM'); ?></label>
			<?php echo $this->events_pagination->getLimitBox(); ?>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php
		// calculate span of columns to show, summary must be 12
		$default_span = array('check' => 1, /*'image' => 1,*/ 'date' => 2, 'title' => 2, 'venue' => 2, 'category' => 2, 'attendees' => 2, 'status' => 1);
		$a_span = array('check' => $default_span['check'], 'date' => $default_span['date'], 'status' => $default_span['status']); // always shown
		/*if ($this->jemsettings->showeventimage == 1) {
			$a_span['image'] = $default_span['image'];
		}*/
		if ($this->jemsettings->showtitle == 1) {
			$a_span['title'] = $default_span['title'];
		}
		if (($this->jemsettings->showlocate == 1) || ($this->jemsettings->showcity == 1) || ($this->jemsettings->showstate == 1)) {
			$a_span['venue'] = $default_span['venue'];
		}
		if ($this->jemsettings->showcat == 1) {
			$a_span['category'] = $default_span['category'];
		}
		if ($this->params->get('displayattendeecolumn') == 1) {
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
			<?php if (empty($this->print) && !empty($this->permissions->canPublishEvent)) : ?>
			<div class="span<?php echo $a_span['check']; ?> showalways"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></div>
			<?php endif; ?>
			<?php if (array_key_exists('image', $a_span)) : ?>
			<!-- div class="span<?php echo $a_span['image']; ?>"><?php echo JText::_('COM_JEM_TABLE_EVENTIMAGE'); ?></div -->
			<?php endif; ?>
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
			<?php if (array_key_exists('status', $a_span)) : ?>
			<div class="span<?php echo $a_span['status']; ?>"><?php echo JText::_('JSTATUS'); ?></div>
			<?php endif; ?>
		</div>

		<?php if (empty($this->events)) : ?>
			<div class="row-fluid sectiontableentry<?php echo $this->params->get('pageclass_sfx'); ?>" >
				<div class="span12">
					<strong><i><?php echo JText::_('COM_JEM_NO_EVENTS'); ?></i></strong>
				</div>
			</div>
		<?php else : ?>
			<?php foreach ($this->events as $i => $row) : ?>
				<?php if (!empty($row->featured)) : ?>
				<div class="row-fluid sectiontableentry featured featured<?php echo $row->id.$this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
				<?php else : ?>
				<div class="row-fluid sectiontableentry<?php echo $this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
				<?php endif; ?>

					<?php if (empty($this->print) && !empty($this->permissions->canPublishEvent)) : ?>
					<div class="span<?php echo $a_span['check']; ?>">
						<?php
						if (!empty($row->params) && $row->params->get('access-change', false)) :
							echo JHtml::_('grid.id', $i, $row->eventid);
						endif;
						?>
					</div>
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
							</a><?php /*echo JemOutput::publishstateicon($row);*/ ?>
						</div>
						<?php else : ?>
						<div class="event" itemprop="name">
							<?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row) /*. JemOutput::publishstateicon($row)*/; ?>
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
						<?php /*echo !empty($row->regCount) ? $this->escape($row->regCount) : '-';*/ ?>
						<?php
						//if ($this->jemsettings->showfroregistra || ($row->registra & 1)) {
						$g_reg = $this->jemsettings->showfroregistra; // 0:No, 1:Yes, 2:Optional
						$e_reg = $row->registra;
						$reg_enabled = ($g_reg == 1) || (($g_reg == 2) && ($e_reg & 1));
						if ($reg_enabled || ($row->regTotal > 0)) {
							$linkreg  = 'index.php?option=com_jem&amp;view=attendees&amp;id='.$row->id.'&Itemid='.$this->itemid;
							$count = isset($row->regCount) ? $row->regCount : '['.$row->regTotal.']';
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

							if (empty($row->finished)) {
							?>
							<a href="<?php echo $linkreg; ?>" title="<?php echo htmlspecialchars(JText::_('COM_JEM_MYEVENT_ATTENDEES_HINT')); ?>">
								<?php echo $count; ?>
							</a>
							<?php
								if (!$reg_enabled) {
									echo JHtml::_('image', 'com_jem/icon-16-warning.png', '!', array('title' => JText::_('COM_JEM_REGISTRATION_DISABLED'), 'class' => 'icon-inline'), true);
								}
							} else {
								echo $count;
							}
						} else {
							echo JHtml::_('image', 'com_jem/publish_r.png', JText::_('COM_JEM_REGISTRATION_DISABLED'), array('title' => JText::_('COM_JEM_REGISTRATION_DISABLED')), true);
						}
						?>
					</div>
					<?php endif; ?>

					<?php if (array_key_exists('status', $a_span)) : ?>
					<div class="span<?php echo $a_span['status']; ?> status">
						<?php // Ensure icon is not clickable if user isn't allowed to change state!
						$enabled = empty($this->print) && !empty($row->params) && $row->params->get('access-change', false);
						echo JHtml::_('jgrid.published', $row->published, $i, 'myevents.', $enabled);
						?>
					</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php endif; /* noevents */ ?>
	</div>

	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="enableemailaddress" value="<?php echo $this->enableemailaddress; ?>" />
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="task" value="<?php echo $this->task; ?>" />
	<input type="hidden" name="option" value="com_jem" />
	<?php echo JHtml::_('form.token'); ?>
</form>

<div class="pagination">
	<?php echo $this->events_pagination->getPagesLinks(); ?>
</div>