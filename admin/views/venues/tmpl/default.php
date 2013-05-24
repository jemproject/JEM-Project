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

defined('_JEXEC') or die;
?>

<form action="<?php echo JRoute::_('index.php?option=com_jem&view=venues'); ?>" method="post" name="adminForm" id="adminForm">

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

<table class="table table-striped" id="articleList">
	<thead>
		<tr>
			<th width="1%" class="center"><?php echo JText::_( 'COM_JEM_NUM' ); ?></th>
			<th width="1%" class="center"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_VENUE', 'l.venue', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="20%"><?php echo JHTML::_('grid.sort', 'COM_JEM_ALIAS', 'l.alias', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th><?php echo JText::_( 'COM_JEM_WEBSITE' ); ?></th>
			<th><?php echo JHTML::_('grid.sort', 'COM_JEM_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th><?php echo JHTML::_('grid.sort', 'COM_JEM_STATE', 'l.state', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%"><?php echo JHTML::_('grid.sort', 'COM_JEM_COUNTRY', 'l.country', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th width="1%" class="center" nowrap="nowrap"><?php echo JText::_( 'JSTATUS' ); ?></th>
			<th><?php echo JText::_( 'COM_JEM_CREATION' ); ?></th>
			<th width="1%" class="center" nowrap="nowrap"><?php echo JText::_( 'COM_JEM_EVENTS' ); ?></th>
		    <th width="8%" colspan="2"><?php echo JHTML::_('grid.sort', 'COM_JEM_REORDER', 'l.ordering', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
		    <th width="1%" class="center" nowrap="nowrap"><?php echo JHTML::_('grid.sort', 'COM_JEM_ID', 'l.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="20">
				<?php echo $this->pagination->getListFooter(); ?>
			</td>
		</tr>
	</tfoot>

	<tbody>
		<?php
		foreach ($this->rows as $i => $row) :
			$link 		= 'index.php?option=com_jem&amp;controller=venues&amp;task=edit&amp;cid[]='. $row->id;
			$published 	= JHTML::_('grid.published', $row, $i );
   		?>
		<tr class="row<?php echo $i % 2; ?>">
			<td class="center"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td class="center"><?php echo JHtml::_('grid.id', $i, $row->id); ?></td>
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
			<td align="left"><?php echo $row->state ? htmlspecialchars($row->state, ENT_QUOTES, 'UTF-8') : '-'; ?></td>
			<td class="center"><?php echo $row->country ? htmlspecialchars($row->country, ENT_QUOTES, 'UTF-8') : '-'; ?></td>
			<td class="center"><?php echo $published; ?></td>
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
			<td class="center"><?php echo $row->assignedevents; ?></td>
			<td align="right">
				<?php
				echo $this->pagination->orderUpIcon( $i, true, 'orderup', 'Move Up', $this->ordering );
				?>
			</td>
			<td align="left">
				<?php
				echo $this->pagination->orderDownIcon( $i,$this->pagination->total, true, 'orderdown', 'Move Down', $this->ordering );
				?>
			</td>
			<td class="center"><?php echo $row->id; ?></td>
		</tr>
		<?php endforeach; ?>

	</tbody>

</table>

<p class="copyright">
	<?php echo JEMAdmin::footer( ); ?>
</p>

	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="controller" value="venues" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
</form>