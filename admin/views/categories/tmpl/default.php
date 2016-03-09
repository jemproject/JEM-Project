<?php
/**
 * @version     2.1.6
 * @package     JEM
 * @copyright   Copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright   Copyright (C) 2005-2009 Christoph Lukes
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

// Include the component HTML helpers.
JHtml::addIncludePath(JPATH_COMPONENT.'/helpers/html');
JHtml::_('behavior.tooltip');
JHtml::_('behavior.multiselect');

$user		= JemFactory::getUser();
$userId		= $user->get('id');
$listOrder	= $this->escape($this->state->get('list.ordering'));
$listDirn	= $this->escape($this->state->get('list.direction'));
$ordering 	= ($listOrder == 'a.lft');
$saveOrder 	= ($listOrder == 'a.lft' && strtolower($listDirn) == 'asc');
?>
<form action="<?php echo JRoute::_('index.php?option=com_jem&view=categories');?>" method="post" name="adminForm" id="adminForm">
	<?php if (isset($this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo $this->sidebar; ?>
	</div>
	<div id="j-main-container" class="span10">
	<?php endif; ?>
		<fieldset id="filter-bar">
			<div class="filter-search fltlft">
				<input type="text" name="filter_search" id="filter_search" placeholder="<?php echo JText::_('COM_JEM_SEARCH');?>" value="<?php echo $this->escape($this->state->get('filter.search')); ?>" />
				<button type="submit"><?php echo JText::_('JSEARCH_FILTER_SUBMIT'); ?></button>
				<button type="button" onclick="document.id('filter_search').value='';this.form.submit();"><?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?></button>
			</div>

			<div class="filter-select fltrt">
				<select name="filter_level" class="inputbox" onchange="this.form.submit()">
					<option value=""><?php echo JText::_('JOPTION_SELECT_MAX_LEVELS');?></option>
					<?php echo JHtml::_('select.options', $this->f_levels, 'value', 'text', $this->state->get('filter.level'));?>
				</select>

				<select name="filter_published" class="inputbox" onchange="this.form.submit()">
					<option value=""><?php echo JText::_('JOPTION_SELECT_PUBLISHED');?></option>
					<?php echo JHtml::_('select.options', JHtml::_('jgrid.publishedOptions'), 'value', 'text', $this->state->get('filter.published'), true);?>
				</select>

				<select name="filter_access" class="inputbox" onchange="this.form.submit()">
					<option value=""><?php echo JText::_('JOPTION_SELECT_ACCESS');?></option>
					<?php echo JHtml::_('select.options', JHtml::_('access.assetgroups'), 'value', 'text', $this->state->get('filter.access'));?>
				</select>
			</div>
		</fieldset>
		<div class="clr"> </div>

		<table class="table table-striped" id="articleList">
			<thead>
				<tr>
					<th width="1%">
						<input type="checkbox" name="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
					</th>
					<th>
						<?php echo JHtml::_('grid.sort', 'JGLOBAL_TITLE', 'a.catname', $listDirn, $listOrder); ?>
					</th>
					<th width="5%" nowrap="nowrap">
						<?php echo JText::_( 'COM_JEM_COLOR' ); ?>
					</th>
					<th width="15%"><?php echo JHtml::_('grid.sort', 'COM_JEM_GROUP', 'gr.name', $listDirn, $listOrder ); ?></th>
					<th width="1%" class="center" nowrap="nowrap"><?php echo JText::_( 'COM_JEM_EVENTS' ); ?></th>
					<th width="5%">
						<?php echo JHtml::_('grid.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?>
					</th>
					<th width="10%">
						<?php echo JHtml::_('grid.sort', 'JGRID_HEADING_ORDERING', 'a.lft', $listDirn, $listOrder); ?>
						<?php if ($saveOrder) :?>
							<?php echo JHtml::_('grid.order',  $this->items, 'filesave.png', 'categories.saveorder'); ?>
						<?php endif; ?>
					</th>
					<th class="center" width="10%">
						<?php echo JHtml::_('grid.sort',  'JGRID_HEADING_ACCESS', 'a.access', $listDirn, $listOrder); ?>
					</th>
					<th width="1%" class="nowrap">
						<?php echo JHtml::_('grid.sort',  'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
					</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<td colspan="15">
						<?php echo (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks() : $this->pagination->getListFooter()); ?>
					</td>
				</tr>
			</tfoot>
			<tbody>
				<?php
				$originalOrders = array();
				foreach ($this->items as $i => $item) :
					$orderkey	= array_search($item->id, $this->ordering[$item->parent_id]);
					$canEdit	= $user->authorise('core.edit');
					$canCheckin	= $user->authorise('core.admin', 'com_checkin') || $item->checked_out == $userId || $item->checked_out == 0;
					$canEditOwn	= $user->authorise('core.edit.own') && $item->created_user_id == $userId;
					$canChange	= $user->authorise('core.edit.state') && $canCheckin;
					$grouplink 	= 'index.php?option=com_jem&amp;task=group.edit&amp;id='. $item->groupid;

					if ($item->level > 0) {
						$repeat = $item->level-1;
					} else {
						$repeat = 0;
					}
				?>
					<tr class="row<?php echo $i % 2; ?>">
						<td class="center">
							<?php echo JHtml::_('grid.id', $i, $item->id); ?>
						</td>
						<td>
							<?php echo str_repeat('<span class="gi">|&mdash;</span>', $repeat) ?>
							<?php if ($item->checked_out) : ?>
								<?php echo JHtml::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'categories.', $canCheckin); ?>
							<?php endif; ?>
							<?php if ($canEdit || $canEditOwn) : ?>
								<a href="<?php echo JRoute::_('index.php?option=com_jem&task=category.edit&id='.$item->id);?>">
									<?php echo $this->escape($item->catname); ?></a>
							<?php else : ?>
								<?php echo $this->escape($item->catname); ?>
							<?php endif; ?>
							<p class="smallsub" title="<?php echo $this->escape($item->path);?>">
								<?php echo str_repeat('<span class="gtr">|&mdash;</span>', $repeat) ?>
								<?php if (empty($item->note)) : ?>
									<?php echo JText::sprintf('JGLOBAL_LIST_ALIAS', $this->escape($item->alias));?>
								<?php else : ?>
									<?php echo JText::sprintf('JGLOBAL_LIST_ALIAS_NOTE', $this->escape($item->alias), $this->escape($item->note));?>
								<?php endif; ?></p>
						</td>
						<td class="center">
							<div class="colorpreview" style="width: 20px; cursor:default;background-color: <?php echo ( $item->color == '' )?"transparent":$item->color; ?>;" title="<?php echo $item->color; ?>">
								&nbsp;
							</div>
						</td>
						<td class="center">
							<?php if ($item->catgroup) : ?>
								<span <?php echo JEMOutput::tooltip(JText::_('COM_JEM_GROUP_EDIT'), $item->catgroup, 'editlinktip'); ?>>
								<a href="<?php echo $grouplink; ?>">
									<?php echo $this->escape($item->catgroup); ?>
								</a></span>
							<?php elseif ($item->groupid) : ?>
								<?php echo JText::sprintf('COM_JEM_CATEGORY_UNKNOWN_GROUP', $item->groupid); ?>
							<?php else : ?>
								<?php echo '-'; ?>
							<?php endif; ?>
						</td>
						<td class="center">
							<?php echo $item->assignedevents; ?>
						</td>
						<td class="center">
							<?php echo JHtml::_('jgrid.published', $item->published, $i, 'categories.', $canChange);?>
						</td>
						<td class="order">
							<?php if ($canChange) : ?>
								<?php if ($saveOrder) : ?>
									<span><?php echo $this->pagination->orderUpIcon($i, isset($this->ordering[$item->parent_id][$orderkey - 1]), 'categories.orderup', 'JLIB_HTML_MOVE_UP', $ordering); ?></span>
									<span><?php echo $this->pagination->orderDownIcon($i, $this->pagination->total, isset($this->ordering[$item->parent_id][$orderkey + 1]), 'categories.orderdown', 'JLIB_HTML_MOVE_DOWN', $ordering); ?></span>
								<?php endif; ?>
								<?php $disabled = $saveOrder ?  '' : 'disabled="disabled"'; ?>
								<input type="text" name="order[]" size="5" value="<?php echo $orderkey + 1;?>" <?php echo $disabled ?> class="text-area-order" />
								<?php $originalOrders[] = $orderkey + 1; ?>
							<?php else : ?>
								<?php echo $orderkey + 1; ?>
							<?php endif; ?>
						</td>
						<td class="center">
							<?php echo $this->escape($item->access_level); ?>
						</td>
						<td class="center">
							<span title="<?php echo sprintf('%d-%d', $item->lft, $item->rgt); ?>">
								<?php echo (int) $item->id; ?></span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php if (isset($this->sidebar)) : ?>
	</div>
	<?php endif; ?>

	<div>
		<input type="hidden" name="task" value="" />
		<input type="hidden" name="boxchecked" value="0" />
		<input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
		<input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
		<input type="hidden" name="original_order_values" value="<?php echo implode($originalOrders, ','); ?>" />
		<?php echo JHtml::_('form.token'); ?>
	</div>
</form>