<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

if (!function_exists('jem_frontend_status_label')) {
    function jem_frontend_status_label($state)
    {
        switch ((int) $state) {
            case 1:
                return Text::_('JPUBLISHED');
            case 0:
                return Text::_('JUNPUBLISHED');
            case 2:
                return Text::_('JARCHIVED');
            case -2:
                return Text::_('JTRASHED');
        }

        return Text::_('JSTATUS');
    }
}

$showAttendeeColumn = ((int) $this->params->get('displayattendeecolumn', 0) === 1);

?>

<?php if (!$this->params->get('show_page_heading', 1)) :
           /* hide this if page heading is shown */     ?>
<h1 class="componentheading"><?php echo Text::_('COM_JEM_MY_EVENTS'); ?></h1>
<?php endif; ?>

<script>
    function tableOrdering(order, dir, view)
    {
        var form = document.getElementById("adminForm");

        form.filter_order.value     = order;
        form.filter_order_Dir.value    = dir;
        form.submit(view);
    }
</script>


<style>
  <?php if (!empty($this->jemsettings->tablewidth)) : ?>
    #jem #adminForm {
      width: <?php echo ($this->jemsettings->tablewidth); ?>;
    }
  <?php endif; ?>

  #jem .jem-list-row.jem-small-list {
    column-gap: 0;
    row-gap: 0.35rem;
  }

  #jem .jem-list-row.jem-small-list > * {
    box-sizing: border-box;
    min-width: 0;
    padding-right: 0.75rem;
  }

  #jem .jem-list-row.jem-small-list > *:last-child {
    padding-right: 0;
  }

  #jem .jem-status-published .btn-micro span.icon-publish:before,
  #jem .jem-status-published .btn-micro i.icon-publish,
  #jem .jem-status-published .jgrid span.publish {
    background-image: url(<?php echo Uri::root(true); ?>/media/com_jem/images/tick.webp) !important;
    content: url(<?php echo Uri::root(true); ?>/media/com_jem/images/tick.webp) !important;
    opacity: 1 !important;
  }

  #jem .jem-status-unpublished .btn-micro span.icon-unpublish:before,
  #jem .jem-status-unpublished .btn-micro i.icon-unpublish:before,
  #jem .jem-status-unpublished .jgrid span.unpublish:before,
  #jem .jem-status-unpublished .icon-unpublish:before {
    background-image: none !important;
    color: #b71c1c !important;
    opacity: 1 !important;
  }

  #jem .jem-status-unpublished .btn-micro i.icon-unpublish,
  #jem .jem-status-unpublished .jgrid span.unpublish,
  #jem .jem-status-unpublished .btn-micro span.icon-unpublish {
    background-image: none !important;
    color: #b71c1c !important;
    opacity: 1 !important;
  }

  #jem .jem-status-unpublished .btn-micro,
  #jem .jem-status-unpublished .btn-micro *,
  #jem .jem-status-unpublished .jgrid,
  #jem .jem-status-unpublished .jgrid * {
    color: #b71c1c !important;
    opacity: 1 !important;
  }

  #jem .jem-status-unpublished .jem-publishstateicon-unpublished,
  #jem .jem-status-unpublished .jem-status-link {
    color: #b71c1c !important;
    opacity: 1 !important;
    text-shadow: none !important;
  }

  #jem .jem-status-trashed .btn-micro span.icon-trash:before,
  #jem .jem-status-trashed .btn-micro i.icon-trash,
  #jem .jem-status-trashed .jgrid span.trash {
    background-image: url(<?php echo Uri::root(true); ?>/media/com_jem/images/icon-16-trash.webp) !important;
    content: url(<?php echo Uri::root(true); ?>/media/com_jem/images/icon-16-trash.webp) !important;
    opacity: 0.55 !important;
  }

  #jem .jem-row-unpublished,
  #jem .jem-row-unpublished a {
    color: #6c757d !important;
  }

  .jem-sort .sectiontableheader {
    position: relative;
  }

  .jem-sort .sectiontableheader .jem-column-icon {
    position: absolute;
    left: -1.1rem;
    top: 50%;
    width: 1rem;
    text-align: center;
    transform: translateY(-50%);
  }

  .jem-sort #jem_date .jem-column-icon {
    position: static;
    margin-right: 0.2rem;
    transform: none;
  }

  .jem-sort #jem_date,
  #jem .jem-event .jem-event-date {
    <?php if (!empty($this->jemsettings->datewidth)) : ?>
      flex: 1 <?php echo intval(($this->jemsettings->datewidth))-4 . '%'; /*take a little off to fit status*/?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
    <?php if (JemHelper::jemStringContains($this->pageclass_sfx, 'jem-nodate')) : ?>
      display: none;
    <?php endif; ?>
    <?php if ($this->showdate != 1) : ?>
      display: none;
    <?php endif; ?>
  }

  .jem-sort #jem_title,
  #jem .jem-event .jem-event-title {
    <?php if (($this->jemsettings->showtitle == 1) && (!empty($this->jemsettings->titlewidth))) : ?>
      flex: 1 <?php echo (intval($this->jemsettings->titlewidth))-4 . '%'; /*take a little off to fit status*/?>;
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
      flex: 1 <?php echo ($this->jemsettings->locationwidth); ?>;
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
    <?php if ($showAttendeeColumn && !empty($this->jemsettings->attewidth)) : ?>
      flex: 1 <?php echo ($this->jemsettings->attewidth); ?>;
    <?php else : ?>
      flex: 1;
    <?php endif; ?>
    <?php if (JemHelper::jemStringContains($this->pageclass_sfx, 'jem-noattendees')) : ?>
      display: none;
    <?php endif; ?>
  }

  .jem-sort .jem-myevents-check,
  #jem .jem-event .jem-myevents-check {
    display: flex;
    flex: 0 0 auto;
    flex-basis: auto !important;
    width: max-content;
    min-width: 1.5rem;
    max-width: max-content;
    align-items: center;
    justify-content: center;
    overflow: visible;
  }

  .jem-sort .jem-myevents-status,
  #jem .jem-event .jem-myevents-status {
    display: flex;
    flex: 0 0 auto;
    flex-basis: auto !important;
    width: max-content;
    min-width: max-content;
    max-width: max-content;
    margin-left: auto;
    align-items: center;
    text-align: center;
    justify-content: center;
    overflow: visible;
  }
