<?php
/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );
?>

<?php if (count((array)$this->venues)) : ?>

<h2><?php echo JText::_('My Venues'); ?></h2>

<table class="eventtable" width="<?php echo $this->elsettings->tablewidth; ?>" border="0" cellspacing="0" cellpadding="0" summary="venues list">
	<thead>
		<tr>
			<th id="el_title" class="sectiontableheader" align="left"><?php echo JText::_('VENUE'); ?></th>
			<th id="el_city" class="sectiontableheader" align="left"><?php echo JText::_('CITY'); ?></th>
		</tr>
	</thead>
	<tbody>
  <?php 
  $i = 0;
  foreach ((array) $this->venues as $row) : 
  ?>
  <?php $link = JRoute::_('index.php?option=com_eventlist&view=venueevents&id=' . $row->venueslug); ?>
    <tr class="sectiontableentry<?php echo $i + 1 . $this->params->get( 'pageclass_sfx' ); ?>" >
      <td headers="el_title" align="left" valign="top"><?php echo JHTML::link($link, $row->venue); ?></td>
      <td headers="el_city" align="left" valign="top"><?php echo $row->city ? $row->city : '-'; ?></td>      
    </tr>
  <?php 
  $i = 1 - $i;
  endforeach;
  ?>
	</tbody>
</table>

<div class="pageslinks">
  <?php echo $this->venues_pageNav->getPagesLinks(); ?>
</div>

<p class="pagescounter">
  <?php echo $this->venues_pageNav->getPagesCounter(); ?>
</p>
<?php endif; ?>