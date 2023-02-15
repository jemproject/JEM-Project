<?php
/**
 * @version 2.3.8
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

	if (/*$this->jemsettings->showlocate ==*/ 1) {
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
	if (1) {
		$sort_by[] = JHtml::_('select.option', 'l.country ASC', JText::_('COM_JEM_COUNTRY') . ' ' . JText::_('COM_JEM_ORDER_ASCENDING'));
		$sort_by[] = JHtml::_('select.option', 'l.country DESC', JText::_('COM_JEM_COUNTRY') . ' ' . JText::_('COM_JEM_ORDER_DESCENDING'));
	}
	$this->lists['sort_by'] = JHtml::_('select.genericlist', $sort_by, 'sort_by', array('size'=>'1','class'=>'inputbox','onchange'=>'fullOrdering(\'sort_by\', \'\');'), 'value', 'text', $this->lists['order'] . ' ' . $this->lists['order_Dir']);
?>

<?php if (!$this->params->get('show_page_heading', 1)) : /* hide this if page heading is shown */ ?>
<h2><?php echo JText::_('COM_JEM_MY_VENUES'); ?></h2>
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
			<?php echo $this->venues_pagination->getLimitBox(); ?>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php
		// calculate span of columns to show, summary must be 12
		$default_span = array('check' => 1, /*'image' => 1,*/ 'venue' => 3, 'city' => 3, 'state' => 3, 'country' => 1, 'status' => 1);
		$a_span = array('check' => $default_span['check'], 'venue' => $default_span['venue'], 'country' => $default_span['country'], 'status' => $default_span['status']); // always shown
		/*if ($this->jemsettings->showeventimage == 1) {
			$a_span['image'] = $default_span['image'];
		}*/
		if ($this->jemsettings->showcity == 1) {
			$a_span['city'] = $default_span['city'];
		}
		if ($this->jemsettings->showstate == 1) {
			$a_span['state'] = $default_span['state'];
		}
		$total = array_sum($a_span);
		if (!array_key_exists('title', $a_span) && !array_key_exists('venue', $a_span) && !array_key_exists('category', $a_span)) {
			$a_span['date'] += 12 - $total;
		} else {
			while ($total < 12) {
				if (array_key_exists('venue', $a_span)) {
					++$a_span['venue'];
					++$total;
				}
				if (($total < 12) && array_key_exists('city', $a_span)) {
					++$a_span['city'];
					++$total;
				}
				if (($total < 12) && array_key_exists('state', $a_span)) {
					++$a_span['state'];
					++$total;
				}
			} // while
		}
	?>
	<div class="eventtable">
		<div class="row-fluid sectiontableheader">
			<?php if (empty($this->print) && !empty($this->permissions->canPublishVenue)) : ?>
			<div class="span<?php echo $a_span['check']; ?> showalways"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></div>
			<?php endif; ?>
			<?php if (array_key_exists('image', $a_span)) : ?>
			<!-- div class="span<?php echo $a_span['image']; ?>"><?php echo JText::_('COM_JEM_TABLE_EVENTIMAGE'); ?></div -->
			<?php endif; ?>
			<?php if (array_key_exists('venue', $a_span)) : ?>
			<div class="span<?php echo $a_span['venue']; ?>"><?php echo JText::_('COM_JEM_TABLE_LOCATION'); ?></div>
			<?php endif; ?>
			<?php if (array_key_exists('city', $a_span)) : ?>
			<div class="span<?php echo $a_span['city']; ?>"><?php echo JText::_('COM_JEM_TABLE_CITY'); ?></div>
			<?php endif; ?>
			<?php if (array_key_exists('state', $a_span)) : ?>
			<div class="span<?php echo $a_span['state']; ?>"><?php echo JText::_('COM_JEM_TABLE_STATE'); ?></div>
			<?php endif; ?>
			<?php if (array_key_exists('country', $a_span)) : ?>
			<div class="span<?php echo $a_span['country']; ?>"><?php echo JText::_('COM_JEM_TABLE_COUNTRY'); ?></div>
			<?php endif; ?>
			<?php if (array_key_exists('status', $a_span)) : ?>
			<div class="span<?php echo $a_span['status']; ?>"><?php echo JText::_('JSTATUS'); ?></div>
			<?php endif; ?>
		</div>

		<?php if (empty($this->venues)) : ?>
			<div class="row-fluid sectiontableentry<?php echo $this->params->get('pageclass_sfx'); ?>" >
				<div class="span12">
					<strong><i><?php echo JText::_('COM_JEM_NO_VENUES'); ?></i></strong>
				</div>
			</div>
		<?php else : ?>
			<?php foreach ($this->venues as $i => $row) : ?>
				<div class="row-fluid sectiontableentry<?php echo $this->params->get('pageclass_sfx'); ?>">

					<?php if (empty($this->print) && !empty($this->permissions->canPublishVenue)) : ?>
					<div class="span<?php echo $a_span['check']; ?>">
						<?php
						if (!empty($row->params) && $row->params->get('access-change', false)) :
							echo JHtml::_('grid.id', $i, $row->id);
						endif;
						?>
					</div>
					<?php endif; ?>

					<?php if (array_key_exists('venue', $a_span)) : ?>
					<div class="span<?php echo $a_span['venue']; ?> venue">
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
					</div>
					<?php endif; ?>

					<?php if (array_key_exists('city', $a_span)) : ?>
					<div class="span<?php echo $a_span['city']; ?> city">
						<?php echo !empty($row->city) ? $this->escape($row->city) : '-'; ?>
					</div>
					<?php endif; ?>

					<?php if (array_key_exists('state', $a_span)) : ?>
					<div class="span<?php echo $a_span['state']; ?> state">
						<?php echo !empty($row->state) ? $this->escape($row->state) : '-'; ?>
					</div>
					<?php endif; ?>

					<?php if (array_key_exists('country', $a_span)) : ?>
					<div class="span<?php echo $a_span['country']; ?> country">
						<?php if (!empty($row->country)) :
							$countryimg = JemHelperCountries::getCountryFlag($row->country);
							if ($countryimg) :
								echo $countryimg; ?><span class="info-text"><?php echo JemHelperCountries::getCountryName($row->country); ?></span><?php
							else :
								echo $this->escape($row->country);
							endif;
						else :
							echo '-';
						endif; ?>
					</div>
					<?php endif; ?>

					<?php if (array_key_exists('status', $a_span)) : ?>
					<div class="span<?php echo $a_span['status']; ?> status">
						<?php // Ensure icon is not clickable if user isn't allowed to change state!
						$enabled = empty($this->print) && !empty($row->params) && $row->params->get('access-change', false);
						echo JHtml::_('jgrid.published', $row->published, $i, 'myvenues.', $enabled);
						?>
					</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php endif; /* noevents */ ?>
	</div>

	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="task" value="<?php echo $this->task; ?>" />
	<input type="hidden" name="option" value="com_jem" />
	<?php echo JHtml::_('form.token'); ?>
</form>

<div class="pagination">
	<?php echo $this->venues_pagination->getPagesLinks(); ?>
</div>