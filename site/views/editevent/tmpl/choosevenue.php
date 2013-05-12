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
defined('_JEXEC') or die;
?>

<script type="text/javascript">

	function tableOrdering( order, dir, view )
	{
		var form = document.getElementById("adminForm");

		form.filter_order.value 	= order;
		form.filter_order_Dir.value	= dir;
		form.submit( view );
	}
</script>

<div id="jem" class="jem_select_venue">

<h1 class='componentheading'>
	<?php
		echo JText::_('COM_JEM_SELECTVENUE');
	?>
</h1>

<div class="clear"></div>

<form action="index.php?option=com_jem&amp;view=editevent&amp;layout=choosevenue&amp;tmpl=component" method="post" id="adminForm">

<div id="jem_filter" class="floattext">
		<div class="jem_fleft">
			<?php
			echo '<label for="filter_type">'.JText::_('COM_JEM_FILTER').'</label>&nbsp;';
			echo $this->searchfilter.'&nbsp;';
			?>
			<input type="text" name="filter" id="filter" value="<?php echo $this->filter;?>" class="text_area" onchange="document.getElementById('adminForm').submit();" />
			<button onclick="document.getElementById('adminForm').submit();"><?php echo JText::_( 'COM_JEM_GO' ); ?></button>
			<button onclick="document.getElementById('filter').value='';document.getElementById('adminForm').submit();"><?php echo JText::_( 'COM_JEM_RESET' ); ?></button>
		</div>
		<div class="jem_fright">
			<?php
			echo '<label for="limit">'.JText::_('COM_JEM_DISPLAY_NUM').'</label>&nbsp;';
			echo $this->pagination->getLimitBox();
			?>
		</div>

</div>

<table class="eventtable" width="100%" summary="jem">
	<thead>
		<tr>
			<th width="7" class="sectiontableheader" align="left"><?php echo JText::_( 'COM_JEM_NUM' ); ?></th>
			<th align="left" class="sectiontableheader" align="left"><?php echo JHTML::_('grid.sort', 'COM_JEM_VENUE', 'l.venue', $this->lists['order_Dir'], $this->lists['order'], 'selectvenue' ); ?></th>
			<th align="left" class="sectiontableheader" align="left"><?php echo JHTML::_('grid.sort', 'COM_JEM_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order'], 'selectvenue' ); ?></th>
			<th align="left" class="sectiontableheader" align="left"><?php echo JText::_( 'COM_JEM_COUNTRY' ); ?></th>
		</tr>
	</thead>

	<tbody>
		<?php
		$k = 0;
		for ($i=0, $n=count( $this->rows ); $i < $n; $i++) {
			$row = $this->rows[$i];
		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td align="left">
				<a style="cursor:pointer" onclick="window.parent.elSelectVenue('<?php echo $row->id; ?>', '<?php echo str_replace( array("'", "\""), array("\\'", ""), $row->venue); ?>');">
						<?php echo $this->escape($row->venue); ?>
				</a>
			</td>
			<td align="left"><?php echo $this->escape($row->city); ?></td>
			<td align="left"><?php echo $row->country; ?></td>
		</tr>
		<?php $k = 1 - $k; } ?>
	</tbody>
</table>

<p>
<input type="hidden" name="task" value="selectvenue" />
<input type="hidden" name="option" value="com_jem" />
<input type="hidden" name="tmpl" value="component" />
<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="" />
</p>
</form>

<div class="pagination">
	<?php echo $this->pagination->getPagesLinks(); ?>
</div>



<p class="copyright">
<?php echo JEMOutput::footer();	?>
</p>
</div>
