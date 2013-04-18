<?php
/**
 * @version 1.1 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

// no direct access
defined( '_JEXEC' ) or die;
?>
<script type="text/javascript">

	function tableOrdering( order, dir, view )
	{
		var form = document.getElementById("adminForm");

		form.filter_order.value 	= order;
		form.filter_order_Dir.value	= dir;
		form.submit( view );
	}
</script>


<?php if ($this->jemsettings->filter || $this->jemsettings->display) : ?>
<div id="el_filter" class="floattext">
		<?php if ($this->jemsettings->filter) : ?>
		<div class="el_fleft">
			<?php
			echo '<label for="filter_type">'.JText::_('COM_JEM_FILTER').'</label>&nbsp;';
			echo $this->lists['filter_types'].'&nbsp;';
			?>
			<input type="text" name="filter" id="filter" value="<?php echo $this->lists['filter'];?>" class="inputbox" onchange="document.getElementById('adminForm').submit();" />
			<button class="regular" onclick="document.getElementById('adminForm').submit();"><?php echo JText::_( 'COM_JEM_GO' ); ?></button>
			<button class="regular" onclick="document.getElementById('filter').value='';document.getElementById('adminForm').submit();"><?php echo JText::_( 'COM_JEM_RESET' ); ?></button>
		</div>
		<?php endif; ?>
		<?php if ($this->jemsettings->display) : ?>
		<div class="el_fright">
			<?php
			echo '<label for="limit">'.JText::_('COM_JEM_DISPLAY_NUM').'</label>&nbsp;';
			echo $this->pagination->getLimitBox();
			?>
		</div>
		<?php endif; ?>
</div>
<?php endif; ?>

<table class="eventtable" style="width:<?php echo $this->jemsettings->tablewidth; ?>;" summary="jem">

	<colgroup>
		<?php if ($this->jemsettings->showeventimage == 1) :	?>
			<col width="<?php echo $this->jemsettings->tableeventimagewidth; ?>" class="el_col_eventimage" />
		<?php endif; ?>
	<col width="<?php echo $this->jemsettings->datewidth; ?>" class="el_col_date" />
		<?php if ($this->jemsettings->showtitle == 1) : ?>
			<col width="<?php echo $this->jemsettings->titlewidth; ?>" class="el_col_title" />
		<?php endif; ?>
		<?php if ($this->jemsettings->showlocate == 1) :	?>
			<col width="<?php echo $this->jemsettings->locationwidth; ?>" class="el_col_venue" />
		<?php endif; ?>
		<?php if ($this->jemsettings->showcity == 1) :	?>
			<col width="<?php echo $this->jemsettings->citywidth; ?>" class="el_col_city" />
		<?php endif; ?>
		<?php if ($this->jemsettings->showstate == 1) :	?>
			<col width="<?php echo $this->jemsettings->statewidth; ?>" class="el_col_state" />
		<?php endif; ?>
		<?php if ($this->jemsettings->showcat == 1) :	?>
			<col width="<?php echo $this->jemsettings->catfrowidth; ?>" class="el_col_category" />
		<?php endif; ?>
		<?php if ($this->jemsettings->showatte == 1) :	?>
			<col width="<?php echo $this->jemsettings->attewidth; ?>" class="el_col_atte" />
		<?php endif; ?>
	</colgroup>

	<thead>
			<tr>
			<?php
			if ($this->jemsettings->showeventimage == 1) :
			?>
			<th id="el_eventimage" class="sectiontableheader" align="left"><?php echo $this->jemsettings->eventimagename; ?></th>
				<?php
			endif;
			?>
			<th id="el_date" class="sectiontableheader" align="left"><?php echo JHTML::_('grid.sort', $this->escape($this->jemsettings->datename), 'a.dates', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php
				if ($this->jemsettings->showtitle == 1) :
				?>
				<th id="el_title" class="sectiontableheader" align="left"><?php echo JHTML::_('grid.sort', $this->escape($this->jemsettings->titlename), 'a.title', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php
				endif;
				if ($this->jemsettings->showlocate == 1) :
				?>
				<th id="el_location" class="sectiontableheader" align="left"><?php echo JHTML::_('grid.sort', $this->escape($this->jemsettings->locationname), 'l.venue', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php
				endif;
				if ($this->jemsettings->showcity == 1) :
				?>
				<th id="el_city" class="sectiontableheader" align="left"><?php echo JHTML::_('grid.sort', $this->escape($this->jemsettings->cityname), 'l.city', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php
				endif;
				if ($this->jemsettings->showstate == 1) :
				?>
				<th id="el_state" class="sectiontableheader" align="left"><?php echo JHTML::_('grid.sort', $this->escape($this->jemsettings->statename), 'l.state', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php
				endif;
				if ($this->jemsettings->showcat == 1) :
				?>
				<th id="el_category" class="sectiontableheader" align="left"><?php echo JHTML::_('grid.sort', $this->escape($this->jemsettings->catfroname), 'c.catname', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php
				endif;
				if ($this->jemsettings->showatte == 1) :
				?>
				<th id="el_atte" class="sectiontableheader" align="center"><?php echo $this->escape($this->jemsettings->attename); ?></th>
				<?php
				endif;
				?>
			</tr>
	</thead>
	
	<tbody>
	<?php
	if ($this->noevents == 1) :
		?>
		<tr align="center"><td colspan="0"><?php echo JText::_( 'COM_JEM_NO_EVENTS' ); ?></td></tr>
		<?php
	else :

	$this->rows = $this->getRows();

	foreach ($this->rows as $row) :
		?>
  			<tr class="sectiontableentry<?php echo ($row->odd +1 ) . $this->params->get( 'pageclass_sfx' ); ?>" >

  			<?php
				if ($this->jemsettings->showeventimage == 1) :
				?>

					<td headers="el_eventimage" align="left" valign="top">
						<?php 
						// echo $row->datimage; 
						
						if ($row->datimage) :
  						$dimage = JEMImage::flyercreator($row->datimage, 'event');
  						echo JEMOutput::flyer( $row, $dimage, 'event' );
				else :
 						 echo JHTML::_('image', 'media/com_jem/images/noimage.png', JText::_('COM_JEM_NO_IMAGE'), array('class' => ''));
						 endif;
						
						?>
					</td>

				<?php
				endif;
				?>
  			
  			
  			
  			
  			
  			
    			<td headers="el_date" align="left">
    				<strong>
    				<?php if (JEMHelper::isValidDate($row->dates)): ?>
    					<?php echo JEMOutput::formatdate($row->dates, $row->times); ?>
    					
    					<?php
    					if ($row->enddates) :
    						echo ' - '.JEMOutput::formatdate($row->enddates, $row->endtimes);
    					endif;
    					?>
    					<?php else: ?>
    						<?php echo JText::_('COM_JEM_OPEN_DATE'); ?>
    					<?php endif; ?>
    				</strong>
    				
					<?php
					if ($this->jemsettings->showtime == 1) :
					?>
						<br />
						<?php
						echo JEMOutput::formattime($row->dates, $row->times);
						
						if ($row->endtimes) :
							echo ' - '.JEMOutput::formattime($row->enddates, $row->endtimes);
						endif;
					endif;
					?>
				</td>

				
				
				<?php
				
				foreach ($row->categories as $key => $category) :
					$cid = $category->catslug;
					break;
				endforeach;
				//Link to details
				$detaillink = JRoute::_( 'index.php?view=details&id='. $row->slug );
				//title
				if (($this->jemsettings->showtitle == 1 ) && ($this->jemsettings->showdetails == 1) ) :
				?>

				<td headers="el_title" align="left" valign="top"><a href="<?php echo $detaillink ; ?>"> <?php echo $this->escape($row->title); ?></a></td>

				<?php
				endif;

				if (( $this->jemsettings->showtitle == 1 ) && ($this->jemsettings->showdetails == 0) ) :
				?>

				<td headers="el_title" align="left" valign="top"><?php echo $this->escape($row->title); ?></td>

				<?php
				endif;
				if ($this->jemsettings->showlocate == 1) :
				?>

					<td headers="el_location" align="left" valign="top">
						<?php
						if ($this->jemsettings->showlinkvenue == 1 ) :
							echo $row->locid != 0 ? "<a href='".JRoute::_('index.php?view=venueevents&id='.$row->venueslug)."'>".$this->escape($row->venue)."</a>" : '-';
						else :
							echo $row->locid ? $this->escape($row->venue) : '-';
						endif;
						?>
					</td>

				<?php
				endif;

				if ($this->jemsettings->showcity == 1) :
				?>

					<td headers="el_city" align="left" valign="top"><?php echo $row->city ? $this->escape($row->city) : '-'; ?></td>

				<?php
				endif;

				if ($this->jemsettings->showstate == 1) :
				?>

					<td headers="el_state" align="left" valign="top"><?php echo $row->state ? $this->escape($row->state) : '-'; ?></td>
				<?php
				endif;

				if ($this->jemsettings->showcat == 1) :
				
				?>
				<td headers="el_category" align="left" valign="top">
					<?php
					$nr = count($row->categories);
					$ix = 0;
					foreach ($row->categories as $key => $category) :

						if ($this->jemsettings->catlinklist == 1) :
						?>
								<a href="<?php echo JRoute::_('index.php?view=categoryevents&id='.$category->catslug); ?>">
									<?php echo $category->catname; ?>
								</a>
						<?php else : ?>

							<?php echo $category->catname; ?>

						<?php
						endif;
						
						$ix++;
						if ($ix != $nr) :
							echo ', ';
						endif;
					endforeach;
					?>
				</td>
				<?php
				endif;
				
				
				if ($this->jemsettings->showatte == 1) :
				?>
					<td headers="el_atte" align="center" valign="top"><?php echo $row->attendees; ?></td>
				<?php
				endif;
				?>
				
				
				
			</tr>

  		<?php
		endforeach;
		endif;
		?>

	</tbody>
</table>
	