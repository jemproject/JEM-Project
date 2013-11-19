<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

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

<form action="index.php?option=com_jem&amp;view=editevent&amp;layout=choosevenue&amp;tmpl=component" method="post" name="adminForm" id="adminForm">

<div id="jem_filter" class="floattext">
		<div class="jem_fleft">
			<?php
			echo '<label for="filter_type">'.JText::_('COM_JEM_FILTER').'</label>&nbsp;';
			echo $this->searchfilter.'&nbsp;';
			?>
			<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->filter;?>" class="inputbox" onchange="document.adminForm.submit();" />
			<button class="buttonfilter" type="submit"><?php echo JText::_('COM_JEM_GO'); ?></button>
			<button class="buttonfilter" type="button" onclick="document.id('filter_search').value='';this.form.submit();"><?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?></button>
			<button class="buttonfilter" type="button" onclick="if (window.parent) window.parent.elSelectVenue('', '<?php echo JText::_('COM_JEM_SELECTVENUE') ?>');"><?php echo JText::_('COM_JEM_NOVENUE')?></button>
		</div>
		<div class="jem_fright">
			<?php
			echo '<label for="limit">'.JText::_('COM_JEM_DISPLAY_NUM').'</label>&nbsp;';
			echo $this->pagination->getLimitBox();
			?>
		</div>

</div>

<table class="eventtable" style="width:100%" summary="jem">
	<thead>
		<tr>
			<th width="7" class="sectiontableheader" align="left"><?php echo JText::_( 'COM_JEM_NUM' ); ?></th>
			<th align="left" class="sectiontableheader" align="left"><?php echo JHtml::_('grid.sort', 'COM_JEM_VENUE', 'l.venue', $this->lists['order_Dir'], $this->lists['order'], 'selectvenue' ); ?></th>
			<th align="left" class="sectiontableheader" align="left"><?php echo JHtml::_('grid.sort', 'COM_JEM_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order'], 'selectvenue' ); ?></th>
			<th align="left" class="sectiontableheader" align="left"><?php echo JHtml::_('grid.sort', 'COM_JEM_STATE', 'l.state', $this->lists['order_Dir'], $this->lists['order']); ?></th>
			<th align="left" class="sectiontableheader" align="left"><?php echo JText::_( 'COM_JEM_COUNTRY' ); ?></th>
		</tr>
	</thead>

	<tbody>
		<?php foreach ($this->rows as $i => $row) : ?>
		<tr class="row<?php echo $i % 2; ?>">
			<td><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td align="left">
			 <a style="cursor:pointer" onclick="if (window.parent) window.parent.elSelectVenue('<?php echo $row->id; ?>', '<?php echo $this->escape(addslashes($row->venue)); ?>');"><?php echo $this->escape($row->venue); ?></a>
			</td>
			<td align="left"><?php echo $this->escape($row->city); ?></td>
			<td align="left"><?php echo $this->escape($row->state); ?></td>
			<td align="left"><?php echo $this->escape($row->country); ?></td>
		</tr>
		<?php endforeach; ?>
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



<div class="copyright">
<?php echo JEMOutput::footer();	?>
</div>
</div>
