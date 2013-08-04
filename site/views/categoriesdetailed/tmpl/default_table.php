<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>

<table class="eventtable" style="width:<?php echo $this->jemsettings->tablewidth; ?>;" summary="jem">

	<colgroup>
		<col width="<?php echo $this->jemsettings->datewidth; ?>" class="jem_col_date" />
		<?php if ($this->jemsettings->showtitle == 1) : ?>
			<col width="<?php echo $this->jemsettings->titlewidth; ?>" class="jem_col_title" />
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
		<?php if ($this->jemsettings->showcat == 1) :	?>
			<col width="<?php echo $this->jemsettings->catfrowidth; ?>" class="jem_col_category" />
		<?php endif; ?>
	</colgroup>

	<thead>
		<tr>
			<th id="jem_date_cat<?php echo $this->categoryid; ?>" class="sectiontableheader" align="left"><?php echo JText::_('COM_JEM_TABLE_DATE'); ?></th>
			<?php
			if ($this->jemsettings->showtitle == 1) :
			?>
			<th id="jem_title_cat<?php echo $this->categoryid; ?>" class="sectiontableheader" align="left"><?php echo JText::_('COM_JEM_TABLE_TITLE'); ?></th>
			<?php
			endif;
			if ($this->jemsettings->showlocate == 1) :
			?>
			<th id="jem_location_cat<?php echo $this->categoryid; ?>" class="sectiontableheader" align="left"><?php echo JText::_('COM_JEM_TABLE_LOCATION'); ?></th>
			<?php
			endif;
			if ($this->jemsettings->showcity == 1) :
			?>
			<th id="jem_city_cat<?php echo $this->categoryid; ?>" class="sectiontableheader" align="left"><?php echo JText::_('COM_JEM_TABLE_CITY'); ?></th>
			<?php
			endif;
			if ($this->jemsettings->showstate == 1) :
			?>
			<th id="jem_state_cat<?php echo $this->categoryid; ?>" class="sectiontableheader" align="left"><?php echo JText::_('COM_JEM_TABLE_STATE'); ?></th>
			<?php
			endif;
			if ($this->jemsettings->showcat == 1) :
			?>
			<th id="jem_category_cat<?php echo $this->categoryid; ?>" class="sectiontableheader" align="left"><?php echo JText::_('COM_JEM_TABLE_CATEGORY'); ?></th>
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
		<tr class="no_events"><td colspan="20"><?php echo JText::_( 'COM_JEM_NO_EVENTS' ); ?></td></tr>
		<?php
		else :

		foreach ($this->rows as $row) :
		?>
			<tr class="sectiontableentry<?php echo ($row->odd +1 ) . $this->params->get( 'pageclass_sfx' ); ?>" >
				<td headers="jem_date_cat<?php echo $this->categoryid; ?>" align="left">
					<?php echo JEMOutput::formatShortDateTime($row->dates, $row->times,
						$row->enddates, $row->endtimes); ?>
				</td>
				<?php
				//Link to details
				$detaillink 	= JRoute::_( JEMHelperRoute::getRoute($row->slug));
				//title
				if (($this->jemsettings->showtitle == 1 ) && ($this->jemsettings->showdetails == 1) ) :
				?>
				<td headers="jem_title_cat<?php echo $this->categoryid; ?>" align="left" valign="top"><a href="<?php echo $detaillink ; ?>"> <?php echo $this->escape($row->title); ?></a></td>
				<?php
				endif;
				if (( $this->jemsettings->showtitle == 1 ) && ($this->jemsettings->showdetails == 0) ) :
				?>
				<td headers="jem_title_cat<?php echo $this->categoryid; ?>" align="left" valign="top"><?php echo $this->escape($row->title); ?></td>
				<?php
				endif;

				if ($this->jemsettings->showlocate == 1) :
				?>
					<td headers="jem_location_cat<?php echo $this->categoryid; ?>" align="left" valign="top">
				<?php
					if ($this->jemsettings->showlinkvenue == 1 ) :
							echo $row->locid != 0 ? "<a href='".JRoute::_(JEMHelperRoute::getVenueRoute($row->venueslug))."'>".$this->escape($row->venue)."</a>" : '-';
						else :
							echo $row->locid ? $this->escape($row->venue) : '-';
						endif;
				?>
					</td>
				<?php
				endif;
				if ($this->jemsettings->showcity == 1) :
				?>
					<td headers="jem_city_cat<?php echo $this->categoryid; ?>" align="left" valign="top"><?php echo ucwords(strtolower($row->city)) ? $this->escape(ucwords(strtolower($row->city))) : '-'; ?></td>
				<?php
				endif;

				if ($this->jemsettings->showstate == 1) :
				?>
					<td headers="jem_state_cat<?php echo $this->categoryid; ?>" align="left" valign="top"><?php echo ucwords(strtolower($row->state)) ? $this->escape(ucwords(strtolower($row->state))) : '-'; ?></td>
				<?php
				endif;

				if ($this->jemsettings->showcat == 1) :

				?>
				<td headers="jem_category_cat" align="left" valign="top">
					<?php
					$nr = count($row->categories);
					$ix = 0;
					foreach ($row->categories as $key => $category) :

						if ($this->jemsettings->catlinklist == 1) :
						?>
								<a href="<?php echo JRoute::_(JEMHelperRoute::getCategoryRoute($category->catslug)); ?>">
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
				<?php endif; ?>
			</tr>
  			<?php
				endforeach;
			endif;
			?>
	</tbody>
</table>
