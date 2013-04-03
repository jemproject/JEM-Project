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

defined('_JEXEC') or die; 
JHTML::_('behavior.tooltip');

$colspan = ($this->event->waitinglist ? 10 : 9);
?>

<form action="index.php" method="post" name="adminForm" id="adminForm">

	<table class="adminlist" cellspacing="1">
		<tr>
		  	<td width="80%">
				<b><?php echo JText::_( 'DATE' ).':'; ?></b>&nbsp;<?php echo $this->event->dates; ?><br />
				<b><?php echo JText::_( 'EVENT TITLE' ).':'; ?></b>&nbsp;<?php echo htmlspecialchars($this->event->title, ENT_QUOTES, 'UTF-8'); ?>
			</td>
			<td width="20%">
				<div class="button2-left"><div class="blank"><a title="<?php echo JText::_('COM_EVENTLIST_PRINT'); ?>" onclick="window.open('index.php?option=com_eventlist&amp;view=attendees&amp;layout=print&amp;task=print&amp;tmpl=component&amp;id=<?php echo $this->event->id; ?>', 'popup', 'width=750,height=400,scrollbars=yes,toolbar=no,status=no,resizable=yes,menubar=no,location=no,directories=no,top=10,left=10')"><?php echo JText::_('PRINT'); ?></a></div></div>
				<div class="button2-left"><div class="blank"><a title="<?php echo JText::_('COM_EVENTLIST_CSV_EXPORT'); ?>" onclick="window.open('index.php?option=com_eventlist&amp;task=export&amp;controller=attendees&amp;tmpl=raw&amp;id=<?php echo $this->event->id; ?>')"><?php echo JText::_('CSV EXPORT'); ?></a></div></div>
			</td>
		  </tr>
	</table>

	<br />

	<table class="adminform">
		<tr>
			 <td width="100%">
			 	<?php echo JText::_( 'COM_EVENTLIST_SEARCH' ).' '.$this->lists['filter']; ?>
				<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
				<button onclick="document.adminForm.submit();"><?php echo JText::_( 'COM_EVENTLIST_GO' ); ?></button>
				<button onclick="$('search').value='';document.adminForm.submit();"><?php echo JText::_( 'COM_EVENTLIST_RESET' ); ?></button>
			</td>
			<?php if ($this->event->waitinglist): ?>
			 <td style="text-align:right; white-space:nowrap;">
			 	<?php echo JText::_( 'State' ).' '.$this->lists['waiting']; ?>
			</td>
			<?php endif; ?>
		</tr>
	</table>

	<table class="adminlist" cellspacing="1">
		<thead>
			<tr>
				<th width="5"><?php echo JText::_( 'COM_EVENTLIST_NUM' ); ?></th>
				<th width="5"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_EVENTLIST_NAME', 'u.name', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_EVENTLIST_USERNAME', 'u.username', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<th class="title"><?php echo JText::_( 'COM_EVENTLIST_EMAIL' ); ?></th>
				<th class="title"><?php echo JText::_( 'COM_EVENTLIST_IP_ADDRESS' ); ?></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_EVENTLIST_REGDATE', 'r.uregdate', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_EVENTLIST_USER_ID', 'r.uid', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php if ($this->event->waitinglist): ?>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_EVENTLIST_HEADER_WAITINGLIST_STATUS', 'r.waiting', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php endif;?>
				<th class="title"><?php echo JText::_( 'COM_EVENTLIST_REMOVE_USER' ); ?></th>
			</tr>
		</thead>

		<tfoot>
			<tr>
				<td colspan="<?php echo $colspan; ?>"><?php echo $this->pageNav->getListFooter(); ?></td>
			</tr>
		</tfoot>

		<tbody>
			<?php
			$k = 0;
			for($i=0, $n=count( $this->rows ); $i < $n; $i++) {
				$row = &$this->rows[$i];
   			?>
			<tr class="<?php echo "row$k"; ?>">
				<td><?php echo $this->pageNav->getRowOffset( $i ); ?></td>
				<td><input type="checkbox" id="cb<?php echo $i;?>" name="cid[]" value="<?php echo $row->id; ?>" onclick="isChecked(this.checked);" /></td>
				<td><a href="<?php echo JRoute::_( 'index.php?option=com_eventlist&controller=attendees&task=edit&cid[]='.$row->id ); ?>"><?php echo $row->name; ?></a></td>
				<td>
					<a href="<?php echo JRoute::_( 'index.php?option=com_users&task=edit&cid[]='.$row->uid ); ?>"><?php echo $row->username; ?></a>
				</td>
				<td><a href="mailto:<?php echo $row->email; ?>"><?php echo $row->email; ?></a></td>
				<td><?php echo $row->uip == 'DISABLED' ? JText::_( 'COM_EVENTLIST_DISABLED' ) : $row->uip; ?></td>
				<td><?php echo JHTML::Date( $row->uregdate, JText::_( 'DATE_FORMAT_LC2' ) ); ?></td>
				<td><?php echo $row->uid; ?></td>
				<?php if ($this->event->waitinglist): ?>
				<td class="hasTip" title="<?php echo ($row->waiting ? JText::_('COM_EVENTLIST_ON_WAITINGLIST') : JText::_('COM_EVENTLIST_ATTENDING')).'::'; ?>">
					<?php if ($row->waiting):?>
						<?php echo JHTML::link( JRoute::_('index.php?option=com_eventlist&controller=attendees&task=toggle&id='.$row->id),
						                        JHTML::image('administrator/images/publish_y.png', JText::_('COM_EVENTLIST_ON_WAITINGLIST'))); ?>
					<?php else: ?>
						<?php echo JHTML::link( JRoute::_('index.php?option=com_eventlist&controller=attendees&task=toggle&id='.$row->id),
						                        JHTML::image('administrator/images/tick.png', JText::_('COM_EVENTLIST_ATTENDING'))); ?>
					<?php endif;?>
				</td>
				<?php endif;?>
				<td><a href="javascript: void(0);" onclick="return listItemTask('cb<?php echo $i;?>','remove')"><img src="images/publish_x.png" width="16" height="16" border="0" alt="Delete" /></a></td>
			</tr>
			<?php $k = 1 - $k;  } ?>
		</tbody>

	</table>

	<p class="copyright">
		<?php echo ELAdmin::footer( ); ?>

		<?php echo JHTML::_( 'form.token' ); ?>
		<input type="hidden" name="boxchecked" value="0" />
		<input type="hidden" name="option" value="com_eventlist" />
		<input type="hidden" name="controller" value="attendees" />
		<input type="hidden" name="view" value="attendees" />
		<input type="hidden" name="task" value="" />
		<input type="hidden" name="id" value="<?php echo $this->event->id; ?>" />
		<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
		<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	</p>
</form>