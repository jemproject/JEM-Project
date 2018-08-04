<?php
/**
 * @version 2.1.7
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

$function = JFactory::getApplication()->input->getCmd('function', 'jSelectUsers');
$checked = 0;

JHtml::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.'/helpers/html');

// Get the form.
JForm::addFormPath(JPATH_COMPONENT . '/models/forms');
$form = JForm::getInstance('com_jem.addusers', 'addusers');

if (empty($form)) {
	return false;
}
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
<script type="text/javascript">
	function checkList(form)
	{
		var r='', i, n, e;
		for (i=0, n=form.elements.length; i<n; i++)
		{
			e = form.elements[i];
			if (e.type == 'checkbox' && e.id.indexOf('cb') === 0 && e.checked)
			{
				if (r) { r += ','; }
				r += e.value;
			}
		}
		return r;
	}
</script>

<div id="jem" class="jem_select_users">
	<h1 class='componentheading'>
		<?php echo JText::_('COM_JEM_SELECT_USERS_AND_STATUS'); ?>
	</h1>

	<div class="jem_fright">
		<button type="button" class="pointer" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>_newusers(checkList(document.adminForm), document.adminForm.boxchecked.value, document.adminForm.status.value, <?php echo $this->event->id; ?>, '<?php echo JSession::getFormToken(); ?>');">
			<?php echo JText::_('COM_JEM_SAVE'); ?>
		</button>
	</div>

	<div class="clr"></div>

	<form action="<?php echo JRoute::_('index.php?option=com_jem&view=attendees&layout=addusers&tmpl=component&function='.$this->escape($function).'&id='.$this->event->id.'&'.JSession::getFormToken().'=1'); ?>" method="post" name="adminForm" id="adminForm">
		<ul class="adminformlist">
			<li><?php echo $form->getLabel('status'); ?><?php echo $form->getInput('status'); ?></li>
		</ul>

		<?php if(1) : ?>
		<div id="jem_filter" class="floattext">
			<div class="jem_fleft">
				<?php
				echo '<label for="filter_type">'.JText::_('COM_JEM_FILTER').'</label>&nbsp;';
				echo $this->searchfilter.'&nbsp;';
				?>
				<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search']; ?>" class="inputbox" onChange="document.adminForm.submit();" />
				<button type="submit" class="pointer"><?php echo JText::_('JSEARCH_FILTER_SUBMIT'); ?></button>
				<button type="button" class="pointer" onclick="document.id('filter_search').value='';this.form.submit();"><?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?></button>
			</div>
			<div class="jem_fright">
				<?php
				echo '<label for="limit">'.JText::_('COM_JEM_DISPLAY_NUM').'</label>&nbsp;';
				echo $this->pagination->getLimitBox();
				?>
			</div>
		</div>
		<?php endif; ?>

		<table class="eventtable" style="width:100%" summary="jem">
			<thead>
				<tr>
					<th width="1%" class="sectiontableheader"><?php echo JText::_('COM_JEM_NUM'); ?></th>
					<th width="1%" class="center"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
					<th align="left" class="sectiontableheader"><?php echo JText::_('COM_JEM_NAME'); ?></th>
					<th width="10%" class="center"><?php echo JText::_('COM_JEM_STATUS'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($this->rows)) : ?>
					<tr align="center"><td colspan="0"><?php echo JText::_('COM_JEM_NOUSERS'); ?></td></tr>
				<?php else :?>
					<?php foreach ($this->rows as $i => $row) : ?>
					<tr class="row<?php echo $i % 2; ?>">
						<td class="center"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
						<td class="center"><?php echo JHtml::_('grid.id', $i, $row->id); ?></td>
						<td align="left"><?php echo $this->escape($row->name); ?></td>
						<td class="center"><?php echo JHtml::_('jemhtml.toggleAttendanceStatus', $row->status, 0, false); ?></td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<input type="hidden" name="task" value="selectusers" />
		<input type="hidden" name="option" value="com_jem" />
		<input type="hidden" name="tmpl" value="component" />
		<input type="hidden" name="function" value="<?php echo $this->escape($function); ?>" />
		<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
		<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
		<input type="hidden" name="boxchecked" value="<?php echo $checked; ?>" />
	</form>

	<div class="pagination">
		<?php echo $this->pagination->getPagesLinks(); ?>
	</div>
</div>