<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * 
 * @todo change "ALT" of publish/unpublish to text-strings
 */

defined('_JEXEC') or die;

$function = JRequest::getCmd('function', 'jSelectCategory');
?>

<form action="index.php?option=com_jem&amp;view=categoryelement&amp;tmpl=component" method="post" name="adminForm" id="adminForm">

<table class="adminform">
	<tr>
		<td width="100%">
			<?php echo JText::_('COM_JEM_SEARCH'); ?>
			<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
			<button type="submit"><?php echo JText::_('COM_JEM_GO'); ?></button>
			<button type="button" onclick="document.id('filter_search').value='';this.form.submit();"><?php echo JText::_('COM_JEM_RESET'); ?></button>
			<button type="button" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('', '<?php echo JText::_('COM_JEM_SELECT_CATEGORY') ?>');"><?php echo JText::_('COM_JEM_NOCATEGORY')?></button>
		</td>
		<td nowrap="nowrap"><?php  echo $this->lists['state']; ?></td>
	</tr>
</table>

<table class="table table-striped" id="articleList">
	<thead>
		<tr>
			<th width="7" class="center"><?php echo JText::_('COM_JEM_NUM'); ?></th>
			<th align="left" class="title"><?php echo JHtml::_('grid.sort','COM_JEM_CATEGORY','catname',$this->lists['order_Dir'],$this->lists['order'],'categoryelement'); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JText::_('COM_JEM_ACCESS'); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo JText::_('COM_JEM_PUBLISHED'); ?></th>
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
			$access = $row->groupname;
   		?>
		 <tr class="row<?php echo $i % 2; ?>">
			<td class="center" width="7"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td align="left">
				<?php echo $row->treename; ?>
				<a class="pointer" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('<?php echo $row->id; ?>', '<?php echo $this->escape(addslashes($row->catname)); ?>');"><?php echo $this->escape($row->catname); ?></a>
			</td>
			<td class="center"><?php echo $access; ?></td>
			<td class="center">
				<?php
				$img = $row->published ? 'tick.png' : 'publish_x.png';
				$alt = $row->published ? 'Published' : 'Unpublished';
				echo JHtml::_('image','com_jem/'.$img,$alt,NULL,true); 
				?>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>

</table>

<div class="copyright">
	<?php echo JEMAdmin::footer( ); ?>
</div>

<input type="hidden" name="task" value="">
<input type="hidden" name="tmpl" value="component">
<input type="hidden" name="function" value="<?php echo $this->escape($function); ?>" />
<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="" />
</form>