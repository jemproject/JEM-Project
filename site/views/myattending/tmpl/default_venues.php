<?php
/**
 * @version 1.9 $Id$
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

<?php if (count((array)$this->venues)) : ?>

<h2><?php echo JText::_('COM_JEM_MY_VENUES'); ?></h2>




<form>
<table class="eventtable" style="width:<?php echo $this->jemsettings->tablewidth; ?>;" summary="venues list">
<colgroup>
<col width="1%" class="jem_col_title" />
	<col width="1%" class="jem_col_city" />
</colgroup>


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
  <?php $link = JRoute::_('index.php?option=com_jem&view=venueevents&id=' . $row->venueslug); ?>
    <tr class="sectiontableentry<?php echo $i + 1 . $this->params->get( 'pageclass_sfx' ); ?>" >
      <td headers="jem_title" align="left" valign="top"><?php echo JHTML::link($link, $row->venue); ?></td>
      <td headers="jem_city" align="left" valign="top"><?php echo $row->city ? $row->city : '-'; ?></td>      
    </tr>
  <?php 
  $i = 1 - $i;
  endforeach;
  ?>
	</tbody>
</table>
</form>


<div class="pagination">
  <?php echo $this->venues_pagination->getPagesLinks(); ?>
</div>


<?php endif; ?>