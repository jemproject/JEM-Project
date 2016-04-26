<?php
/**
 * @version 2.1.6
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

$user		= JemFactory::getUser();
$userId		= $user->get('id');
$listOrder	= $this->escape($this->state->get('list.ordering'));
$listDirn	= $this->escape($this->state->get('list.direction'));
$canOrder	= $user->authorise('core.edit.state', 'com_jem.category');
$saveOrder	= $listOrder=='ordering';

$params		= (isset($this->state->params)) ? $this->state->params : new JObject();
?>

<form action="<?php echo JRoute::_('index.php?option=com_jem&view=groups'); ?>" method="post" name="adminForm" id="adminForm">
	<?php if (isset($this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo $this->sidebar; ?>
	</div>
	<div id="j-main-container" class="span10">
	<?php endif; ?>
		<fieldset id="filter-bar">
			<div class="filter-search fltlft">
				<input type="text" name="filter_search" id="filter_search" placeholder="<?php echo JText::_('COM_JEM_SEARCH');?>" value="<?php echo $this->escape($this->state->get('filter_search')); ?>" class="text_area" onChange="document.adminForm.submit();" />
				<button type="submit"><?php echo JText::_('JSEARCH_FILTER_SUBMIT'); ?></button>
				<button type="button" onclick="document.id('filter_search').value='';this.form.submit();"><?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?></button>
			</div>
		</fieldset>
		<div class="clr"> </div>

		<table class="table table-striped" id="articleList">
			<thead>
				<tr>
				<th width="5" class="center"><?php echo JText::_( 'COM_JEM_NUM' ); ?></th>
				<th width="5" class="center"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
				<th width="30%" class="title"><?php echo JHtml::_('grid.sort', 'COM_JEM_GROUP_NAME', 'name', $listDirn, $listOrder ); ?></th>
				<th><?php echo JText::_( 'COM_JEM_DESCRIPTION' ); ?></th>
				</tr>
			</thead>

			<tfoot>
				<tr>
					<td colspan="20">
						<?php echo (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks() : $this->pagination->getListFooter()); ?>
					</td>
				</tr>
			</tfoot>

			<tbody id="seach_in_here">
				<?php foreach ($this->items as $i => $row) :
					$ordering	= ($listOrder == 'ordering');
					$canCreate	= $user->authorise('core.create');
					$canEdit	= $user->authorise('core.edit');
					$canCheckin	= $user->authorise('core.manage',		'com_checkin') || $row->checked_out == $userId || $row->checked_out == 0;
					$canChange	= $user->authorise('core.edit.state') && $canCheckin;

					$link 		= 'index.php?option=com_jem&amp;task=group.edit&amp;id='.$row->id;
				?>
				<tr class="row<?php echo $i % 2; ?>">
					<td class="center"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
					<td class="center"><?php echo JHtml::_('grid.id', $i, $row->id); ?></td>
					<td>
						<?php if ($row->checked_out) : ?>
							<?php echo JHtml::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'groups.', $canCheckin); ?>
						<?php endif; ?>
						<?php if ($canEdit) : ?>
							<a href="<?php echo $link; ?>">
								<?php echo $this->escape($row->name); ?>
							</a>
						<?php else : ?>
								<?php echo $this->escape($row->name); ?>
						<?php endif; ?>
					</td>
					<td>
						<?php
							$desc = $row->description;
							$descoutput = strip_tags($desc);
							echo $this->escape($descoutput);
						?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php if (isset($this->sidebar)) : ?>
	</div>
	<?php endif; ?>

	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
	<?php echo JHtml::_('form.token'); ?>
</form>