</style>


<form action="<?php echo htmlspecialchars($this->action); ?>" method="post" name="adminForm" id="adminForm">
  <?php if ($this->settings->get('global_show_filter',1) || $this->settings->get('global_display',1)) : ?>
        <?php if ($this->settings->get('global_show_filter',1)) : ?>
        <div id="jem_filter" class="floattext jem-form jem-row jem-justify-start">
        <div>
          <?php echo '<label for="filter">'.Text::_('COM_JEM_FILTER').'</label>'; ?>
        </div>
        <div class="jem-row jem-justify-start jem-nowrap">
          <?php echo $this->lists['filter']; ?>
          <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8');?>" class="inputbox form-control" onchange="document.adminForm.submit();" />
        </div>
        <div class="jem-row jem-justify-start jem-nowrap">
          <button class="buttonfilter btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
          <button class="buttonfilter btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
        </div>
        <?php if ($this->settings->get('global_display',1)) : ?>
        <div class="jem-row jem-justify-start jem-nowrap">
        <label for="limit"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>&nbsp;
        <?php echo $this->events_pagination->getLimitBox(); ?>
        </div>
        <?php endif; ?>
            </div>
        <?php endif; ?>
  <?php endif; ?>

  <div class="jem-sort jem-sort-small">
    <div class="jem-list-row jem-small-list">
      <?php if (empty($this->print) && !empty($this->permissions->canPublishEvent)) : ?>
                <div class="sectiontableheader jem-myevents-check">
          <input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
        </div>
      <?php endif; ?>
      <?php if ($this->showdate == 1) : ?>
        <div id="jem_date" class="sectiontableheader"><i class="jem-column-icon far fa-clock" aria-hidden="true"></i><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_DATE', 'a.dates', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <?php if ($this->jemsettings->showtitle == 1) : ?>
        <div id="jem_title" class="sectiontableheader"><i class="jem-column-icon fa fa-comment" aria-hidden="true"></i><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_TITLE', 'a.title', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <?php if ($this->jemsettings->showlocate == 1) : ?>
        <div id="jem_location" class="sectiontableheader"><i class="jem-column-icon fa fa-map-marker" aria-hidden="true"></i><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'l.venue', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <?php if ($this->jemsettings->showcity == 1) : ?>
        <div id="jem_city" class="sectiontableheader"><i class="jem-column-icon fa fa-building" aria-hidden="true"></i><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <?php if ($this->jemsettings->showstate == 1) : ?>
        <div id="jem_state" class="sectiontableheader"><i class="jem-column-icon fa fa-map" aria-hidden="true"></i><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_STATE', 'l.state', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <?php if ($this->jemsettings->showcat == 1) : ?>
        <div id="jem_category" class="sectiontableheader"><i class="jem-column-icon fa fa-tag" aria-hidden="true"></i><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CATEGORY', 'c.catname', $this->lists['order_Dir'], $this->lists['order']); ?></div>
      <?php endif; ?>
      <?php if ($showAttendeeColumn) : ?>
                <div id="jem_atte" class="sectiontableheader">&nbsp;<?php echo Text::_('COM_JEM_TABLE_ATTENDEES'); ?></div>
      <?php endif; ?>
      <div class="jem-myevents-status" title="<?php echo Text::_('JSTATUS'); ?>" aria-label="<?php echo Text::_('JSTATUS'); ?>">
        <span class="fa fa-check-circle" aria-hidden="true"></span>&nbsp;<?php echo Text::_('JSTATUS'); ?>
      </div>
    </div>
  </div>

    <ul class="eventlist jem-myevents">
        <?php if (count((array)$this->events) == 0) : ?>
            <li class="jem-event"><?php echo Text::_('COM_JEM_NO_EVENTS'); ?></li>
        <?php else : ?>
            <?php foreach ($this->events as $i => $row) : ?>
        <?php if (!empty($row->featured)) :   ?>
          <li class="jem-event jem-list-row jem-small-list jem-featured event-id<?php echo $row->id.$this->params->get('pageclass_sfx') . ' event_id' . $this->escape($row->id); ?><?php echo ((int) $row->published === 0) ? ' jem-row-unpublished' : ''; ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
                <?php else : ?>
          <li class="jem-event jem-list-row jem-small-list jem-odd<?php echo ($i % 2) . $this->params->get('pageclass_sfx') . ' event_id' . $this->escape($row->id); ?><?php echo ((int) $row->published === 0) ? ' jem-row-unpublished' : ''; ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
                <?php endif; ?>
            <?php /*<div><?php echo $this->events_pagination->getRowOffset( $i ); ?></div>*/ ?>

            <?php if (empty($this->print) && !empty($this->permissions->canPublishEvent)) : ?>
            <div class="jem-event-info-small jem-myevents-check" >
              <?php
              if (!empty($row->params) && $row->params->get('access-change', false)) :
                echo HTMLHelper::_('grid.id', $i, $row->eventid) . '&nbsp;';
              endif;
              ?>
            </div>
            <?php endif; ?>

            <?php if ($this->showdate == 1) : ?>
            <div class="jem-event-info-small jem-event-date" title="<?php echo Text::_('COM_JEM_TABLE_DATE').': '.strip_tags(JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $this->jemsettings->showtime)); ?>">
              <?php
                echo JemOutput::formatShortDateTime($row->dates, $row->times,
                  $row->enddates, $row->endtimes, $this->jemsettings->showtime);
              ?>
               <?php if ($this->jemsettings->showtitle == 0) : ?>
                <?php echo JemOutput::recurrenceicon($row); ?>
                <?php if (!empty($row->featured)) :?>
                  <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
                <?php endif; ?>
               <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($this->jemsettings->showtitle == 1) : ?>
              <div class="jem-event-info-small jem-event-title" title="<?php echo Text::_('COM_JEM_TABLE_TITLE').': '.$this->escape($row->title); ?>">
                <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>"><?php echo $this->escape($row->title); ?></a>
                <?php echo JemOutput::recurrenceicon($row); ?>
                <?php if (!empty($row->featured)) :?>
                  <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
                <?php endif; ?>
              </div>
            <?php else : ?>
            <?php endif; ?>

            <?php if ($this->jemsettings->showlocate == 1) : ?>
              <?php if (!empty($row->venue)) : ?>
                <div class="jem-event-info-small jem-event-venue" title="<?php echo Text::_('COM_JEM_TABLE_LOCATION').': '.$this->escape($row->venue); ?>">
                  <?php if (($this->jemsettings->showlinkvenue == 1) && !empty($row->venueslug)) : ?>
                    <?php echo "<a href='".Route::_(JemHelperRoute::getVenueRoute($row->venueslug))."'>".$this->escape($row->venue)."</a>"; ?>
                  <?php else : ?>
                    <?php echo $this->escape($row->venue); ?>
                  <?php endif; ?>
                </div>
              <?php else : ?>
                <div class="jem-event-info-small jem-event-venue">
                  -
                </div>
              <?php endif; ?>
            <?php endif; ?>

            <?php if ($this->jemsettings->showcity == 1) : ?>
              <?php if (!empty($row->city)) : ?>
                <div class="jem-event-info-small jem-event-city" title="<?php echo Text::_('COM_JEM_TABLE_CITY').': '.$this->escape($row->city); ?>">
                  <?php echo $this->escape($row->city); ?>
                </div>
              <?php else : ?>
                <div class="jem-event-info-small jem-event-city">-</div>
              <?php endif; ?>
            <?php endif; ?>

            <?php if ($this->jemsettings->showstate == 1) : ?>
              <?php if (!empty($row->state)) : ?>
                <div class="jem-event-info-small jem-event-state" title="<?php echo Text::_('COM_JEM_TABLE_STATE').': '.$this->escape($row->state); ?>">
                  <?php echo $this->escape($row->state); ?>
                </div>
              <?php else : ?>
                <div class="jem-event-info-small jem-event-state">-</div>
              <?php endif; ?>
            <?php endif; ?>

            <?php if ($this->jemsettings->showcat == 1) : ?>
              <div class="jem-event-info-small jem-event-category" title="<?php echo strip_tags(Text::_('COM_JEM_TABLE_CATEGORY').': '.implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist))); ?>">
                <?php echo implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist)); ?>
              </div>
            <?php endif; ?>

                    <?php if ($showAttendeeColumn) : ?>
                    <div class="jem-event-info-small jem-event-attendees" title="<?php echo Text::_('COM_JEM_TABLE_ATTENDEES').': '.$this->escape($row->regCount); ?>">
            <i class="fa fa-user" aria-hidden="true"></i>
                        <?php
                        if ($this->jemsettings->showfroregistra || ($row->registra & 1)) {
                            $linkreg  = 'index.php?option=com_jem&amp;view=attendees&amp;id='.$row->id.'&Itemid='.$this->itemid;
                            $count = $row->regCount;
                            if ($row->maxplaces)
                            {
                                $count .= ' / '.$row->maxplaces;
                                if ($row->waitinglist && $row->waiting) {
                                    $count .= ' + '.$row->waiting;
                                }
                            }
                            if (!empty($row->unregCount)) {
                                $count .= ' - '.(int)$row->unregCount;
                            }
                            if (!empty($row->invited)) {
                                $count .= ', ? '.(int)$row->invited .' ';
                            }

                            if (!empty($row->regTotal) || empty($row->finished)) {
                            ?>
                            <a href="<?php echo $linkreg; ?>" title="<?php echo Text::_('COM_JEM_MYEVENT_MANAGEATTENDEES'); ?>">
                                <?php echo $count; ?>
                            </a>
                            <?php
                            } else {
                                echo $count;
                            }
                        } else {
              echo JemOutput::removebutton(NULL,NULL);
                        }
                        ?>
                    </div>
                    <?php endif; ?>

                    <div class="jem-event-info-small jem-myevents-status jem-status-<?php echo ((int) $row->published === 1) ? 'published' : (((int) $row->published === -2) ? 'trashed' : 'unpublished'); ?>" title="<?php echo Text::_('JSTATUS') . ': ' . jem_frontend_status_label($row->published); ?>" aria-label="<?php echo Text::_('JSTATUS') . ': ' . jem_frontend_status_label($row->published); ?>">
                        <?php
                        $enabled = empty($this->print) && !empty($row->params) && $row->params->get('access-change', false);
                        $statusIcon = JemOutput::publishstateicon($row, array(), false, false);
                        if ($enabled && ((int) $row->published >= 0)) :
                            $statusTask = ((int) $row->published === 1) ? 'unpublish' : 'publish';
                            ?>
                            <a href="javascript:void(0);" onclick="return listItemTask('cb<?php echo $i; ?>','myevents.<?php echo $statusTask; ?>')" class="jem-status-link">
                                <?php echo $statusIcon; ?>
                            </a>
                        <?php else :
                            echo $statusIcon;
                        endif;
                        ?>
                    </div>
                </li>

                <?php $i = 1 - $i; ?>
            <?php endforeach; ?>
        <?php endif; ?>
  </ul>

    <input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
    <input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
    <input type="hidden" name="enableemailaddress" value="<?php echo $this->enableemailaddress; ?>" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="task" value="<?php echo $this->task; ?>" />
    <input type="hidden" name="option" value="com_jem" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>



<div class="pagination">
    <?php echo $this->events_pagination->getPagesLinks(); ?>
</div>
