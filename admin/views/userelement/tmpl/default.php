<?php
/**
 * @version 2.2.3
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

JHtml::_('behavior.tooltip');
?>

<form action="index.php?option=com_jem&amp;view=userelement&tmpl=component" method="post" id="adminForm" name="adminForm">

	<table class="adminform">
		<tr>
			<td width="100%">
				<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
				<button class="buttonfilter" type="submit"><?php echo JText::_('JSEARCH_FILTER_SUBMIT'); ?></button>
				<button class="buttonfilter" type="button" onclick="document.id('filter_search').value='';this.form.submit();"><?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?></button>
			</td>
		</tr>
	</table>

	<table class="table table-striped" id="articleList">
		<thead>
			<tr>
				<th class="center" width="5"><?php echo JText::_('COM_JEM_NUM'); ?></th>
				<th class="title"><?php echo JHtml::_('grid.sort', 'Name', 'u.name', $this->lists['order_Dir'], $this->lists['order'], 'selectuser' ); ?></th>
				<th class="title"><?php echo JHtml::_('grid.sort', 'Username', 'u.username', $this->lists['order_Dir'], $this->lists['order'], 'selectuser' ); ?></th>
				<th class="title"><?php echo JHtml::_('grid.sort', 'Email', 'u.email', $this->lists['order_Dir'], $this->lists['order'], 'selectuser' ); ?></th>
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
		$k = 0;
		for ($i = 0, $n = is_array($this->rows) ? count($this->rows) : 0; $i < $n; $i++) {
			$row = $this->rows[$i];
		?>
			<tr class="<?php echo "row$k"; ?>">
				<td class="center"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
				<td>
					<span <?php echo JemOutput::tooltip(JText::_('COM_JEM_SELECT'), $row->name, 'editlinktip'); ?>>
					<a style="cursor:pointer" onclick="window.parent.modalSelectUser('<?php echo $row->id; ?>', '<?php echo str_replace( array("'", "\""), array("\\'", ""), $row->name ); ?>');">
						<?php echo $this->escape($row->name); ?>
					</a></span>
				</td>
				<td><?php echo $row->username; ?></td>
				<td><?php echo $row->email; ?></td>
			</tr>
		<?php
			$k = 1 - $k;
		}
		?>
		</tbody>

	</table>

	<div class="copyright">
		<?php echo JemAdmin::footer( ); ?>
	</div>

	<input type="hidden" name="task" value="selectuser" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
</form>