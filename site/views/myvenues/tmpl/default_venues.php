<?php
/**
 * @version 2.1.7
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;
?>

<?php if (!$this->params->get('show_page_heading', 1)) :
           /* hide this if page heading is shown */     ?>
<h2><?php echo JText::_('COM_JEM_MY_VENUES'); ?></h2>
<?php endif; ?>

<form action="<?php echo htmlspecialchars($this->action); ?>" method="post" id="adminForm" name="adminForm">

	<?php if ($this->settings->get('global_show_filter',1) || $this->settings->get('global_display',1)) : ?>
	<div id="jem_filter" class="floattext">
		<?php if ($this->settings->get('global_show_filter',1)) : ?>
		<div class="jem_fleft">
			<?php
			echo '<label for="filter">'.JText::_('COM_JEM_FILTER').'</label>&nbsp;';
			echo $this->lists['filter'].'&nbsp;';
			?>
			<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search'];?>" class="inputbox" onchange="document.adminForm.submit();" />
			<button class="buttonfilter" type="submit"><?php echo JText::_('JSEARCH_FILTER_SUBMIT'); ?></button>
			<button class="buttonfilter" type="button" onclick="document.id('filter_search').value='';this.form.submit();"><?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?></button>
		</div>
		<?php endif; ?>

		<?php if ($this->settings->get('global_display',1)) : ?>
		<div class="jem_fright">
			<?php
			echo '<label for="limit">'.JText::_('COM_JEM_DISPLAY_NUM').'</label>&nbsp;';
			echo $this->venues_pagination->getLimitBox();
			?>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<table class="eventtable" style="width:<?php echo $this->jemsettings->tablewidth; ?>;" summary="Venues">
		<colgroup>
			<?php if (empty($this->print) && $this->permissions->canPublishVenue) : ?>
			<col width="1%" class="jem_col_checkall" />
			<?php endif; ?>
			<?php if ($this->jemsettings->showlocate == 1) :	?>
			<col width="<?php echo $this->jemsettings->locationwidth; ?>" class="jem_col_venue" />
			<?php endif; ?>
			<?php if ($this->jemsettings->showcity == 1) :	?>
			<col width="<?php echo $this->jemsettings->citywidth; ?>" class="jem_col_city" />
			<?php endif; ?>
			<?php if ($this->jemsettings->showstate == 1) :	?>
			<col width="<?php echo $this->jemsettings->statewidth; ?>" class="jem_col_state" />
			<?php endif; ?>
			<col width="1%" class="jem_col_status" />
		</colgroup>

		<thead>
			<tr>
				<?php if (empty($this->print) && $this->permissions->canPublishVenue) : ?>
				<th class="sectiontableheader center"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
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
				<th id="jem_status" class="sectiontableheader" align="center" nowrap="nowrap"><?php echo JText::_('JSTATUS'); ?></th>
			</tr>
		</thead>

		<tbody>
		<?php if (count((array)$this->venues) == 0) : ?>
			<tr align="center"><td colspan="0"><?php echo JText::_('COM_JEM_NO_VENUES'); ?></td></tr>
		<?php else :?>
			<?php foreach ($this->venues as $i => $row) : ?>
				<tr class="row<?php echo $i % 2; ?>">

					<?php if (empty($this->print) && $this->permissions->canPublishVenue) : ?>
					<td class="center">
						<?php
						if (!empty($row->params) && $row->params->get('access-change', false)) :
							echo JHtml::_('grid.id', $i, $row->id);
						endif;
						?>
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
					<td headers="jem_city" align="left" valign="top"><?php echo $row->city ? $this->escape($row->city) : '-'; ?></td>
					<?php endif; ?>

					<?php if ($this->jemsettings->showstate == 1) : ?>
					<td headers="jem_state" align="left" valign="top"><?php echo $row->state ? $this->escape($row->state) : '-'; ?></td>
					<?php endif; ?>

					<td class="center">
						<?php // Ensure icon is not clickable if user isn't allowed to change state!
						$enabled = empty($this->print) && !empty($row->params) && $row->params->get('access-change', false);
						echo JHtml::_('jgrid.published', $row->published, $i, 'myvenues.', $enabled);
						?>
					</td>
				</tr>

				<?php $i = 1 - $i; ?>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="option" value="com_jem" />
	<?php echo JHtml::_('form.token'); ?>
</form>

<div class="pagination">
	<?php echo $this->venues_pagination->getPagesLinks(); ?>
</div>