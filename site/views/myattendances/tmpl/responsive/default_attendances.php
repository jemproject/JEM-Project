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

// HTMLHelper::_('behavior.tooltip');

HTMLHelper::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.'/helpers/html');
?>

<style>
  <?php if (!empty($this->jemsettings->tablewidth)) : ?>
    #jem #adminForm {
      width: <?php echo ($this->jemsettings->tablewidth); ?>;
    }
  <?php endif; ?>

  .jem-sort #jem_date,
  #jem .jem-event .jem-event-date {
    <?php if (!empty($this->jemsettings->datewidth)) : ?>
      flex: 1 <?php echo intval(($this->jemsettings->datewidth))-5 . '%'; /*take a little off to fit comment*/?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
    <?php if (JemHelper::jemStringContains($this->pageclass_sfx, 'jem-nodate')) : ?>
      display: none;
    <?php endif; ?>
  }

  .jem-sort #jem_title,
  #jem .jem-event .jem-event-title {
    <?php if (($this->jemsettings->showtitle == 1) && (!empty($this->jemsettings->titlewidth))) : ?>
      flex: 1 <?php echo intval(($this->jemsettings->titlewidth))-5 . '%'; /*take a little off to fit comment*/?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
    <?php if (JemHelper::jemStringContains($this->pageclass_sfx, 'jem-notitle')) : ?>
      display: none;
    <?php endif; ?>
  }

  .jem-sort #jem_location,
  #jem .jem-event .jem-event-venue {
    <?php if (($this->jemsettings->showlocate == 1) && (!empty($this->jemsettings->locationwidth))) : ?>
      flex: 1 <?php echo intval(($this->jemsettings->locationwidth))-3 . '%'; /*take a little off to fit comment*/?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
    <?php if (JemHelper::jemStringContains($this->pageclass_sfx, 'jem-novenue')) : ?>
      display: none;
    <?php endif; ?>
  }

  .jem-sort #jem_city,
  #jem .jem-event .jem-event-city {
    <?php if (($this->jemsettings->showcity == 1) && (!empty($this->jemsettings->citywidth))) : ?>
      flex: 1 <?php echo ($this->jemsettings->citywidth); ?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
    <?php if (JemHelper::jemStringContains($this->pageclass_sfx, 'jem-nocity')) : ?>
      display: none;
    <?php endif; ?>
  }

  .jem-sort #jem_state,
  #jem .jem-event .jem-event-state {
    <?php if (($this->jemsettings->showstate == 1) && (!empty($this->jemsettings->statewidth))) : ?>
      flex: 1 <?php echo ($this->jemsettings->statewidth); ?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
    <?php if (JemHelper::jemStringContains($this->pageclass_sfx, 'jem-nostate')) : ?>
      display: none;
    <?php endif; ?>
  }

  .jem-sort #jem_category,
  #jem .jem-event .jem-event-category {
    <?php if (($this->jemsettings->showcat == 1) && (!empty($this->jemsettings->catfrowidth))) : ?>
      flex: 1 <?php echo ($this->jemsettings->catfrowidth); ?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
    <?php if (JemHelper::jemStringContains($this->pageclass_sfx, 'jem-nocategory')) : ?>
      display: none;
    <?php endif; ?>
  }

  .jem-sort #jem_atte,
  #jem .jem-event .jem-event-attendees {
    <?php if (($this->jemsettings->showatte == 1) && (!empty($this->jemsettings->attewidth))) : ?>
      flex: 1 <?php echo ($this->jemsettings->attewidth); ?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
    <?php if (JemHelper::jemStringContains($this->pageclass_sfx, 'jem-noattendees')) : ?>
      display: none;
    <?php endif; ?>
  }

    .jem-sort #jem_places,
    #jem .jem-event .jem-myattendances-places {
        flex: 0 5%;
	    text-align: center;
    }

  .jem-sort #jem_status,
  #jem .jem-event .jem-myattendances-status {
        flex: 0 5%;
	    text-align: center;
  }
    .jem-sort #jem_comment,
  #jem .jem-event .jem-myattendances-comments {
    flex: 0 5%;
  }
</style>

<script type="text/javascript">
	function tableOrdering(order, dir, view)
	{
		var form = document.getElementById("adminForm");

		form.filter_order.value     = order;
		form.filter_order_Dir.value = dir;
		form.submit(view);
	}
</script>

