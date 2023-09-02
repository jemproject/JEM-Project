<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

$function = Factory::getApplication()->input->getCmd('function', 'jSelectCategory');
?>

<form action="index.php?option=com_jem&amp;view=categoryelement&amp;tmpl=component" method="post" name="adminForm" id="adminForm">

<table class="adminform">
	<tr>
		<td width="100%">
			<input type="text" name="filter_search" id="filter_search" placeholder="<?php echo Text::_('COM_JEM_SEARCH');?>" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
			<button type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
			<button type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
			<button type="button" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('', '<?php echo Text::_('COM_JEM_SELECT_CATEGORY') ?>');"><?php echo Text::_('COM_JEM_GLOBAL_NOCATEGORY')?></button>
		</td>
		<td nowrap="nowrap">
			<select name="filter_state" class="inputbox" onchange="this.form.submit()">
			<option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED');?></option>
			<?php echo JHtml::_('select.options', JHtml::_('jgrid.publishedOptions',array('all' => 0, 'unpublished' => 0,'archived' => 0, 'trash' => 0)), 'value', 'text', $this->filter_state, true);?>
			</select>
		</td>
	</tr>
</table>

<table class="table table-striped" id="articleList">
	<thead>
		<tr>
			<th width="7" class="center"><?php echo Text::_('COM_JEM_NUM'); ?></th>
			<th align="left" class="title"><?php echo JHtml::_('grid.sort','COM_JEM_CATEGORY','c.catname',$this->lists['order_Dir'],$this->lists['order']); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo Text::_('COM_JEM_ACCESS'); ?></th>
			<th width="1%" nowrap="nowrap"><?php echo Text::_('JSTATUS'); ?></th>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="4">
				<?php echo (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks() : $this->pagination->getListFooter()); ?>
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
				<a class="pointer" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('<?php echo $row->id; ?>', '<?php echo $this->escape(addslashes($row->catname)); ?>');"><?php echo htmlspecialchars_decode($this->escape($row->treename)); ?></a>
			</td>
			<td class="center"><?php echo $access; ?></td>
			<td class="center">
				<?php echo JHtml::_('jgrid.published', $row->published, $i,'',false); ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>

</table>

<div class="copyright">
	<?php echo JemAdmin::footer( ); ?>
</div>

<input type="hidden" name="task" value="">
<input type="hidden" name="tmpl" value="component">
<input type="hidden" name="function" value="<?php echo $this->escape($function); ?>" />
<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
</form>
