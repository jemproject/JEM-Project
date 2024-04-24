<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

// HTMLHelper::_('behavior.tooltip');

HTMLHelper::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.'/helpers/html');

$colspan = ($this->event->waitinglist ? 10 : 9);

$detaillink = Route::_(JemHelperRoute::getEventRoute($this->event->id.':'.$this->event->alias));

$namefield = $this->settings->get('global_regname', '1') ? 'name' : 'username';
$namelabel = $this->settings->get('global_regname', '1') ? 'COM_JEM_NAME' : 'COM_JEM_USERNAME';
?>
<script type="text/javascript">
	function tableOrdering(order, dir, view)
	{
		var form = document.getElementById("adminForm");

		form.filter_order.value     = order;
		form.filter_order_Dir.value = dir;
		form.submit(view);
	}
</script>
<script type="text/javascript">
	function jSelectUsers_newusers(ids, count, status, places, eventid, token) {
		document.location.href = 'index.php?option=com_jem&task=attendees.attendeeadd&id='+eventid+'&status='+status+'&places='+places+'&uids='+ids+'&'+token+'=1';
		SqueezeBox.close();
	}
</script>

<div id="jem" class="jem_attendees<?php echo $this->pageclass_sfx;?>">
	<div class="buttons">
		<?php
		$permissions = new stdClass();
		$permissions->canAddUsers = true;
		$btn_params = array('print_link' => $this->print_link, 'id' => $this->event->id);
		echo JemOutput::createButtonBar($this->getName(), $permissions, $btn_params);
		?>
	</div>

	<?php if ($this->params->get('show_page_heading', 1)) : ?>
	<h1 class="componentheading">
		<?php echo $this->escape($this->params->get('page_heading')); ?>
	</h1>
	<?php endif; ?>

	<div class="clr"></div>

	<?php if ($this->params->get('showintrotext')) : ?>
	<div class="description no_space floattext">
		<?php echo $this->params->get('introtext'); ?>
	</div>
	<?php endif; ?>

	<h2><?php echo $this->escape($this->event->title); ?></h2>

	<form action="<?php echo htmlspecialchars($this->action); ?>"  method="post" name="adminForm" id="adminForm">
		<table class="adminlist">
			<tr>
				<td width="80%">
					<b><?php echo Text::_('COM_JEM_TITLE').':'; ?></b>&nbsp;
					<a href="<?php echo $detaillink ; ?>"><?php echo $this->escape($this->event->title); ?></a>
					<br />
					<b><?php echo Text::_('COM_JEM_DATE').':'; ?></b>&nbsp;<?php
						echo JemOutput::formatLongDateTime($this->event->dates, $this->event->times, $this->event->enddates, $this->event->endtimes, $this->settings->get('global_show_timedetails', 1)); ?>
				</td>
			</tr>
		</table>
		<br />

		<?php if (empty($this->rows)) : ?>

		<div class="eventtable">
			<strong><i><?php echo Text::_('COM_JEM_ATTENDEES_EMPTY_YET'); ?></i></strong>
		</div>

		<?php else : /* empty($this->rows) */ ?>

		<div id="jem_filter" class="floattext">
			<div class="jem_fleft">
				<label for="filter"><?php echo Text::_('COM_JEM_SEARCH'); ?></label>
				<?php echo $this->lists['filter'].'&nbsp;'; ?>
				<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search']; ?>" class="inputbox" onChange="document.adminForm.submit();" />
				<button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
				<button class="btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
				&nbsp;
			</div>
			<br><br><br>
			<div class="jem_fleft" style="white-space:nowrap;">
				<?php echo Text::_('COM_JEM_STATUS').' '.$this->lists['status']; ?>
			</div>
			<div class="jem_fright">
				<label for="limit"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>
				<?php echo $this->pagination->getLimitBox(); ?>
			</div>
		</div>

		<?php $del_link = 'index.php?option=com_jem&view=attendees&task=attendees.attendeeremove&id='.$this->event->id.(!empty($this->item->id)?'&Itemid='.$this->item->id:'').'&'.Session::getFormToken().'=1'; ?>

		<div class="table-responsive">
			<table class="eventtable table table-striped" style="margin: 20px 0 0 0;" id="articleList">
				<thead>
					<tr>
						<th width="1%" class="center"><?php echo Text::_('COM_JEM_NUM'); ?></th>
						<!--th width="1%" class="center"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th-->
						<th class="title"><?php echo HTMLHelper::_('grid.sort', $namelabel, 'u.'.$namefield, $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
						<?php if ($this->enableemailaddress == 1) : ?>
						<th class="title"><?php echo Text::_('COM_JEM_EMAIL'); ?></th>
						<?php endif; ?>
						<th class="title"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_REGDATE', 'r.uregdate', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
						<th class="center"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_STATUS', 'r.status', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
						<th class="center"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_PLACES', 'r.places', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
						<?php if (!empty($this->jemsettings->regallowcomments)) : ?>
						<th class="title"><?php echo Text::_('COM_JEM_COMMENT'); ?></th>
						<?php endif;?>
						<th class="center"><?php echo Text::_('COM_JEM_REMOVE_USER'); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($this->rows as $i => $row) : ?>
					<tr class="row<?php echo $i % 2; ?>">
						<td class="center"><?php echo $this->pagination->getRowOffset($i); ?></td>
						<!--td class="center"><?php echo HTMLHelper::_('grid.id', $i, $row->id); ?></td-->
						<td><?php echo $row->$namefield; ?></td>
						<?php if ($this->enableemailaddress == 1) : ?>
						<td><a href="mailto:<?php echo $row->email; ?>"><?php echo $row->email; ?></a></td>
						<?php endif; ?>
						<td><?php if (!empty($row->uregdate)) { echo HTMLHelper::_('date', $row->uregdate, Text::_('DATE_FORMAT_LC5')); } ?></td>
						<td class="center">
							<?php
							$status = (int)$row->status;
                            if($this->event->waitinglist) {
                                if ($status === 1 && $row->waiting == 1) { $status = 2; }
                                echo jemhtml::toggleAttendanceStatus($row->id, $status, true);
                            }else{
                                echo jemhtml::toggleAttendanceStatus($row->id, $status, false);
                            }
							?>
						</td>
						<td class="center"><?php echo $row->places; ?></td>
						<?php if (!empty($this->jemsettings->regallowcomments)) : ?>
						<?php $cmnt = (\Joomla\String\StringHelper::strlen($row->comment) > 16) ? (\Joomla\String\StringHelper::substr($row->comment, 0, 14).'&hellip;') : $row->comment; ?>
						<td><?php if (!empty($cmnt)) { echo HTMLHelper::_('tooltip', $row->comment, null, null, $cmnt, null, null); } ?></td>
						<?php endif;?>
                        <td class="center">
                            <a href="<?php echo Route::_($del_link.'&cid[]='.$row->id); ?>">
                                <?php echo JemOutput::removebutton(Text::_('COM_JEM_ATTENDEES_DELETE'), array('title' => Text::_('COM_JEM_ATTENDEES_DELETE'), 'class' => 'hasTooltip')); ?>
                            </a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php endif; /* empty($this->rows) */ ?>

		<?php echo HTMLHelper::_('form.token'); ?>
		<input type="hidden" name="option" value="com_jem" />
		<input type="hidden" name="boxchecked" value="0" />
		<input type="hidden" name="task" value="" />
		<input type="hidden" name="view" value="attendees" />
		<input type="hidden" name="id" value="<?php echo $this->event->id; ?>" />
		<input type="hidden" name="Itemid" value="<?php echo $this->item->id;?>" />
		<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
		<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
		<input type="hidden" name="enableemailaddress" value="<?php echo $this->enableemailaddress; ?>" />
	</form>

	<div class="pagination">
		<?php echo $this->pagination->getPagesLinks(); ?>
	</div>

	<div class="copyright">
		<?php echo JemOutput::footer(); ?>
	</div>
</div>
