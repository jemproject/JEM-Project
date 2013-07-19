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

<form action="<?php echo JRoute::_('index.php?option=com_jem&view=groups'); ?>"  method="post" name="adminForm" id="adminForm">

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

	<table class="table table-striped" id="articleList">
	<thead>
		<tr>
			<th width="5" class="center"><?php echo JText::_( 'COM_JEM_NUM' ); ?></th>
			<th width="5" class="center"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
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
	    foreach ($this->rows as $i => $row) :
	    $link 		= 'index.php?option=com_jem&amp;task=groups.edit&amp;cid[]='.$row->id;
		
   		?>
			<tr class="row<?php echo $i % 2; ?>">
			<td class="center"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td class="center"><?php echo JHtml::_('grid.id', $i, $row->id); ?></td>
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
		<?php endforeach; ?>

	</tbody>

</table>

<p class="copyright">
	<?php echo JEMAdmin::footer( ); ?>
</p>

<input type="hidden" name="boxchecked" value="0" />
<input type="hidden" name="controller" value="groups" />
<input type="hidden" name="task" value="" />
<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
</form>