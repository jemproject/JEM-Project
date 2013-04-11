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
			<?php echo JText::_( 'COM_JEM_SEARCH' );?>
			<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
			<button onclick="document.adminForm.submit();"><?php echo JText::_( 'COM_JEM_GO' ); ?></button>
			<button onclick="$('search').value='';document.adminForm.submit();"><?php echo JText::_( 'COM_JEM_RESET' ); ?></button>
		</td>
	</tr>
</table>

<table class="adminlist" cellspacing="1">
	<thead>
		<tr>
			<th width="5"><?php echo JText::_( 'COM_JEM_NUM' ); ?></th>
			<th width="5"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
			<th width="30%" class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_GROUP_NAME', 'name', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th><?php echo JText::_( 'COM_JEM_DESCRIPTION' ); ?></th>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="4">
				<?php echo $this->pagination->getListFooter(); ?>
			</td>
		</tr>
	</tfoot>

	<tbody>
		<?php
		$k = 0;
		for($i=0, $n=count( $this->rows ); $i < $n; $i++) {
			$row = &$this->rows[$i];

			$link 		= 'index.php?option=com_jem&amp;controller=groups&amp;task=edit&amp;cid[]='.$row->id;
			//$checked 	= JHTML::_('grid.checkedout', $row, $i );
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td><?php echo JHtml::_('grid.id', $i, $row->id); ?></td>
			<td>
				<?php
					if ( $row->checked_out && ( $row->checked_out != $this->user->get('id') ) ) {
						echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8');
					} else {
				?>
				<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_EDIT_GROUP' );?>::<?php echo $row->name; ?>">
				<a href="<?php echo $link; ?>">
				<?php echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8'); ?>
				</a></span>
				<?php } ?>
			</td>
			<td><?php echo htmlspecialchars($row->description, ENT_QUOTES, 'UTF-8'); ?></td>
		</tr>
		<?php $k = 1 - $k;  } ?>

	</tbody>

</table>

<p class="copyright">
	<?php echo ELAdmin::footer( ); ?>
</p>

<input type="hidden" name="boxchecked" value="0" />
<input type="hidden" name="option" value="com_jem" />
<input type="hidden" name="controller" value="groups" />
<input type="hidden" name="view" value="groups" />
<input type="hidden" name="task" value="" />
<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
</form>