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

defined('_JEXEC') or die; ?>

<form action="index.php" method="post" name="adminForm" id="adminForm">

	<table class="adminform">
		<tr>
			<td width="100%">
			  	<?php echo JText::_( 'COM_EVENTLIST_SEARCH' ); ?>
				<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
				<button onclick="document.adminForm.submit();"><?php echo JText::_( 'COM_EVENTLIST_GO' ); ?></button>
				<button onclick="$('search').value='';document.adminForm.submit();"><?php echo JText::_( 'COM_EVENTLIST_RESET' ); ?></button>
			</td>
			<td nowrap="nowrap">
			  <?php
			  echo $this->lists['state'];
				?>
			</td>
		</tr>
	</table>

	<table class="adminlist" cellspacing="1">
	<thead>
		<tr>
			<th width="5"><?php echo JText::_( 'COM_EVENTLIST_NUM' ); ?></th>
			<th width="5"><input type="checkbox" name="toggle" value="" onClick="checkAll(<?php echo count( $this->rows ); ?>);" /></th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'COM_EVENTLIST_CATEGORY', 'c.catname', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="20%"><?php echo JHTML::_('grid.sort', 'COM_EVENTLIST_ALIAS', 'c.alias', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
      <th width="10px" nowrap="nowrap"><?php echo JText::_( 'COM_EVENTLIST_COLOR' ); ?></th>
			<th width="15%"><?php echo JHTML::_('grid.sort', 'COM_EVENTLIST_GROUP', 'gr.name', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JText::_( 'COM_EVENTLIST_EVENTS' ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JText::_( 'COM_EVENTLIST_PUBLISHED' ); ?></th>
			<th width="7%"><?php echo JHTML::_('grid.sort', 'COM_EVENTLIST_ACCESS', 'c.access', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="80"><?php echo JHTML::_('grid.sort', 'COM_EVENTLIST_REORDER', 'c.ordering', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%"><?php echo JHTML::_('grid.order', $this->rows, 'filesave.png', 'saveordercat' ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JHTML::_('grid.sort', 'COM_EVENTLIST_ID', 'c.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="12">
				<?php 
				// echo $this->pageNav->getListFooter(); 
				?>
			</td>
		</tr>
	</tfoot>

	<tbody>
		<?php
		$k = 0;
		$i = 0;
		$n = count($this->rows);
		foreach ($this->rows as $row) {
			$link 		= 'index.php?option=com_eventlist&amp;controller=categories&amp;task=edit&amp;cid[]='. $row->id;
			$grouplink 	= 'index.php?option=com_eventlist&amp;controller=groups&amp;task=edit&amp;cid[]='. $row->groupid;
			$published 	= JHTML::_('grid.published', $row, $i );
			$access 	= JHTML::_('grid.access', $row, $i );
			//$checked 	= JHTML::_('grid.checkedout', $row, $i );
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $this->pageNav->getRowOffset( $i ); ?></td>
			<td><?php echo JHtml::_('grid.id', $i, $row->id); ?></td>
			<td align="left">
				<?php
				if ( $row->checked_out && ( $row->checked_out != $this->user->get('id') ) ) {
					echo $row->treename.' '.$this->escape($row->catname);
				} else {
				?>
					<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_EDIT_CATEGORY' );?>::<?php echo $row->catname; ?>">
					<?php echo $row->treename.' ';?>
					<a href="<?php echo $link; ?>">
					<?php echo $this->escape($row->catname); ?>
					</a></span>
				<?php
				}
				?>
			</td>
			<td>
				<?php
				if (JString::strlen($row->alias) > 25) {
					echo JString::substr( htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8'), 0 , 25).'...';
				} else {
					echo htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8');
				}
				?>
			</td>
      <td align="center">
        <div class="colorpreview" style="width: 15px; background: <?php echo ( $row->color == '' )?"transparent":$row->color; ?>;" title="<?php echo $row->color; ?>">
        &nbsp;
        </div>
      </td>
			<td align="center">
				<?php if ($row->catgroup) {	?>
					<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_EDIT_GROUP' );?>::<?php echo $row->catgroup; ?>">
					<a href="<?php echo $grouplink; ?>">
						<?php echo htmlspecialchars($row->catgroup, ENT_QUOTES, 'UTF-8'); ?>
					</a></span>
				<?php
				} else {
					echo '-';
				}
				?>
			</td>
			<td align="center">
				<?php echo $row->assignedevents; ?>
			</td>
			<td align="center">
				<?php echo $published; ?>
			</td>
			<td align="center">
				<?php echo $access; ?>
			</td>
			<td class="order" colspan="2">
				<span><?php echo $this->pageNav->orderUpIcon( $i, true, 'orderup', 'Move Up', $this->ordering ); ?></span>

				<span><?php echo $this->pageNav->orderDownIcon( $i, $n, true, 'orderdown', 'Move Down', $this->ordering );?></span>

				<?php $disabled = $this->ordering ?  '' : '"disabled=disabled"'; ?>

				<input type="text" name="order[]" size="5" value="<?php echo $row->ordering; ?>" <?php echo $disabled; ?> class="text_area" style="text-align: center" />
			</td>
			<td align="center"><?php echo $row->id; ?></td>
		</tr>
		<?php 
		$k = 1 - $k;
        $i++;
		}
		?>
	</tbody>

	</table>

	<p class="copyright">
		<?php echo ELAdmin::footer( ); ?>
	</p>

	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_eventlist" />
	<input type="hidden" name="controller" value="categories" />
	<input type="hidden" name="view" value="categories" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<?php echo JHTML::_( 'form.token' ); ?>
</form>