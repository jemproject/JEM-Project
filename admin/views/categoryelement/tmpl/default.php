<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>

<form action="index.php?option=com_jem&amp;view=categoryelement&amp;tmpl=component" method="post" name="adminForm" id="adminForm">

<table class="adminform">
	<tr>
		<td width="100%">
			<?php echo JText::_( 'COM_JEM_SEARCH' ); ?>
			<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
			<button onclick="this.form.submit();"><?php echo JText::_( 'COM_JEM_GO' ); ?></button>
			<button onclick="this.form.getElementById('search').value='';this.form.submit();"><?php echo JText::_( 'COM_JEM_RESET' ); ?></button>
		</td>
		<td nowrap="nowrap"><?php  echo $this->lists['state']; ?></td>
	</tr>
</table>

<table class="table table-striped" id="articleList">
	<thead>
		<tr>
			<th width="7" class="center"><?php echo JText::_( 'COM_JEM_NUM' ); ?></th>
			<th align="left" class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_CATEGORY', 'catname', $this->lists['order_Dir'], $this->lists['order'], 'categoryelement' ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JText::_( 'COM_JEM_ACCESS' ); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JText::_( 'COM_JEM_PUBLISHED' ); ?></th>
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
		$i = 0;
		$n = count($this->rows);
		foreach ($this->rows as $row) {

			$access = $row->groupname;
   		?>
		<tr class="<?php echo "row$k"; ?>">
			<td class="center" width="7"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td align="left">
				<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SELECT' );?>::<?php echo $row->catname; ?>">
				<?php echo $row->treename; ?>
				<a style="cursor:pointer" onclick="window.parent.elSelectCategory('<?php echo $row->id; ?>', '<?php echo str_replace( array("'", "\""), array("\\'", ""), $row->catname ); ?>');">
					<?php echo htmlspecialchars($row->catname, ENT_QUOTES, 'UTF-8'); ?>
				</a></span>
			</td>
			<td class="center"><?php echo $access; ?></td>
			<td class="center">
				<?php
				$img = $row->published ? 'tick.png' : 'publish_x.png';
				$alt = $row->published ? 'Published' : 'Unpublished';
				?>
				<img src="../media/com_jem/images/<?php echo $img;?>" width="16" height="16" border="0" alt="<?php echo $alt;?>" />
			</td>
		</tr>
		<?php 
		$k = 1 - $k;
        $i++;
		}
		?>
	</tbody>

</table>

<p class="copyright">
	<?php echo JEMAdmin::footer( ); ?>
</p>

<input type="hidden" name="task" value="">
<input type="hidden" name="tmpl" value="component">
<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="" />
</form>