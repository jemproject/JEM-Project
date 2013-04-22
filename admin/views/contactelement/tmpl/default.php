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
?>

<form action="index.php?option=com_jem&amp;view=contactelement&amp;tmpl=component" method="post" name="adminForm" id="adminForm">

<table class="adminform">
	<tr>
		<td width="100%">
			<?php echo JText::_( 'COM_JEM_SEARCH' ).' '.$this->lists['filter']; ?>
			<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
			<button onclick="this.form.submit();"><?php echo JText::_( 'COM_JEM_GO' ); ?></button>
			<button onclick="this.form.getElementById('search').value='';this.form.submit();"><?php echo JText::_( 'COM_JEM_RESET' ); ?></button>
		</td>
		<td nowrap="nowrap">
			 <?php echo $this->lists['state']; ?>
		</td>
	</tr>
</table>

<table class="adminlist">
	<thead>
		<tr>
			<th width="7"><?php echo JText::_( 'COM_JEM_NUM' ); ?></th>
			<th align="left" class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_NAME', 'con.name', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th align="left" class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_ADDRESS', 'con.address', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th align="left" class="title"><?php echo JText::_( 'COM_JEM_EMAIL' ); ?></th>
			<th align="left" class="title"><?php echo JText::_( 'COM_JEM_TELEPHONE' ); ?></th>
			<th class="title"><?php echo JText::_( 'COM_JEM_PUBLISHED' ); ?></th>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="6">
				<?php echo $this->pagination->getListFooter(); ?>
			</td>
		</tr>
	</tfoot>

	<tbody>
		<?php
		$k = 0;
		for ($i=0, $n=count( $this->rows ); $i < $n; $i++) {
			$row = $this->rows[$i];
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td align="left">
				<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SELECT' );?>::<?php echo $row->name; ?>">
				<a style="cursor:pointer" onclick="window.parent.elSelectContact('<?php echo $row->id; ?>', '<?php echo str_replace( array("'", "\""), array("\\'", ""), $row->name ); ?>');">
				<?php echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8'); ?>
				</a></span>
			</td>
			<td align="left"><?php echo htmlspecialchars($row->address, ENT_QUOTES, 'UTF-8'); ?></td>
			<td align="left"><?php echo htmlspecialchars($row->email_to, ENT_QUOTES, 'UTF-8'); ?></td>
			<td align="left"><?php echo htmlspecialchars($row->telephone, ENT_QUOTES, 'UTF-8'); ?></td>
			<td>
				<?php $img = $row->published ? 'tick.png' : 'publish_x.png'; ?>
				<img src="../media/com_jem/images/<?php echo $img;?>" width="16" height="16" border="0" alt="" />
			</td>
		</tr>

		<?php $k = 1 - $k; } ?>

	</tbody>

</table>

<p class="copyright">
	<?php echo JEMAdmin::footer( ); ?>
</p>

<input type="hidden" name="task" value="" />
<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
</form>