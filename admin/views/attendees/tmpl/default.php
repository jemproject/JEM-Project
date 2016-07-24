<?php
/**
 * @version 2.1.7
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;
JHtml::_('behavior.tooltip');

JHtml::addIncludePath(JPATH_COMPONENT.'/helpers/html');
$user		= JemFactory::getUser();
$userId		= $user->get('id');
$listOrder	= $this->escape($this->state->get('list.ordering'));
$listDirn	= $this->escape($this->state->get('list.direction'));

JFactory::getDocument()->addScriptDeclaration('
	Joomla.submitbutton = function(task)
	{
		document.adminForm.task.value=task;
		if (task == "attendees.export") {
			Joomla.submitform(task, document.getElementById("adminForm"));
			document.adminForm.task.value="";
		} else {
      		Joomla.submitform(task, document.getElementById("adminForm"));
		}
	};
');
?>
<form action="<?php echo JRoute::_('index.php?option=com_jem&view=attendees&eventid='.$this->event->id); ?>"  method="post" name="adminForm" id="adminForm">
	<?php if (isset($this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo $this->sidebar; ?>
	</div>
	<div id="j-main-container" class="span10">
	<?php endif; ?>
		<table class="adminlist" style="width:100%;">
			<tr>
				<td style="width:100%;padding:10px">
					<b><?php echo JText::_('COM_JEM_DATE').':'; ?></b>&nbsp;<?php echo $this->event->dates; ?><br />
					<b><?php echo JText::_('COM_JEM_EVENT_TITLE').':'; ?></b>&nbsp;<?php echo $this->escape($this->event->title); ?>
				</td>
			</tr>
		</table>
		<br />
		<table class="adminform">
			<tr>
				<td width="100%">
					<?php echo JText::_('COM_JEM_SEARCH').' '.$this->lists['filter']; ?>
					<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
					<button class="buttonfilter" type="submit"><?php echo JText::_('JSEARCH_FILTER_SUBMIT'); ?></button>
					<button class="buttonfilter" type="button" onclick="document.id('filter_search').value='';this.form.submit();"><?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?></button>
				</td>
				<td style="text-align:right; white-space:nowrap;">
					<?php echo JText::_('COM_JEM_STATUS').' '.$this->lists['status']; ?>
				</td>
			</tr>
		</table>
		<table class="table table-striped" id="attendeeList">
			<thead>
				<tr>
					<th width="1%" class="center"><?php echo JText::_('COM_JEM_NUM'); ?></th>
					<th width="1%" class="center"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
					<th class="title"><?php echo JHtml::_('grid.sort', 'COM_JEM_NAME', 'u.name', $listDirn, $listOrder); ?></th>
					<th class="title"><?php echo JHtml::_('grid.sort', 'COM_JEM_USERNAME', 'u.username', $listDirn, $listOrder); ?></th>
					<th class="title"><?php echo JText::_('COM_JEM_EMAIL'); ?></th>
					<th class="title"><?php echo JText::_('COM_JEM_IP_ADDRESS'); ?></th>
					<th class="title"><?php echo JHtml::_('grid.sort', 'COM_JEM_REGDATE', 'r.uregdate', $listDirn, $listOrder); ?></th>
					<th class="title center"><?php echo JHtml::_('grid.sort', 'COM_JEM_USER_ID', 'r.uid', $listDirn, $listOrder); ?></th>
					<th class="title center"><?php echo JHtml::_('grid.sort', 'COM_JEM_HEADER_WAITINGLIST_STATUS', 'r.waiting',$listDirn, $listOrder); ?></th>
					<?php if (!empty($this->jemsettings->regallowcomments)) : ?>
					<th class="title"><?php echo JText::_('COM_JEM_COMMENT'); ?></th>
					<?php endif;?>
					<th class="title center"><?php echo JText::_('COM_JEM_REMOVE_USER'); ?></th>
					<th width="1%" class="center nowrap"><?php echo JHtml::_('grid.sort', 'COM_JEM_ATTENDEES_REGID', 'r.id', $listDirn, $listOrder ); ?></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<td colspan="20">
						<?php echo (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks() : $this->pagination->getListFooter()); ?>
					</td>
				</tr>
			</tfoot>
			<tbody>
				<?php
				$canChange = $user->authorise('core.edit.state');

				foreach ($this->items as $i => $row) :
				?>
				<tr class="row<?php echo $i % 2; ?>">
					<td class="center"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
					<td class="center"><?php echo JHtml::_('grid.id', $i, $row->id); ?></td>
					<td><a href="<?php echo JRoute::_('index.php?option=com_jem&task=attendees.edit&cid[]='.$row->id); ?>"><?php echo $row->name; ?></a></td>
					<td><?php echo $row->username; ?></td>
					<td class="email"><a href="mailto:<?php echo $row->email; ?>"><?php echo $row->email; ?></a></td>
					<td><?php echo $row->uip == 'DISABLED' ? JText::_('COM_JEM_DISABLED') : $row->uip; ?></td>
					<td><?php if (!empty($row->uregdate)) { echo JHtml::_('date', $row->uregdate, JText::_('DATE_FORMAT_LC2')); } ?></td>
					<td class="center">
					<a href="<?php echo JRoute::_('index.php?option=com_users&task=user.edit&id='.$row->uid); ?>"><?php echo $row->uid; ?></a>
					</td>
					<td class="center">
						<?php
						$status = (int)$row->status;
						if ($status === 1 && $row->waiting == 1) { $status = 2; }
						echo JHtml::_('jemhtml.toggleAttendanceStatus', $status, $i, $canChange);
						?>
					</td>
					<?php if (!empty($this->jemsettings->regallowcomments)) : ?>
					<?php $cmnt = (strlen($row->comment) > 16) ? (rtrim(substr($row->comment, 0, 14)).'&hellip;') : $row->comment; ?>
					<td><?php if (!empty($cmnt)) { echo JHtml::_('tooltip', $row->comment, null, null, $cmnt, null, null); } ?></td>
					<?php endif; ?>
					<td class="center">
						<a href="javascript: void(0);" onclick="return listItemTask('cb<?php echo $i;?>','attendees.remove')">
							<?php echo JHtml::_('image','com_jem/publish_r.png',JText::_('COM_JEM_REMOVE'),NULL,true); ?>
						</a>
					</td>
					<td class="center">
					<?php echo $this->escape($row->id); ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php if (isset($this->sidebar)) : ?>
	</div>
	<?php endif; ?>

	<?php echo JHtml::_( 'form.token' ); ?>
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="eventid" value="<?php echo $this->event->id; ?>" />
	<input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
</form>