<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

$function = JRequest::getCmd('function', 'jSelectContact');

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

<div id="jem" class="jem_select_contact">

<h1 class='componentheading'>
	<?php
		echo JText::_('COM_JEM_SELECT_CONTACT');
	?>
</h1>

<div class="clear"></div>

<form action="index.php?option=com_jem&amp;view=editevent&amp;layout=choosecontact&amp;tmpl=component" method="post" name="adminForm" id="adminForm">

<div id="jem_filter" class="floattext">
	<div class="jem_fleft">
			<?php
			echo '<label for="filter_type">'.JText::_('COM_JEM_FILTER').'</label>&nbsp;';
			echo $this->searchfilter.'&nbsp;';
			?>
			<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search']; ?>" class="inputbox" onChange="document.adminForm.submit();" />
			<button type="submit"><?php echo JText::_('COM_JEM_GO'); ?></button>
			<button type="button" onclick="document.id('filter_search').value='';this.form.submit();"><?php echo JText::_('COM_JEM_RESET'); ?></button>
			<button type="button" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('', '<?php echo JText::_('COM_JEM_SELECT_CONTACT') ?>');"><?php echo JText::_('COM_JEM_NOCONTACT')?></button>
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
			<th width="7" class="sectiontableheader"><?php echo JText::_('COM_JEM_NUM'); ?></th>
			<th align="left" class="sectiontableheader"><?php echo JHtml::_('grid.sort', 'COM_JEM_NAME', 'con.name', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th align="left" class="sectiontableheader"><?php echo JHtml::_('grid.sort', 'COM_JEM_ADDRESS', 'con.address', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th align="left" class="sectiontableheader"><?php echo JHtml::_('grid.sort', 'COM_JEM_CITY', 'con.suburb', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th align="left" class="sectiontableheader"><?php echo JHtml::_('grid.sort', 'COM_JEM_STATE', 'con.state', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			<th align="left" class="sectiontableheader"><?php echo JText::_('COM_JEM_EMAIL'); ?></th>
			<th align="left" class="sectiontableheader"><?php echo JText::_('COM_JEM_TELEPHONE'); ?></th>
			<th class="title center"><?php echo JText::_('JPUBLISHED'); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($this->rows as $i => $row) : ?>
		 <tr class="row<?php echo $i % 2; ?>">
			<td class="center"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td align="left">
				<span class="editlinktip hasTip" title="<?php echo JText::_('COM_JEM_SELECT');?>::<?php echo $row->name; ?>">
				<a style="cursor:pointer;" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('<?php echo $row->id; ?>', '<?php echo $this->escape(addslashes($row->name)); ?>');"><?php echo $this->escape($row->name); ?></a>
				</span>
			</td>
			<td align="left"><?php echo htmlspecialchars($row->address, ENT_QUOTES, 'UTF-8'); ?></td>
			<td align="left"><?php echo htmlspecialchars($row->suburb, ENT_QUOTES, 'UTF-8'); ?></td>
			<td align="left"><?php echo htmlspecialchars($row->state, ENT_QUOTES, 'UTF-8'); ?></td>
			<td align="left"><?php echo htmlspecialchars($row->email_to, ENT_QUOTES, 'UTF-8'); ?></td>
			<td align="left"><?php echo htmlspecialchars($row->telephone, ENT_QUOTES, 'UTF-8'); ?></td>
			<td class="center">
				<?php $img = $row->published ? 'tick.png' : 'publish_x.png'; ?>
				<?php echo JHtml::_('image', 'com_jem/'.$img, NULL, NULL, true); ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<input type="hidden" name="task" value="selectcontact" />
<input type="hidden" name="option" value="com_jem" />
<input type="hidden" name="tmpl" value="component" />
<input type="hidden" name="function" value="<?php echo $this->escape($function); ?>" />
<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
</form>
<div class="pagination">
	<?php echo $this->pagination->getPagesLinks(); ?>
</div>
<div class="copyright">
<?php echo JEMOutput::footer();	?>
</div>
</div>