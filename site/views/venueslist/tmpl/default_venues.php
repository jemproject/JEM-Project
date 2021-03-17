<?php
/**
 * @version 2.1.5
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;
?>


<form action="<?php echo htmlspecialchars($this->action); ?>" method="post" id="adminForm" name="adminForm">
<?php if ($this->settings->get('global_show_filter',1) || $this->settings->get('global_display',1)) : ?>
	
<div id="jem_filter" class="floattext">	
	<?php if ($this->settings->get('global_show_filter',1)) : ?>
	<div class="pull-left">
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
	<div class="pull-right">
			<?php

			echo $this->pagination->getLimitBox();
			?>
	</div>
	<?php endif; ?>
</div>	
	
<?php endif; ?>
	<div class="table-responsive">
	<table class="eventtable table" style="width:<?php echo $this->jemsettings->tablewidth; ?>;" summary="Venues">
		<colgroup>
			<col width="5%" class="jem_col_city" />
			<col width="1%" class="jem_col_state" />


			
			<?php if ($this->jemsettings->showlocate == 1) :	?>
			<col width="<?php echo $this->jemsettings->locationwidth; ?>" class="jem_col_venue" />
			<?php endif; ?>
		</colgroup>
		<thead>
			<tr>
				<th id="jem_city" class="sectiontableheader" align="left"><?php echo JHtml::_('grid.sort', 'COM_JEM_TABLE_CITY', 'a.city', $this->lists['order_Dir'], $this->lists['order']); ?></th>
				<th id="jem_state" class="sectiontableheader" align="left"><?php echo JHtml::_('grid.sort', 'COM_JEM_TABLE_STATE', 'a.state', $this->lists['order_Dir'], $this->lists['order']); ?></th>
			
			
				<?php if ($this->jemsettings->showlocate == 1) : ?>
				<th id="jem_location" class="sectiontableheader" align="left"><?php echo JHtml::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'a.venue', $this->lists['order_Dir'], $this->lists['order']); ?></th>
				<?php endif; ?>


			</tr>
		</thead>

		<tbody>
		<?php if (count((array)$this->items) == 0) : ?>
			<tr align="center"><td colspan="0"><?php echo JText::_('COM_JEM_NO_VENUES'); ?></td></tr>
		<?php else :?>
			<?php foreach ($this->items as $i => $row) : ?>
				<tr class="row<?php echo $i % 2; ?>">
				
		
				
					<td headers="jem_city" align="left" valign="top"><?php echo $row->city ? $this->escape($row->city) : '-'; ?></td>
					<td headers="jem_state" align="left" valign="top">
						<?php echo !empty($row->state) ? $this->escape($row->state) : '-'; ?>
					</td>	
				
					<?php if ($this->jemsettings->showlocate == 1) : ?>
					<td headers="jem_location" align="left" valign="top">
						<?php
						if ($this->jemsettings->showlinkvenue == 1) :
							echo $row->id != 0 ? "<a href='".JRoute::_(JemHelperRoute::getVenueRoute($row->venueslug))."'>".$this->escape($row->venue)."</a>" : '-';
						else :
							echo $row->id ? $this->escape($row->venue) : '-';
						endif;
						?>
					</td>
					<?php endif; ?>




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
	<?php echo $this->pagination->getPagesLinks(); ?>
</div>