<h2><?php echo Text::_('COM_JEM_REGISTERED_TO'); ?></h2>

<form action="<?php echo htmlspecialchars($this->action); ?>" method="post" id="adminForm" name="adminForm">
  <?php if ($this->settings->get('global_show_filter',1) || $this->settings->get('global_display',1)) : ?>
		<?php if ($this->settings->get('global_show_filter',1)) : ?>
      <div id="jem_filter" class="floattext jem-form jem-row jem-justify-start">
        <div>
          <?php echo '<label for="filter">'.Text::_('COM_JEM_FILTER').'</label>'; ?>
        </div>
        <div class="jem-row jem-justify-start jem-nowrap">
          <?php echo $this->lists['filter']; ?>
          <input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search'];?>" class="inputbox" onchange="document.adminForm.submit();" />
        </div>
        <div class="jem-row jem-justify-start jem-nowrap">
          <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
          <button class="btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
        </div>
		<?php if ($this->settings->get('global_display',1)) : ?>
  <div class="jem-limit-smallist">
    <?php
      echo '<label for="limit">'.Text::_('COM_JEM_DISPLAY_NUM').'</label>&nbsp;';
      //echo '<span class="jem-limit-text">'.Text::_('COM_JEM_DISPLAY_NUM').'</span>&nbsp;';
      echo $this->attending_pagination->getLimitBox();
    ?>
  </div>
