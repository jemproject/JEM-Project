<?php
/**
 * @version 1.9 $Id$
 * @package JEM
 * @subpackage JEM Wide Module
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 *
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

defined('_JEXEC') or die;
?>

<div id="jemmodulewide">

<table class="eventset" summary="mod_jem_wide">

	<colgroup>
		<col width="30%" class="jemmodw_col_title" />
		<col width="20%" class="jemmodw_col_category" />
		<col width="20%" class="jemmodw_col_venue" />
		<col width="15%" class="jemmodw_col_eventimage" />
		<col width="15%" class="jemmodw_col_venueimage" />
	</colgroup>

<?php foreach ($list as $item) :  ?>
	<tr>
		<td valign="top">
			<span class="event-title">
				<?php if ($item->eventlink) : ?>
				<a href="<?php echo $item->eventlink; ?>" title="<?php echo $item->title; ?>">
				<?php endif; ?>
						
					<?php echo $item->title; ?>
							
				<?php if ($item->eventlink) : ?>
				</a>
				<?php endif; ?>
			</span>
			
			<br />
			
			<span class="date">
				<?php echo $item->date; ?>
			</span>
			<?php 
			
			if ($item->time && $params->get('datemethod', 1) == 1) : 
			?>
			<span class="time">
				<?php echo $item->time; ?>
			</span>
			<?php endif; ?>
						
		</td>
		<td>
			<span class="category">
				<?php if ($item->categorylink) : ?>
				<a href="<?php echo $item->categorylink; ?>" title="<?php echo $item->catname; ?>">
				<?php endif; ?>
						
					<?php echo $item->catname; ?>
							
				<?php if ($item->categorylink) : ?>
				</a>
				<?php endif; ?>
			</span>
		</td>
		<td>
			<span class="venue-title">
				<?php if ($item->venuelink) : ?>
				<a href="<?php echo $item->venuelink; ?>" title="<?php echo $item->venue; ?>">
				<?php endif; ?>
						
					<?php echo $item->venue; ?>
							
				<?php if ($item->venuelink) : ?>
				</a>
				<?php endif; ?>
			</span>
		</td>
		<td align="center" class="event-image-cell">
			<?php if ($params->get('use_modal')) : ?>
			
			<?php if ($item->eventimageorig) {
				$image = $item->eventimageorig;
			} else   { $image = '';   }
			 ?>
			
			<a href="<?php echo $image; ?>" class="modal" title="<?php echo $item->title; ?>">
			<?php endif; ?>
						
				<img src="<?php echo $item->eventimage; ?>" alt="<?php echo $item->title; ?>" class="image-preview" />
								
			<?php if ($item->eventlink) : ?>
			</a>
			<?php endif; ?>
		</td>
		<td align="center" class="event-image-cell">
			<?php if ($params->get('use_modal')) : ?>
			<a href="<?php echo $item->venueimageorig; ?>" class="modal" title="<?php echo $item->venue; ?>">
			<?php endif; ?>
						
				<img src="<?php echo $item->venueimage; ?>" alt="<?php echo $item->venue; ?>" class="image-preview" />
							
			<?php if ($item->venuelink) : ?>
			</a>
			<?php endif; ?>
		</td>
	</tr>
<?php endforeach; ?>
</table>
</div>