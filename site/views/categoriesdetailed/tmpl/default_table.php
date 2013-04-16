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

<table class="eventtable" style="width:<?php echo $this->elsettings->tablewidth; ?>;" summary="jem">
	
	<colgroup>
		<col width="<?php echo $this->elsettings->datewidth; ?>" class="el_col_date" />
		<?php if ($this->elsettings->showtitle == 1) : ?>
			<col width="<?php echo $this->elsettings->titlewidth; ?>" class="el_col_title" />
		<?php endif; ?>
		<?php if ($this->elsettings->showlocate == 1) :	?>
			<col width="<?php echo $this->elsettings->locationwidth; ?>" class="el_col_venue" />
		<?php endif; ?>
		<?php if ($this->elsettings->showcity == 1) :	?>
			<col width="<?php echo $this->elsettings->citywidth; ?>" class="el_col_city" />
		<?php endif; ?>
		<?php if ($this->elsettings->showstate == 1) :	?>
			<col width="<?php echo $this->elsettings->statewidth; ?>" class="el_col_state" />
		<?php endif; ?>
		<?php if ($this->elsettings->showcat == 1) :	?>
			<col width="<?php echo $this->elsettings->catfrowidth; ?>" class="el_col_category" />
		<?php endif; ?>
	</colgroup>
	
	<thead>
			<tr>
				<th id="el_date_cat<?php echo $this->categoryid; ?>" class="sectiontableheader" align="left"><?php echo $this->escape($this->elsettings->datename); ?></th>
				<?php
				if ($this->elsettings->showtitle == 1) :
				?>
				<th id="el_title_cat<?php echo $this->categoryid; ?>" class="sectiontableheader" align="left"><?php echo $this->escape($this->elsettings->titlename); ?></th>
				<?php
				endif;
				if ($this->elsettings->showlocate == 1) :
				?>
				<th id="el_location_cat<?php echo $this->categoryid; ?>" class="sectiontableheader" align="left"><?php echo $this->escape($this->elsettings->locationname); ?></th>
				<?php
				endif;
				if ($this->elsettings->showcity == 1) :
				?>
				<th id="el_city_cat<?php echo $this->categoryid; ?>" class="sectiontableheader" align="left"><?php echo $this->escape($this->elsettings->cityname); ?></th>
				<?php
				endif;
				if ($this->elsettings->showstate == 1) :
				?>
				<th id="el_state_cat<?php echo $this->categoryid; ?>" class="sectiontableheader" align="left"><?php echo $this->escape($this->elsettings->statename); ?></th>
				<?php
				endif;
				if ($this->elsettings->showcat == 1) :
				?>
				<th id="el_category_cat<?php echo $this->categoryid; ?>" class="sectiontableheader" align="left"><?php echo $this->escape($this->elsettings->catfroname); ?></th>
				<?php
				endif;
				?>
			</tr>
	</thead>

	<tbody>
		<?php
		$this->rows = $this->getRows();
		if (!$this->rows) :
		?>
		<tr class="no_events"><td colspan="0"><?php echo JText::_( 'COM_JEM_NO_EVENTS' ); ?></td></tr>
		<?php
		else :

		foreach ($this->rows as $row) :
		?>
  			<tr class="sectiontableentry<?php echo ($row->odd +1 ) . $this->params->get( 'pageclass_sfx' ); ?>" >
    			<td headers="el_date_cat<?php echo $this->categoryid; ?>" align="left">
    			    <strong>
    			    <?php if (ELHelper::isValidDate($row->dates)): ?>
	    					<?php echo ELOutput::formatdate($row->dates, $row->times); ?>
	    					
	    					<?php
	    					if ($row->enddates && $row->enddates != $row->dates) :
	    						echo ' - '.ELOutput::formatdate($row->enddates, $row->endtimes);
	    					endif;
	    					?>
    					<?php else: ?>
    						<?php echo JText::_('COM_JEM_OPEN_DATE'); ?>
    					<?php endif; ?>
    				</strong>
    				
					<?php
					if ($this->elsettings->showtime == 1) :
					?>
						<br />
						<?php
						echo ELOutput::formattime($row->dates, $row->times);
						
						if ($row->endtimes) :
							echo ' - '.ELOutput::formattime($row->enddates, $row->endtimes);
						endif;
					endif;
					?>
				</td>
				<?php
				//Link to details
				$detaillink 	= JRoute::_( JEMHelperRoute::getRoute($row->slug));
				//title
				if (($this->elsettings->showtitle == 1 ) && ($this->elsettings->showdetails == 1) ) :
				?>
				<td headers="el_title_cat<?php echo $this->categoryid; ?>" align="left" valign="top"><a href="<?php echo $detaillink ; ?>"> <?php echo $this->escape($row->title); ?></a></td>
				<?php
				endif;
				if (( $this->elsettings->showtitle == 1 ) && ($this->elsettings->showdetails == 0) ) :
				?>
				<td headers="el_title_cat<?php echo $this->categoryid; ?>" align="left" valign="top"><?php echo $this->escape($row->title); ?></td>
				<?php
				endif;

				if ($this->elsettings->showlocate == 1) :
				?>
					<td headers="el_location_cat<?php echo $this->categoryid; ?>" align="left" valign="top">
				<?php
					if ($this->elsettings->showlinkvenue == 1 ) :
							echo $row->locid != 0 ? "<a href='".JRoute::_("index.php?view=venueevents&id=$row->venueslug")."'>".$this->escape($row->venue)."</a>" : '-';
						else :
							echo $row->locid ? $this->escape($row->venue) : '-';
						endif;
				?>
					</td>
				<?php
				endif;
				if ($this->elsettings->showcity == 1) :
				?>
					<td headers="el_city_cat<?php echo $this->categoryid; ?>" align="left" valign="top"><?php echo $row->city ? $this->escape($row->city) : '-'; ?></td>
				<?php
				endif;

				if ($this->elsettings->showstate == 1) :
				?>
					<td headers="el_state_cat<?php echo $this->categoryid; ?>" align="left" valign="top"><?php echo $row->state ? $this->escape($row->state) : '-'; ?></td>
				<?php
				endif;

				if ($this->elsettings->showcat == 1) :
				
				?>
				<td headers="el_category_cat" align="left" valign="top">
					<?php
					$nr = count($row->categories);
					$ix = 0;
					foreach ($row->categories as $key => $category) :

						if ($this->elsettings->catlinklist == 1) :
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
				?>
			</tr>
  			<?php
			endforeach;
			endif;
			?>
	</tbody>
</table>