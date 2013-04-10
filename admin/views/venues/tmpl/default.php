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
?>

<form action="index.php" method="post" name="adminForm" id="adminForm">

<table class="adminform">
	<tr>
		<td width="100%">
			 <?php echo JText::_( 'COM_JEM_SEARCH' ).' '.$this->lists['filter']; ?>
			<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
			<button onclick="document.adminForm.submit();"><?php echo JText::_( 'COM_JEM_GO' ); ?></button>
			<button onclick="$('search').value='';document.adminForm.submit();;"><?php echo JText::_( 'COM_JEM_RESET' ); ?></button>
		</td>
		<td nowrap="nowrap"><?php echo $this->lists['state']; ?></td>
	</tr>
</table>

<table class="adminlist" cellspacing="1">
	<thead>
		<tr>
			<th width="5"><?php echo JText::_( 'COM_JEM_NUM' ); ?></th>
			<th width="5"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_VENUE', 'l.venue', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="20%"><?php echo JHTML::_('grid.sort', 'COM_JEM_ALIAS', 'l.alias', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th><?php echo JText::_( 'COM_JEM_WEBSITE' ); ?></th>
			<th><?php echo JHTML::_('grid.sort', 'COM_JEM_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JText::_( 'COM_JEM_PUBLISHED' ); ?></th>
			<th><?php echo JText::_( 'COM_JEM_CREATION' ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JText::_( 'COM_JEM_EVENTS' ); ?></th>
		    <th width="80" colspan="2"><?php echo JHTML::_('grid.sort', 'COM_JEM_REORDER', 'l.ordering', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
		    <th width="1%" nowrap="nowrap"><?php echo JHTML::_('grid.sort', 'COM_JEM_ID', 'l.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="12">
				<?php echo $this->pagination->getListFooter(); ?>
			</td>
		</tr>
	</tfoot>

	<tbody>
		<?php
		$k = 0;
		for ($i=0, $n=count( $this->rows ); $i < $n; $i++) {
			$row = &$this->rows[$i];
			$link 		= 'index.php?option=com_jem&amp;controller=venues&amp;task=edit&amp;cid[]='. $row->id;
			//$checked 	= JHTML::_('grid.checkedout', $row, $i );
			$published 	= JHTML::_('grid.published', $row, $i );
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td><?php echo JHtml::_('grid.id', $i, $row->id); ?></td>
			<td align="left">
				<?php
				if ( $row->checked_out && ( $row->checked_out != $this->user->get('id') ) ) {
					echo htmlspecialchars($row->venue, ENT_QUOTES, 'UTF-8');
				} else {
					?>
					<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_EDIT_VENUE' );?>::<?php echo $row->venue; ?>">
					<a href="<?php echo $link; ?>">
					<?php echo htmlspecialchars($row->venue, ENT_QUOTES, 'UTF-8'); ?>
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
			<td align="left">
				<?php
				if ($row->url) {
				?>
					<a href="<?php echo htmlspecialchars($row->url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
						<?php
						if (JString::strlen($row->url) > 25) {
							echo JString::substr( htmlspecialchars($row->url, ENT_QUOTES, 'UTF-8'), 0 , 25).'...';
						} else {
							echo htmlspecialchars($row->url, ENT_QUOTES, 'UTF-8');
						}
						?>
					</a>
				<?php
				} else {
					echo  '-';
				}
				?>
			</td>
			<td align="left"><?php echo $row->city ? htmlspecialchars($row->city, ENT_QUOTES, 'UTF-8') : '-'; ?></td>
			<td align="center"><?php echo $published; ?></td>
			<td>
				<?php echo JText::_( 'COM_JEM_AUTHOR' ).': '; ?><a href="<?php echo 'index.php?option=com_users&amp;task=edit&amp;hidemainmenu=1&amp;cid[]='.$row->created_by; ?>"><?php echo $row->author; ?></a><br />
				<?php echo JText::_( 'COM_JEM_EMAIL' ).': '; ?><a href="mailto:<?php echo $row->email; ?>"><?php echo $row->email; ?></a><br />
				<?php
				$delivertime 	= JHTML::Date( $row->created, JText::_( 'DATE_FORMAT_LC2' ) );
				$edittime 		= JHTML::Date( $row->modified, JText::_( 'DATE_FORMAT_LC2' ) );
				$ip				= $row->author_ip == 'COM_JEM_DISABLED' ? JText::_( 'COM_JEM_DISABLED' ) : $row->author_ip;
				$image 			= JHTML::_('image', 'administrator/templates/'. $this->template .'/images/menu/icon-16-info.png', JText::_('COM_JEM_NOTES') );
				$overlib 		= JText::_( 'COM_JEM_CREATED_AT' ).': '.$delivertime.'<br />';
				$overlib		.= JText::_( 'COM_JEM_WITH_IP' ).': '.$ip.'<br />';
				if ($row->modified != '0000-00-00 00:00:00') {
					$overlib 	.= JText::_( 'COM_JEM_EDITED_AT' ).': '.$edittime.'<br />';
					$overlib 	.= JText::_( 'COM_JEM_EDITED_FROM' ).': '.$row->editor.'<br />';
				}
				?>
				<span class="editlinktip hasTip" title="<?php echo JText::_('COM_JEM_VENUE_STATS'); ?>::<?php echo $overlib; ?>">
					<?php echo $image; ?>
				</span>
			</td>
			<td align="center"><?php echo $row->assignedevents; ?></td>
			<td align="right">
				<?php
				echo $this->pagination->orderUpIcon( $i, true, 'orderup', 'Move Up', $this->ordering );
				?>
			</td>
			<td align="left">
				<?php
				echo $this->pagination->orderDownIcon( $i, $n, true, 'orderdown', 'Move Down', $this->ordering );
				?>
			</td>
			<td align="center"><?php echo $row->id; ?></td>
		</tr>
		<?php $k = 1 - $k; } ?>

	</tbody>

</table>

<p class="copyright">
	<?php echo ELAdmin::footer( ); ?>
</p>

	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_jem" />
	<input type="hidden" name="view" value="venues" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="controller" value="venues" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
</form>