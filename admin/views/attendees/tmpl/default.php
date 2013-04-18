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

defined('_JEXEC') or die; 
JHTML::_('behavior.tooltip');

$colspan = ($this->event->waitinglist ? 10 : 9);
?>

<form action="<?php echo JRoute::_('index.php?option=com_jem&view=attendees'); ?>"  method="post" name="adminForm" id="adminForm">

	<table class="adminlist">
		<tr>
		  	<td width="80%">
				<b><?php echo JText::_( 'COM_JEM_DATE' ).':'; ?></b>&nbsp;<?php echo $this->event->dates; ?><br />
				<b><?php echo JText::_( 'COM_JEM_EVENT_TITLE' ).':'; ?></b>&nbsp;<?php echo htmlspecialchars($this->event->title, ENT_QUOTES, 'UTF-8'); ?>
			</td>
			<td width="20%">
				<div class="button2-left"><div class="blank"><a title="<?php echo JText::_('COM_JEM_PRINT'); ?>" onclick="window.open('index.php?option=com_jem&amp;view=attendees&amp;layout=print&amp;task=print&amp;tmpl=component&amp;id=<?php echo $this->event->id; ?>', 'popup', 'width=750,height=400,scrollbars=yes,toolbar=no,status=no,resizable=yes,menubar=no,location=no,directories=no,top=10,left=10')"><?php echo JText::_('COM_JEM_PRINT'); ?></a></div></div>
				<div class="button2-left"><div class="blank"><a title="<?php echo JText::_('COM_JEM_CSV_EXPORT'); ?>" onclick="window.open('index.php?option=com_jem&amp;task=export&amp;controller=attendees&amp;tmpl=raw&amp;id=<?php echo $this->event->id; ?>')"><?php echo JText::_('COM_JEM_CSV_EXPORT'); ?></a></div></div>
			</td>
		  </tr>
	</table>

	<br />

	<table class="adminform">
		<tr>
			 <td width="100%">
			 	<?php echo JText::_( 'COM_JEM_SEARCH' ).' '.$this->lists['filter']; ?>
				<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
				<button onclick="document.adminForm.submit();"><?php echo JText::_( 'COM_JEM_GO' ); ?></button>
				<button onclick="this.form.getElementById('search').value='';this.form.submit();"><?php echo JText::_( 'COM_JEM_RESET' ); ?></button>
			</td>
			<?php if ($this->event->waitinglist): ?>
			 <td style="text-align:right; white-space:nowrap;">
			 	<?php echo JText::_( 'COM_JEM_STATE' ).' '.$this->lists['waiting']; ?>
			</td>
			<?php endif; ?>
		</tr>
	</table>

	<table class="adminlist">
		<thead>
			<tr>
				<th width="5%"><?php echo JText::_( 'COM_JEM_NUM' ); ?></th>
				<th width="5%"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_NAME', 'u.name', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_USERNAME', 'u.username', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<th class="title"><?php echo JText::_( 'COM_JEM_EMAIL' ); ?></th>
				<th class="title"><?php echo JText::_( 'COM_JEM_IP_ADDRESS' ); ?></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_REGDATE', 'r.uregdate', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_USER_ID', 'r.uid', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php if ($this->event->waitinglist): ?>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_HEADER_WAITINGLIST_STATUS', 'r.waiting', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php endif;?>
				<th class="title"><?php echo JText::_( 'COM_JEM_REMOVE_USER' ); ?></th>
			</tr>
		</thead>

		<tfoot>
			<tr>
				<td colspan="<?php echo $colspan; ?>"><?php echo $this->pagination->getListFooter(); ?></td>
			</tr>
		</tfoot>

		<tbody>
			<?php
   		foreach ($this->rows as $i => $row) :
			?>
			<tr class="row<?php echo $i % 2; ?>">
				<td><?php echo $this->pagination->getRowOffset( $i ); ?></td>
				<td><input type="checkbox" id="cb<?php echo $i;?>" name="cid[]" value="<?php echo $row->id; ?>" onclick="isChecked(this.checked);" /></td>
				<td><a href="<?php echo JRoute::_( 'index.php?option=com_jem&controller=attendees&task=edit&cid[]='.$row->id ); ?>"><?php echo $row->name; ?></a></td>
				<td>
					<a href="<?php echo JRoute::_( 'index.php?option=com_users&task=edit&cid[]='.$row->uid ); ?>"><?php echo $row->username; ?></a>
				</td>
				<td><a href="mailto:<?php echo $row->email; ?>"><?php echo $row->email; ?></a></td>
				<td><?php echo $row->uip == 'DISABLED' ? JText::_( 'COM_JEM_DISABLED' ) : $row->uip; ?></td>
				<td><?php echo JHTML::Date( $row->uregdate, JText::_( 'DATE_FORMAT_LC2' ) ); ?></td>
				<td><?php echo $row->uid; ?></td>
				<?php if ($this->event->waitinglist): ?>
				<td class="hasTip" title="<?php echo ($row->waiting ? JText::_('COM_JEM_ON_WAITINGLIST') : JText::_('COM_JEM_ATTENDING')).'::'; ?>">
					<?php if ($row->waiting):?>
						<?php echo JHTML::link( JRoute::_('index.php?option=com_jem&controller=attendees&task=toggle&id='.$row->id),
						                        JHTML::image('media/com_jem/images/publish_y.png', JText::_('COM_JEM_ON_WAITINGLIST'))); ?>
					<?php else: ?>
						<?php echo JHTML::link( JRoute::_('index.php?option=com_jem&controller=attendees&task=toggle&id='.$row->id),
						                        JHTML::image('media/com_jem/images/tick.png', JText::_('COM_JEM_ATTENDING'))); ?>
					<?php endif;?>
				</td>
				<?php endif;?>
				<td><a href="javascript: void(0);" onclick="return listItemTask('cb<?php echo $i;?>','remove')"><img src="../media/com_jem/images/publish_x.png" width="16" height="16" border="0" alt="Delete" /></a></td>
			</tr>
			<?php endforeach; ?>
		</tbody>

	</table>

	<p class="copyright">
		<?php echo JEMAdmin::footer( ); ?>

		<?php echo JHTML::_( 'form.token' ); ?>
		<input type="hidden" name="boxchecked" value="0" />
		<input type="hidden" name="controller" value="attendees" />
		<input type="hidden" name="task" value="" />
		<input type="hidden" name="id" value="<?php echo $this->event->id; ?>" />
		<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
		<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	</p>
</form>