<?php endif; ?>
			</div>
		<?php endif; ?>
  <?php endif; ?>

	<div class="jem-sort jem-sort-small">
    <div class="jem-list-row jem-small-list">
      <div id="jem_date" class="sectiontableheader">&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_DATE', 'a.dates', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php if ($this->jemsettings->showtitle == 1) : ?>
        <div id="jem_title" class="sectiontableheader">&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_TITLE', 'a.title', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <?php if ($this->jemsettings->showlocate == 1) : ?>
        <div id="jem_location" class="sectiontableheader">&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'l.venue', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <?php if ($this->jemsettings->showcity == 1) : ?>
        <div id="jem_city" class="sectiontableheader">&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <?php if ($this->jemsettings->showstate == 1) : ?>
        <div id="jem_state" class="sectiontableheader">&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_STATE', 'l.state', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <?php if ($this->jemsettings->showcat == 1) : ?>
        <div id="jem_category" class="sectiontableheader">&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CATEGORY', 'c.catname', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <div id="jem_places" class="sectiontableheader"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_PLACES', 'r.places', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <div id="jem_status" class="sectiontableheader"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_HEADER_WAITINGLIST_STATUS', 'r.status', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php if (!empty($this->jemsettings->regallowcomments)) : ?>
        <div id="jem_comment" class="sectiontableheader"><?php echo Text::_('COM_JEM_COMMENT'); ?></div>
      <?php endif; ?>
    </div>
  </div>

	<ul class="eventlist">
		<?php if (count((array)$this->attending) == 0) : ?>
			<li class="jem-event"><?php echo Text::_('COM_JEM_NO_EVENTS'); ?></li>
		<?php else : ?>
			<?php foreach ($this->attending as $i => $row) : ?>
        <?php if (!empty($row->featured)) :   ?>
          <li class="jem-event jem-list-row jem-small-list jem-featured event-id<?php echo $row->id.$this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
				<?php else : ?>
          <li class="jem-event jem-list-row jem-small-list jem-odd<?php echo ($i % 2) . $this->params->get('pageclass_sfx'); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
				<?php endif; ?>

					<div class="jem-event-info-small jem-event-date" title="<?php echo Text::_('COM_JEM_TABLE_DATE').': '.strip_tags(JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $this->jemsettings->showtime)); ?>">
            <i class="far fa-clock" aria-hidden="true"></i>
            <?php
              echo JemOutput::formatShortDateTime($row->dates, $row->times,
                $row->enddates, $row->endtimes, $this->jemsettings->showtime);
            ?>
             <?php if ($this->jemsettings->showtitle == 0) : ?>
              <?php if (!empty($row->featured)) :?>
                <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
              <?php endif; ?>
             <?php endif; ?>
          </div>

					<?php if ($this->jemsettings->showtitle == 1) : ?>
            <div class="jem-event-info-small jem-event-title" title="<?php echo Text::_('COM_JEM_TABLE_TITLE').': '.$this->escape($row->title); ?>">
              <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>"><?php echo $this->escape($row->title); ?></a>
              <?php echo JemOutput::recurrenceicon($row) . JemOutput::publishstateicon($row); ?>
              <?php if (!empty($row->featured)) :?>
                <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
              <?php endif; ?>
            </div>
          <?php else : ?>
          <?php endif; ?>

					<?php if ($this->jemsettings->showlocate == 1) : ?>
            <?php if (!empty($row->venue)) : ?>
              <div class="jem-event-info-small jem-event-venue" title="<?php echo Text::_('COM_JEM_TABLE_LOCATION').': '.$this->escape($row->venue); ?>">
                <i class="fa fa-map-marker" aria-hidden="true"></i>
                <?php if (($this->jemsettings->showlinkvenue == 1) && !empty($row->venueslug)) : ?>
                  <?php echo "<a href='".Route::_(JemHelperRoute::getVenueRoute($row->venueslug))."'>".$this->escape($row->venue)."</a>"; ?>
                <?php else : ?>
                  <?php echo $this->escape($row->venue); ?>
                <?php endif; ?>
              </div>
            <?php else : ?>
              <div class="jem-event-info-small jem-event-venue">
                <i class="fa fa-map-marker" aria-hidden="true"></i> -
              </div>
            <?php endif; ?>
          <?php endif; ?>

					<?php if ($this->jemsettings->showcity == 1) : ?>
            <?php if (!empty($row->city)) : ?>
              <div class="jem-event-info-small jem-event-city" title="<?php echo Text::_('COM_JEM_TABLE_CITY').': '.$this->escape($row->city); ?>">
                <i class="fa fa-building" aria-hidden="true"></i>
                <?php echo $this->escape($row->city); ?>
              </div>
            <?php else : ?>
              <div class="jem-event-info-small jem-event-city"><i class="fa fa-building" aria-hidden="true"></i> -</div>
            <?php endif; ?>
          <?php endif; ?>

					<?php if ($this->jemsettings->showstate == 1) : ?>
            <?php if (!empty($row->state)) : ?>
              <div class="jem-event-info-small jem-event-state" title="<?php echo Text::_('COM_JEM_TABLE_STATE').': '.$this->escape($row->state); ?>">
                <i class="fa fa-map" aria-hidden="true"></i>
                <?php echo $this->escape($row->state); ?>
              </div>
            <?php else : ?>
              <div class="jem-event-info-small jem-event-state"><i class="fa fa-map" aria-hidden="true"></i> -</div>
            <?php endif; ?>
          <?php endif; ?>

					<?php if ($this->jemsettings->showcat == 1) : ?>
            <div class="jem-event-info-small jem-event-category" title="<?php echo strip_tags(Text::_('COM_JEM_TABLE_CATEGORY').': '.implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist))); ?>">
              <i class="fa fa-tag" aria-hidden="true"></i>
              <?php echo implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist)); ?>
            </div>
          <?php endif; ?>

            <div class="jem-event-info-small jem-myattendances-places" title="<?php echo Text::_('COM_JEM_TABLE_PLACES').': '.$this->escape($row->places); ?>">
                <?php echo $this->escape($row->places); ?>
            </div>            

					<div class="jem-event-info-small jem-myattendances-status">
						<?php
						$status = (int)$row->status;
						if ($status === 1 && $row->waiting == 1) { $status = 2; }
						echo jemhtml::toggleAttendanceStatus($row->id, $status, false, $this->print);
						?>
					</div>

					<?php if (!empty($this->jemsettings->regallowcomments)) : ?>
            <div class="jem-event-info-small jem-myattendances-comments">
              <?php
              $len  = ($this->print) ? 256 : 16;
              $cmnt = (\Joomla\String\StringHelper::strlen($row->comment) > $len) ? (\Joomla\String\StringHelper::substr($row->comment, 0, $len - 2).'&hellip;') : $row->comment;
              if (!empty($cmnt)) :
                echo ($this->print) ? $cmnt : HTMLHelper::_('tooltip', $row->comment, null, null, $cmnt, null, null);
              endif;
              ?>
            </div>
					<?php endif; ?>
				<?php $i = 1 - $i; ?>
			<?php endforeach; ?>
		<?php endif; ?>
	</ul>

	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="task" value="<?php echo $this->task; ?>" />
	<input type="hidden" name="option" value="com_jem" />
</form>


<div class="pagination">
	<?php echo $this->attending_pagination->getPagesLinks(); ?>
</div>
