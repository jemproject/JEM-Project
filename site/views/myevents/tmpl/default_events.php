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

?>

<script>
    function tableOrdering(order, dir, view)
    {
        var form = document.getElementById("adminForm");

        form.filter_order.value     = order;
        form.filter_order_Dir.value = dir;
        form.submit(view);
    }
</script>

<style>
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

</style>

<?php if (!$this->params->get('show_page_heading', 1)) : /* hide this if page heading is shown */ ?>
<h1 class="componentheading"><?php echo Text::_('COM_JEM_MY_EVENTS'); ?></h1>
<?php endif; ?>

<form action="<?php echo htmlspecialchars($this->action); ?>" method="post" id="adminForm" name="adminForm">
    <?php if ($this->settings->get('global_show_filter',1) || $this->settings->get('global_display',1)) : ?>
    <div id="jem_filter" class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <?php if ($this->settings->get('global_show_filter',1)) : ?>
        <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1">
            <label for="filter" class="mb-0"><?php echo Text::_('COM_JEM_FILTER'); ?></label>
            <?php echo $this->lists['filter']; ?>
            <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="form-control form-control-sm" style="flex:1 1 8rem;min-width:6rem;max-width:20rem;" onchange="document.adminForm.submit();" />
            <button class="btn btn-primary btn-sm" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            <button class="btn btn-secondary btn-sm" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
        </div>
        <?php endif; ?>

        <?php if ($this->settings->get('global_display',1)) : ?>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <label for="limit" class="mb-0"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>
            <?php echo $this->events_pagination->getLimitBox(); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="eventtable table jem-myevents table-striped" style="width:<?php echo $this->jemsettings->tablewidth; ?>;" summary="jem">
            <colgroup>
                <?php if (empty($this->print) && !empty($this->permissions->canPublishEvent)) : ?>
                <col wstyle="width: 1%" class="jem_col_checkall" />
                <?php endif; ?>
                <?php if ($this->showdate == 1) : ?>
                <col style="width: <?php echo $this->jemsettings->datewidth; ?>" class="jem_col_date" />
                <?php endif; ?>
                <?php if ($this->jemsettings->showtitle == 1) : ?>
                <col style="width: <?php echo $this->jemsettings->titlewidth; ?>" class="jem_col_title" />
                <?php endif; ?>
                <?php if ($this->jemsettings->showlocate == 1) : ?>
                <col style="width: <?php echo $this->jemsettings->locationwidth; ?>" class="jem_col_venue" />
                <?php endif; ?>
                <?php if ($this->jemsettings->showcity == 1) : ?>
                <col style="width: <?php echo $this->jemsettings->citywidth; ?>" class="jem_col_city" />
                <?php endif; ?>
                <?php if ($this->jemsettings->showstate == 1) : ?>
                <col style="width: <?php echo $this->jemsettings->statewidth; ?>" class="jem_col_state" />
                <?php endif; ?>
                <?php if ($this->jemsettings->showcat == 1) : ?>
                <col style="width: <?php echo $this->jemsettings->catfrowidth; ?>" class="jem_col_category" />
                <?php endif; ?>
                <?php if ($this->params->get('displayattendeecolumn') == 1) : ?>
                <col style="width: "<?php echo $this->jemsettings->attewidth; ?>" class="jem_col_atte" />
                <?php endif; ?>
                <col style="width: 1%" class="jem_col_status" />
            </colgroup>

            <thead>
                <tr>
                    <?php if (empty($this->print) && !empty($this->permissions->canPublishEvent)) : ?>
                    <th class="sectiontableheader center"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
                    <?php endif; ?>
                    <?php if ($this->showdate == 1) : ?>
                    <th id="jem_date" class="sectiontableheader" style="text-align: left;"><i class="far fa-clock" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_DATE', 'a.dates', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                    <?php endif; ?>
                    <?php if ($this->jemsettings->showtitle == 1) : ?>
                    <th id="jem_title" class="sectiontableheader" style="text-align: left;"><i class="fa fa-comment" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_TITLE', 'a.title', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                    <?php endif; ?>
                    <?php if ($this->jemsettings->showlocate == 1) : ?>
                    <th id="jem_location" class="sectiontableheader" style="text-align: left;"><i class="fa fa-map-marker" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'l.venue', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                    <?php endif; ?>
                    <?php if ($this->jemsettings->showcity == 1) : ?>
                    <th id="jem_city" class="sectiontableheader" style="text-align: left;"><i class="fa fa-building" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                    <?php endif; ?>
                    <?php if ($this->jemsettings->showstate == 1) : ?>
                    <th id="jem_state" class="sectiontableheader" style="text-align: left;"><i class="fa fa-map" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_STATE', 'l.state', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                    <?php endif; ?>
                    <?php if ($this->jemsettings->showcat == 1) : ?>
                    <th id="jem_category" class="sectiontableheader" style="text-align: left;"><i class="fa fa-tag" aria-hidden="true"></i>&nbsp;<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CATEGORY', 'c.catname', $this->lists['order_Dir'], $this->lists['order']); ?></th>
                    <?php endif; ?>
                    <?php if ($this->params->get('displayattendeecolumn') == 1) : ?>
                    <th id="jem_atte" class="sectiontableheader" style="text-align: center;"><?php echo Text::_('COM_JEM_TABLE_ATTENDEES'); ?></th>
                    <?php endif; ?>
                    <th id="jem_status" class="sectiontableheader center" nowrap="nowrap" title="<?php echo Text::_('JSTATUS'); ?>" aria-label="<?php echo Text::_('JSTATUS'); ?>">
                        <span class="fa fa-check-circle" aria-hidden="true"></span>&nbsp;<?php echo Text::_('JSTATUS'); ?>
                    </th>
                </tr>
            </thead>

            <tbody>
                <?php if (empty($this->events)) : ?>
                    <tr class="no_events"><td colspan="20"><?php echo Text::_('COM_JEM_NO_EVENTS'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($this->events as $i => $row) : ?>
                        <tr class="row<?php echo $i % 2 . ' event_id' . $this->escape($row->id); ?><?php echo ((int) $row->published === 0) ? ' jem-row-unpublished' : ''; ?>">

                            <?php if (empty($this->print) && !empty($this->permissions->canPublishEvent)) : ?>
                            <td class="center">
                                <?php
                                if (!empty($row->params) && $row->params->get('access-change', false)) :
                                    echo HTMLHelper::_('grid.id', $i, $row->eventid);
                                endif;
                                ?>
                            </td>
                            <?php endif; ?>

                            <?php if ($this->showdate == 1) : ?>
                            <td headers="jem_date" style="text-align: left;">
                                <?php echo JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $this->jemsettings->showtime); ?>
                            </td>
                            <?php endif; ?>

                            <?php if (($this->jemsettings->showtitle == 1) && ($this->jemsettings->showdetails == 1)) : ?>
                            <td headers="jem_title" style="text-align: left; vertical-align: top;">
                                <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>">
                                    <?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row); ?>
                                </a>
                            </td>
                            <?php endif; ?>

                            <?php if (($this->jemsettings->showtitle == 1) && ($this->jemsettings->showdetails == 0)) : ?>
                            <td headers="jem_title" style="text-align: left; vertical-align: top;">
                                <?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row); ?>
                            </td>
                            <?php endif; ?>

                            <?php if ($this->jemsettings->showlocate == 1) : ?>
                            <td headers="jem_location" style="text-align: left; vertical-align: top;">
                                <?php
                                if (!empty($row->venue)) :
                                    if (($this->jemsettings->showlinkvenue == 1) && !empty($row->venueslug)) :
                                        echo "<a href='".Route::_(JemHelperRoute::getVenueRoute($row->venueslug))."'>".$this->escape($row->venue)."</a>";
                                    else :
                                        echo $this->escape($row->venue);
                                    endif;
                                else :
                                    echo '-';
                                endif;
                                ?>
                            </td>
                            <?php endif; ?>

                            <?php if ($this->jemsettings->showcity == 1) : ?>
                            <td headers="jem_city" style="text-align: left; vertical-align: top;">
                                <?php echo !empty($row->city) ? $this->escape($row->city) : '-'; ?>
                            </td>
                            <?php endif; ?>

                            <?php if ($this->jemsettings->showstate == 1) : ?>
                            <td headers="jem_state" style="text-align: left; vertical-align: top;">
                                <?php echo !empty($row->state) ? $this->escape($row->state) : '-'; ?>
                            </td>
                            <?php endif; ?>

                            <?php if ($this->jemsettings->showcat == 1) : ?>
                            <td headers="jem_category" style="text-align: left; vertical-align: top;">
                                <?php echo implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist)); ?>
                            </td>
                            <?php endif; ?>

                            <?php if ($this->params->get('displayattendeecolumn') == 1) : ?>
                            <td headers="jem_atte" style="text-align: center; vertical-align: top;">
                                <?php
                                if ($this->jemsettings->showfroregistra || ($row->registra & 1)) {
                                    $linkreg  = 'index.php?option=com_jem&amp;view=attendees&amp;id='.$row->id.'&Itemid='.$this->itemid;
                                    $count = $row->regCount;
                                    if ($row->maxplaces)
                                    {
                                        $count .= '/'.$row->maxplaces;
                                        if ($row->waitinglist && $row->waiting) {
                                            $count .= ' + '.$row->waiting;
                                        }
                                    }
                                    if (!empty($row->unregCount)) {
                                        $count .= ' - '.(int)$row->unregCount;
                                    }
                                    if (!empty($row->invited)) {
                                        $count .= ', '.(int)$row->invited .' ?';
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
                                    echo HTMLHelper::_('image', 'com_jem/publish_r.webp',NULL,NULL,true);
                                }
                                ?>
                            </td>
                            <?php endif; ?>

                            <td headers="jem_status" class="center jem-status-<?php echo ((int) $row->published === 1) ? 'published' : (((int) $row->published === -2) ? 'trashed' : 'unpublished'); ?>" title="<?php echo Text::_('JSTATUS') . ': ' . jem_frontend_status_label($row->published); ?>" aria-label="<?php echo Text::_('JSTATUS') . ': ' . jem_frontend_status_label($row->published); ?>">
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
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

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
