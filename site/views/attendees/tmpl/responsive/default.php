<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

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

		form.filter_order.value 	= order;
		form.filter_order_Dir.value	= dir;
		form.submit(view);
	}
</script>
<script type="text/javascript">
    function jSelectUsers_newusers(ids, count, status, places, eventid, token) {
        document.location.href = 'index.php?option=com_jem&task=attendees.attendeeadd&id='+eventid+'&status='+status+'&places='+places+'&uids='+ids+'&'+token+'=1';
		SqueezeBox.close();
	}
</script>

<div id="jem" class="jem_attendees <?php echo $this->pageclass_sfx;?>">
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
		<dl class="jem-dl">
					<dt class="jem-title"><?php echo Text::_('COM_JEM_TITLE').':'; ?></dt>
					<dd class="jem-title"><a href="<?php echo $detaillink ; ?>"><?php echo $this->escape($this->event->title); ?></a></dd>

					<dt class="jem-date"><?php echo Text::_('COM_JEM_DATE').':'; ?></dt>
          <dd class="jem-date">
            <?php echo JemOutput::formatLongDateTime($this->event->dates, $this->event->times, $this->event->enddates, $this->event->endtimes, $this->settings->get('global_show_timedetails', 1)); ?>
          </dd>
		</dl>

    <?php if (empty($this->rows)) : //ToDo EGNARTS!!! ?>
      <dl id="jem_filter" class="jem-dl">
        <dt>
          <?php echo '<label for="filter_search">'.Text::_('COM_JEM_SEARCH').'</label>'; ?>
        </dt>
        <dd class="jem-form jem-row jem-justify-start">
          <div class="jem-row jem-justify-start jem-nowrap">
            <?php echo $this->lists['filter']; ?>
            <input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search']; ?>" class="inputbox" onChange="document.adminForm.submit();" />
          </div>
          <div class="jem-row jem-justify-start jem-nowrap">
            <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            <button class="btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
          </div>
        </dd>

        <dt class="jem-row jem-justify-start jem-nowrap">
          <?php echo '<label for="filter_status">'.Text::_('COM_JEM_STATUS').'</label>'; ?>
        </dt>
        <dd>
          <?php echo $this->lists['status']; ?>
        </dd>
      </dl>

      <div class="eventtable">
			<strong><i><?php echo Text::_('COM_JEM_ATTENDEES_EMPTY_YET'); ?></i></strong>
		</div>

		<?php else : /* empty($this->rows) */ ?>
      <dl id="jem_filter" class="jem-dl">
        <dt>
          <?php echo '<label for="filter_search">'.Text::_('COM_JEM_SEARCH').'</label>'; ?>
        </dt>
        <dd class="jem-form jem-row jem-justify-start">
          <div class="jem-row jem-justify-start jem-nowrap">
            <?php echo $this->lists['filter']; ?>
            <input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search']; ?>" class="inputbox" onChange="document.adminForm.submit();" />
          </div>
          <div class="jem-row jem-justify-start jem-nowrap">
            <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            <button class="btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
          </div>
        </dd>

        <dt class="jem-row jem-justify-start jem-nowrap">
          <?php echo '<label for="filter_status">'.Text::_('COM_JEM_STATUS').'</label>'; ?>
        </dt>
        <dd>
          <?php echo $this->lists['status']; ?>
        </dd>
  <div class="jem-limit-smallist">
    <?php
      echo '<span class="jem-limit-text">'.Text::_('COM_JEM_DISPLAY_NUM').'</span>&nbsp;';
      echo $this->pagination->getLimitBox();
    ?>
  </div>
      </dl>

      <div class="jem-sort jem-sort-small" id="articleList">
        <div class="jem-list-row jem-small-list">
          <div class="sectiontableheader jem-attendee-number"><?php echo Text::_('COM_JEM_NUM'); ?></div>
          <div class="sectiontableheader jem-attendee-name"><?php echo JHtml::_('grid.sort', 'COM_JEM_USERNAME', 'u.'.$namefield, $this->lists['order_Dir'], $this->lists['order'] ); ?></div>
          <?php if ($this->enableemailaddress == 1) :?>
            <div class="sectiontableheader jem-attendee-email"><?php echo Text::_('COM_JEM_EMAIL'); ?></div>
          <?php endif; ?>
          <div class="sectiontableheader jem-attendee-regdate"><?php echo JHtml::_('grid.sort', 'COM_JEM_REGDATE', 'r.uregdate', $this->lists['order_Dir'], $this->lists['order'] ); ?></div>
          <div class="sectiontableheader jem-attendee-status"><?php echo JHtml::_('grid.sort', 'COM_JEM_STATUS', 'r.status', $this->lists['order_Dir'], $this->lists['order'] ); ?></div>
          <div class="sectiontableheader jem-attendee-places"><?php echo JHtml::_('grid.sort', 'COM_JEM_PLACES', 'r.places', $this->lists['order_Dir'], $this->lists['order'] ); ?></div>
          <?php if (!empty($this->jemsettings->regallowcomments)) : ?>
            <div class="sectiontableheader jem-attendee-comment"><?php echo Text::_('COM_JEM_COMMENT'); ?></div>
          <?php endif; ?>
          <div class="sectiontableheader jem-attendee-remove"><?php echo Text::_('COM_JEM_REMOVE_USER'); ?></div>
        </div>
      </div>

      <ul class="eventlist eventtable">
        <?php $del_link = 'index.php?option=com_jem&view=attendees&task=attendees.attendeeremove&id='.$this->event->id.(!empty($this->item->id)?'&Itemid='.$this->item->id:'').'&'.JSession::getFormToken().'=1'; 
        ?>
        <?php foreach ($this->rows as $i => $row) : ?>
          <li class="jem-event jem-list-row jem-small-list row<?php echo $i % 2; ?>">
            <div class="jem-event-info-small jem-attendee-number">
              <?php echo $this->pagination->getRowOffset($i); ?>
            </div>

            <div class="jem-event-info-small jem-attendee-name">
              <?php echo $row->$namefield; ?>
            </div>

            <?php if ($this->enableemailaddress == 1) :?>
              <div class="jem-event-info-small jem-attendee-email">
                <a href="mailto:<?php echo $row->email; ?>"><?php echo $row->email; ?></a>
              </div>
            <?php endif; ?>

            <div class="jem-event-info-small jem-attendee-regdate">
              <?php if (!empty($row->uregdate)) { echo JHtml::_('date', $row->uregdate, Text::_('DATE_FORMAT_LC5')); } ?>
            </div>

            <div class="jem-event-info-small jem-attendee-status">
              <?php
              $status = (int)$row->status;
              if ($status === 1 && $row->waiting == 1) { $status = 2; }
              echo jemhtml::toggleAttendanceStatus($row->id, $status, true);
              ?>
            </div>
            <div class="jem-event-info-small jem-attendee-places">
	            <?php echo $row->places; ?>
            </div>

            <?php if (!empty($this->jemsettings->regallowcomments)) : ?>
              <?php $cmnt = (\Joomla\String\StringHelper::strlen($row->comment) > 16) ? (\Joomla\String\StringHelper::substr($row->comment, 0, 14).'&hellip;') : $row->comment; ?>
              <div class="jem-event-info-small jem-attendee-comment">
                <?php if (!empty($cmnt)) { echo JHtml::_('tooltip', $row->comment, null, null, $cmnt, null, null); } ?>
              </div>
            <?php endif;?>

            <div class="jem-event-info-small jem-attendee-remove">
                <div class="center">
              <a href="<?php echo JRoute::_($del_link.'&cid[]='.$row->id); ?>">
                <?php echo JemOutput::removebutton(Text::_('COM_JEM_ATTENDEES_DELETE'), array('title' => Text::_('COM_JEM_ATTENDEES_DELETE'), 'class' => 'hasTooltip')); ?>
              </a>
                </div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>

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
