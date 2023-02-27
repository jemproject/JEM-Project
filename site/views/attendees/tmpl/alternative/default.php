<?php
/**
 * @version 2.3.9
 * @package JEM
 * @copyright (C) 2013-2022 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

// JHtml::_('behavior.tooltip');

JHtml::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.'/helpers/html');

$colspan = ($this->event->waitinglist ? 10 : 9);

$detaillink = JRoute::_(JemHelperRoute::getEventRoute($this->event->id.':'.$this->event->alias));

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
	function fullOrdering(id, view)
	{
		var form = document.getElementById("adminForm");
		var field = form.getElementById(id);
		var parts = field.value.split(' ');

		if (parts.length > 1) {
			form.filter_order.value     = parts[0];
			form.filter_order_Dir.value = parts[1];
		}
		form.submit(view);
	}
</script>
<script type="text/javascript">
	function jSelectUsers_newusers(ids, count, status, eventid, token) {
		document.location.href = 'index.php?option=com_jem&task=attendees.attendeeadd&id='+eventid+'&status='+status+'&uids='+ids+'&'+token+'=1';
		SqueezeBox.close();
	}
</script>

<?php
$sort_by = array();
if ($this->settings->get('global_regname', '1')) {
	$sort_by[] = JHtml::_('select.option', 'u.name ASC', JText::_('COM_JEM_NAME') . ' ' . JText::_('COM_JEM_ORDER_ASCENDING'));
	$sort_by[] = JHtml::_('select.option', 'u.name DESC', JText::_('COM_JEM_NAME') . ' ' . JText::_('COM_JEM_ORDER_DESCENDING'));
} else {
	$sort_by[] = JHtml::_('select.option', 'u.username ASC', JText::_('COM_JEM_USERNAME') . ' ' . JText::_('COM_JEM_ORDER_ASCENDING'));
	$sort_by[] = JHtml::_('select.option', 'u.username DESC', JText::_('COM_JEM_USERNAME') . ' ' . JText::_('COM_JEM_ORDER_DESCENDING'));
}
$sort_by[] = JHtml::_('select.option', 'r.uregdate ASC', JText::_('COM_JEM_REGDATE') . ' ' . JText::_('COM_JEM_ORDER_ASCENDING'));
$sort_by[] = JHtml::_('select.option', 'r.uregdate DESC', JText::_('COM_JEM_REGDATE') . ' ' . JText::_('COM_JEM_ORDER_DESCENDING'));
$sort_by[] = JHtml::_('select.option', 'r.status ASC', JText::_('COM_JEM_STATUS') . ' ' . JText::_('COM_JEM_ORDER_ASCENDING'));
$sort_by[] = JHtml::_('select.option', 'r.status DESC', JText::_('COM_JEM_STATUS') . ' ' . JText::_('COM_JEM_ORDER_DESCENDING'));

$this->lists['sort_by'] = JHtml::_('select.genericlist', $sort_by, 'sort_by', array('size'=>'1','class'=>'inputbox','onchange'=>'fullOrdering(\'sort_by\', \'\');'), 'value', 'text', $this->lists['order'] . ' ' . $this->lists['order_Dir']);
?>

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
		<div>
			<b><?php echo JText::_('COM_JEM_TITLE').':'; ?></b>
			<a href="<?php echo $detaillink ; ?>"><?php echo $this->escape($this->event->title); ?></a>
			<br />
			<b><?php echo JText::_('COM_JEM_DATE').':'; ?></b>
			<?php echo JemOutput::formatLongDateTime($this->event->dates, $this->event->times, $this->event->enddates, $this->event->endtimes, $this->settings->get('global_show_timedetails', 1)); ?>
			<?php
			$g_reg = $this->jemsettings->showfroregistra;
			$e_reg = $this->event->registra;
			if (($g_reg < 1) || (($g_reg == 2) && (($e_reg & 1) == 0))) :
			?>
			<br />
			<br />
			<b><?php echo JHtml::_('image', 'com_jem/icon-16-warning.png', null, 'class="icon-inline-left"', true) . JText::_('COM_JEM_REGISTRATION_DISABLED'); ?></b><br />
			<?php echo JText::_(($g_reg < 1) ? 'COM_JEM_REGISTRATION_DISABLED_GLOBAL_HINT' : 'COM_JEM_REGISTRATION_DISABLED_EVENT_HINT'); ?>
			<?php endif; ?>
		</div>

		<?php if (empty($this->rows)) : ?>

		<div class="eventtable">
			<strong><i><?php echo JText::_('COM_JEM_ATTENDEES_EMPTY_YET'); ?></i></strong>
		</div>

		<?php else : /* empty($this->rows) */ ?>

		<div id="jem_filter" class="floattext">
			<div class="jem_fleft">
				<label for="filter"><?php echo JText::_('COM_JEM_SEARCH'); ?></label>
				<?php echo $this->lists['filter'].'&nbsp;'; ?>
				<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search']; ?>" class="inputbox" onChange="document.adminForm.submit();" />
				<button class="buttonfilter" type="submit"><?php echo JText::_('JSEARCH_FILTER_SUBMIT'); ?></button>
				<button class="buttonfilter" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?></button>
				&nbsp;
			</div>
			<div class="jem_fleft" style="white-space:nowrap;">
				<?php echo JText::_('COM_JEM_STATUS').' '.$this->lists['status']; ?>
			</div>
			<div class="jem_fright">
				<label for="sort_by"><?php echo JText::_('COM_JEM_ORDERING'); ?></label>
				<?php echo $this->lists['sort_by'].' '; ?>
				<label for="limit"><?php echo JText::_('COM_JEM_DISPLAY_NUM'); ?></label>
				<?php echo $this->pagination->getLimitBox(); ?>
			</div>
		</div>

		<?php $del_link = 'index.php?option=com_jem&view=attendees&task=attendees.attendeeremove&id='.$this->event->id.(!empty($this->item->id)?'&Itemid='.$this->item->id:'').'&'.JSession::getFormToken().'=1'; ?>

		<?php
			$default_span = array('number' => 1, 'user' => 2, 'email' => 2, 'date' => 2, 'status' => 1, 'comment' => 2, 'remove' => 2);
			$a_span = array('number' => $default_span['number'], 'user' => $default_span['user']); // always shown
			if ($this->enableemailaddress == 1) {
				$a_span['email'] = $default_span['email'];
			}
			$a_span['date'] = $default_span['date']; // always shown
			$a_span['status'] = $default_span['status']; // always shown
			if (!empty($this->jemsettings->regallowcomments)) {
				$a_span['comment'] = $default_span['comment'];
			}
			$a_span['remove'] = $default_span['remove']; // always shown
			$total = array_sum($a_span);
			while ($total < 12) {
				if (array_key_exists('comment', $a_span)) {
					++$a_span['comment'];
					++$total;
				}
				if ($total < 12 && ($a_span['date'] <= $default_span['date'])) {
					++$a_span['date'];
					++$total;
				}
				if (($total < 12) && array_key_exists('user', $a_span)) {
					++$a_span['user'];
					++$total;
				}
				if (($total < 12) && array_key_exists('email', $a_span)) {
					++$a_span['email'];
					++$total;
				}
			} // while
		?>
		<div class="eventtable">
			<div class="row-fluid sectiontableheader">
				<div class="span<?php echo $a_span['number']; ?>"><?php echo JText::_('COM_JEM_NUM'); ?></div>
				<div class="span<?php echo $a_span['user']; ?>"><?php echo JText::_($namelabel); ?></div>
				<?php if (array_key_exists('email', $a_span)) : ?>
				<div class="span<?php echo $a_span['email']; ?>"><?php echo JText::_('COM_JEM_EMAIL'); ?></div>
				<?php endif; ?>
				<div class="span<?php echo $a_span['date']; ?>"><?php echo JText::_('COM_JEM_REGDATE'); ?></div>
				<div class="span<?php echo $a_span['status']; ?>"><?php echo JText::_('COM_JEM_STATUS'); ?></div>
				<?php if (array_key_exists('comment', $a_span)) : ?>
				<div class="span<?php echo $a_span['comment']; ?>"><?php echo JText::_('COM_JEM_COMMENT'); ?></div>
				<?php endif; ?>
				<div class="span<?php echo $a_span['remove']; ?>"><?php echo JText::_('COM_JEM_REMOVE_USER'); ?></div>
			</div>
			<?php foreach ($this->rows as $i => $row) : ?>
				<div class="row-fluid sectiontableentry<?php echo $this->params->get('pageclass_sfx'); ?>">
					<div class="span<?php echo $a_span['number']; ?> number"><?php echo $this->pagination->getRowOffset($i); ?></div>
					<div class="span<?php echo $a_span['user']; ?> user">
						<?php echo $row->$namefield; ?>
					</div>
					<?php if (array_key_exists('email', $a_span)) : ?>
					<div class="span<?php echo $a_span['email']; ?> email">
						<a href="mailto:<?php echo $row->email; ?>"><?php echo $row->email; ?></a>
					</div>
					<?php endif; ?>
					<div class="span<?php echo $a_span['date']; ?> date">
						<?php if (!empty($row->uregdate)) { echo JHtml::_('date', $row->uregdate, JText::_('DATE_FORMAT_LC2')); } ?>
					</div>
					<div class="span<?php echo $a_span['status']; ?> status">
						<?php
						$status = (int)$row->status;
						if ($status === 1 && $row->waiting == 1) { $status = 2; }
						echo jemhtml::toggleAttendanceStatus( $row->id, $status, true);
						?><span class="info-text"><?php
							echo JHtml::_('jemhtml.getAttendanceStatusText', $row->id, $status, false, true);
						?></span>
					</div>
					<?php if (array_key_exists('comment', $a_span)) : ?>
					<div class="span<?php echo $a_span['comment']; ?> comment">
						<?php $cmnt = (\Joomla\String\StringHelper::strlen($row->comment) > 16) ? (\Joomla\String\StringHelper::substr($row->comment, 0, 14).'&hellip;') : $row->comment; ?>
						<?php if (!empty($cmnt)) { echo JHtml::_('tooltip', $row->comment, null, null, $cmnt, null, null); } ?>
					</div>
					<?php endif;?>
					<div class="span<?php echo $a_span['remove']; ?> remove">
						<a href="<?php echo JRoute::_($del_link.'&cid[]='.$row->id); ?>"><?php
							echo JHtml::_('image','com_jem/publish_r.png', JText::_('COM_JEM_ATTENDEES_DELETE'), array('title' => JText::_('COM_JEM_ATTENDEES_DELETE'), 'class' => (version_compare(JVERSION, '3.3', 'lt')) ? 'hasTip' : 'hasTooltip'), true);
						?></a><span class="info-text"><?php
							echo JText::_('COM_JEM_ATTENDEES_DELETE');
						?></span>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php endif; /* empty($this->rows) */ ?>

		<?php echo JHtml::_('form.token'); ?>
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
