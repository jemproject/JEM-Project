<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Button\FeaturedButton;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Button\PublishedButton;
use Joomla\String\StringHelper;
use Joomla\Registry\Registry;

HTMLHelper::addIncludePath(JPATH_COMPONENT.'/helpers/html');
$user        = JemFactory::getUser();
$userId        = $user->get('id');
$listOrder    = $this->escape($this->state->get('list.ordering'));
$listDirn    = $this->escape($this->state->get('list.direction'));
$canOrder    = $user->authorise('core.edit.state', 'com_jem.category');
$saveOrder    = $listOrder=='a.ordering';

$params        = (isset($this->state->params)) ? $this->state->params : new Registry();
$settings    = $this->settings;
$wa = $this->document->getWebAssetManager();
$wa->useScript('table.columns');

$eventStatusOptions = array(
    'scheduled'    => array('label' => 'COM_JEM_EVENT_STATUS_SCHEDULED', 'icon' => 'fa fa-calendar', 'class' => 'bg-secondary'),
    'cancelled'    => array('label' => 'COM_JEM_EVENT_STATUS_CANCELLED', 'icon' => 'fa fa-ban', 'class' => 'bg-danger'),
    'postponed'    => array('label' => 'COM_JEM_EVENT_STATUS_POSTPONED', 'icon' => 'fa fa-clock', 'class' => 'bg-warning text-dark'),
    'rescheduled'  => array('label' => 'COM_JEM_EVENT_STATUS_RESCHEDULED', 'icon' => 'fa fa-refresh', 'class' => 'bg-primary'),
    'moved_online' => array('label' => 'COM_JEM_EVENT_STATUS_MOVED_ONLINE', 'icon' => 'fa fa-globe', 'class' => 'bg-success'),
);

$ticketAvailabilityOptions = array(
    'instock'  => array('label' => 'COM_JEM_EVENT_AVAILABILITY_INSTOCK', 'icon' => 'fa fa-check-circle', 'class' => 'bg-success'),
    'preorder' => array('label' => 'COM_JEM_EVENT_AVAILABILITY_PREORDER', 'icon' => 'fa fa-hourglass-half', 'class' => 'bg-warning text-dark'),
    'soldout'  => array('label' => 'COM_JEM_EVENT_AVAILABILITY_SOLDOUT', 'icon' => 'fa fa-times-circle', 'class' => 'bg-danger'),
);
?>
<script>
    $(document).ready(function() {
        var h = <?php echo $settings->get('highlight','0'); ?>;

        switch(h)
        {
            case 0:
                break;
            case 1:
                highlightevents();
                break;
        }
    });
</script>

<form action="<?php echo Route::_('index.php?option=com_jem&view=events'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <fieldset id="filter-bar" class=" mb-3">
            <div class="jem-admin-filter-bar">
                <div class="jem-admin-filter-item">
                    <?php echo $this->lists['filter']; ?>
                </div>
                <div class="jem-admin-filter-search">
                    <div class="input-group">
                        <input type="text" name="filter_search" id="filter_search" class="form-control" aria-describedby="filter_search-desc" placeholder="<?php echo Text::_('COM_JEM_SEARCH');?>" value="<?php echo $this->escape($this->state->get('filter_search')); ?>"  inputmode="search" onChange="document.adminForm.submit();" >

                        <button type="submit" class="filter-search-bar__button btn btn-primary" aria-label="Search">
                            <span class="filter-search-bar__button-icon icon-search" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('filter_search').value='';this.form.filter_state.value='';document.getElementById('filter_category_id').value='0';document.getElementById('filter_event_type_id').value='0';document.getElementById('filter_venue_id').value='0';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
                    </div>
                </div>
                <div class="jem-admin-filter-date-range">
                    <div class="jem-admin-filter-calendar">
                        <?php echo HTMLHelper::_('calendar', $this->state->get('filter_begin'), 'filter_begin', 'filter_begin', '%Y-%m-%d' , array('size'=>10, 'onchange'=>"this.form.fireEvent('submit');this.form.submit()",'placeholder'=>Text::_('COM_JEM_EVENTS_FILTER_STARTDATE')));?>
                    </div>
                    <div class="jem-admin-filter-calendar">
                        <?php echo HTMLHelper::_('calendar', $this->state->get('filter_end'), 'filter_end', 'filter_end', '%Y-%m-%d' , array('size'=>10, 'onchange'=>"this.form.fireEvent('submit');this.form.submit()",'placeholder'=>Text::_('COM_JEM_EVENTS_FILTER_ENDDATE') ));?>
                    </div>
                </div>
                <div class="jem-admin-filter-item">
                    <?php echo $this->lists['event_type_filter']; ?>
                </div>
                <div class="jem-admin-filter-item">
                    <?php echo $this->lists['category_filter']; ?>
                </div>
                <div class="jem-admin-filter-item">
                    <select name="filter_state" class="inputbox form-select wauto-minwmax" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED');?></option>
                        <?php echo HTMLHelper::_('select.options', HTMLHelper::_('jgrid.publishedOptions'), 'value', 'text', $this->state->get('filter_state'), true);?>
                    </select>
                </div>
                <div class="jem-admin-filter-item">
                    <select name="filter_access" class="inputbox form-select wauto-minwmax" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('JOPTION_SELECT_ACCESS');?></option>
                        <?php echo HTMLHelper::_('select.options', HTMLHelper::_('access.assetgroups'), 'value', 'text', $this->state->get('filter.access'));?>
                    </select>
                </div>
                <div class="jem-admin-filter-limit">
                    <?php echo $this->pagination->getLimitBox(); ?>
                </div>
            </div>
        </fieldset>
        <div class="clr"> </div>
        <div class="table">
            <table class="table table-striped itemList" id="eventList">
                <thead>
                <tr>
                    <th class="center jem-list-check"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
                    <th class="center jem-list-featured"><?php echo HTMLHelper::_('grid.sort', 'JFEATURED', 'a.featured', $listDirn, $listOrder, NULL, 'desc'); ?></th>
                    <th class="center nowrap jem-list-status"><?php echo Text::_('JSTATUS'); ?></th>
                    <th class="nowrap"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_DATE', 'a.dates', $listDirn, $listOrder ); ?></th>
                    <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_STARTTIME_SHORT', 'a.times', $listDirn, $listOrder ); ?></th>
                    <th class="nowrap"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_EVENT_TITLE', 'a.title', $listDirn, $listOrder ); ?></th>
                    <th class="nowrap center"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_EVENT_FIELD_EVENT_STATUS_LABEL', 'a.event_status', $listDirn, $listOrder ); ?></th>
                    <th class="nowrap center"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_EVENT_FIELD_TICKET_AVAILABILITY_LABEL', 'a.ticket_availability', $listDirn, $listOrder ); ?></th>
                    <th class="nowrap"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TYPE', 'jt.name', $listDirn, $listOrder ); ?></th>
                    <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_VENUE', 'loc.venue', $listDirn, $listOrder ); ?></th>
                    <th><?php echo Text::_('COM_JEM_CATEGORIES'); ?></th>
                    <th style="width: 1%" class="center nowrap"><?php echo Text::_('COM_JEM_REGISTERED_USERS_SHORT'); ?></th>
                    <th style="width: 1%" class="center nowrap"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ARTICLE_ID', 'a.article_id', $listDirn, $listOrder); ?></th>
                    <th style="width: 9%" class="center"><?php echo HTMLHelper::_('grid.sort',  'JGRID_HEADING_ACCESS', 'a.access', $listDirn, $listOrder); ?></th>
                    <th class="nowrap"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_AUTHOR', 'u.name', $listDirn, $listOrder); ?></th>
                    <th class="center nowrap"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_HITS', 'a.hits', $listDirn, $listOrder); ?></th>
                    <th class="center nowrap"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_LAST_VISIT', 'a.last_visit', $listDirn, $listOrder); ?></th>
                    <th class="center nowrap"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_DATE_CREATED', 'a.created', $listDirn, $listOrder); ?></th>
                    <th style="width: 1%" class="center nowrap"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ID', 'a.id', $listDirn, $listOrder ); ?></th>
                </tr>
                </thead>

                <tbody id="search_in_here">
                <?php
                foreach ($this->items as $i => $row) :
                    //Prepare date
                    $displaydate = JemOutput::formatShortDateTime($row->dates, null, $row->enddates, null, $this->jemsettings->showtime);
                    // Insert a break between date and enddate if possible
                    $displaydate = str_replace(" - ", " -<br>", $displaydate);

                    //Prepare time
                    if (!$row->times) {
                        $displaytime = '-';
                    } else {
                        $displaytime = JemOutput::formattime($row->times);
                    }

                    $ordering    = ($listOrder == 'ordering');
                    $canCreate    = $user->authorise('core.create');
                    $canEdit    = $user->authorise('core.edit');
                    $canCheckin    = $user->authorise('core.manage', 'com_checkin') || $row->checked_out == $userId || $row->checked_out == 0;
                    $canChange    = $user->authorise('core.edit.state') && $canCheckin;

                    $venuelink         = 'index.php?option=com_jem&amp;task=venue.edit&amp;id='.$row->locid;
                    $published         = HTMLHelper::_('jgrid.published', $row->published, $i, 'events.');
                    ?>
                    <tr class="row<?php echo $i % 2; ?>">
                        <td class="center"><?php echo HTMLHelper::_('grid.id', $i, $row->id); ?></td>
                        <td class="center">
                            <?php
                            $options = [
                                'task_prefix' => 'events.',
                                'disabled' => !$canChange,
                                'id' => 'featured-' . $row->id
                            ];
                            echo (new FeaturedButton())->render((int) $row->featured, $i, $options);
                            ?>
                        </td>
                        <td class="center">
                            <?php
                            $options = [
                            'task_prefix' => 'events.',
                            'disabled' => !$canChange,
                            'id' => 'state-' . $row->id
                            ];
                            echo (new PublishedButton())->render((int) $row->published, $i, $options, $row->publish_up, $row->publish_down);
                            ?>
                        </td>
                        <td class="startdate">
                            <?php if ($row->checked_out) : ?>
                                <?php echo HTMLHelper::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'events.', $canCheckin); ?>
                            <?php endif; ?>
                            <?php if ($canEdit) : ?>
                                <a href="<?php echo Route::_('index.php?option=com_jem&task=event.edit&id='.(int) $row->id); ?>">
                                    <?php echo $displaydate; ?>
                                </a>
                            <?php else : ?>
                                <?php echo $displaydate; ?>
                            <?php endif; ?>
                        </td>
                        <td class="starttime"><?php echo $displaytime; ?></td>
                        <td class="eventtitle">
                            <?php if ($canEdit) : ?>
                                <a href="<?php echo Route::_('index.php?option=com_jem&task=event.edit&id='.(int) $row->id); ?>">
                                    <?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row); ?>
                                </a>
                            <?php else : ?>
                                <?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row); ?>
                            <?php endif; ?>
                            <br>
                            <?php if (StringHelper::strlen($row->alias) > 25) : ?>
                                <?php echo StringHelper::substr( $this->escape($row->alias), 0 , 25).'...'; ?>
                            <?php else : ?>
                                <?php echo $this->escape($row->alias); ?>
                            <?php endif; ?>
                        </td>
                        <td class="center">
                            <?php
                            $eventStatus = empty($row->event_status) ? 'scheduled' : $row->event_status;
                            $eventStatus = isset($eventStatusOptions[$eventStatus]) ? $eventStatus : 'scheduled';
                            $eventStatusOption = $eventStatusOptions[$eventStatus];
                            $eventStatusText = Text::_($eventStatusOption['label']);
                            ?>
                            <span class="badge <?php echo $eventStatusOption['class']; ?>" title="<?php echo $this->escape($eventStatusText); ?>" aria-label="<?php echo $this->escape($eventStatusText); ?>">
                                <span class="<?php echo $eventStatusOption['icon']; ?>" aria-hidden="true"></span>
                                <?php echo $this->escape($eventStatusText); ?>
                            </span>
                        </td>
                        <td class="center">
                            <?php
                            $ticketAvailability = empty($row->ticket_availability) ? 'instock' : $row->ticket_availability;
                            $ticketAvailability = isset($ticketAvailabilityOptions[$ticketAvailability]) ? $ticketAvailability : 'instock';
                            $ticketAvailabilityOption = $ticketAvailabilityOptions[$ticketAvailability];
                            $ticketAvailabilityText = Text::_($ticketAvailabilityOption['label']);
                            ?>
                            <span class="badge <?php echo $ticketAvailabilityOption['class']; ?>" title="<?php echo $this->escape($ticketAvailabilityText); ?>" aria-label="<?php echo $this->escape($ticketAvailabilityText); ?>">
                                <span class="<?php echo $ticketAvailabilityOption['icon']; ?>" aria-hidden="true"></span>
                                <?php echo $this->escape($ticketAvailabilityText); ?>
                            </span>
                        </td>
                        <td class="type">
                            <?php echo !empty($row->type_name) ? $this->escape($row->type_name) : '-'; ?>
                        </td>
                        <td class="venue">
                            <?php if ($row->venue) : ?>
                                <?php if ( $row->vchecked_out && ( $row->vchecked_out != $this->user->get('id') ) ) : ?>
                                    <?php echo $this->escape($row->venue); ?>
                                <?php else : ?>
                                    <span <?php echo JEMOutput::tooltip(Text::_('COM_JEM_EDIT_VENUE'), $row->venue, 'editlinktip'); ?>>
                                        <a href="<?php echo $venuelink; ?>">
                                            <?php echo $this->escape($row->venue); ?>
                                        </a>
                                    </span>
                                <?php endif; ?>
                            <?php else : ?>
                                <?php echo '-'; ?>
                            <?php endif; ?>
                        </td>
                        <td class="category">
                            <?php echo implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist,true)); ?>
                        </td>
                        <td class="center">
                            <?php
                            if ($this->jemsettings->showfroregistra || ($row->registra & 1)) {
                                $linkreg     = 'index.php?option=com_jem&amp;view=attendees&amp;eventid='.$row->id;
                                $count = $row->regCount+$row->reserved;
                                if ($row->maxplaces)
                                {
                                    $count .= '/'.$row->maxplaces;
                                    if ($row->waitinglist && $row->waiting) {
                                        $count .= '+'.$row->waiting;
                                    }
                                }
                                if (!empty($row->unregCount)) {
                                    $count .= '-'.(int)$row->unregCount;
                                }
                                if (!empty($row->invited)) {
                                    $count .= ','.(int)$row->invited .'?';
                                }
                                ?>
                                <a href="<?php echo $linkreg; ?>" title="<?php echo Text::_('COM_JEM_EVENTS_MANAGEATTENDEES'); ?>">
                                    <?php echo $count; ?>
                                </a>
                            <?php } else { ?>
                                <?php echo HTMLHelper::_('image', 'com_jem/publish_r.webp', NULL, NULL, true); ?>
                            <?php } ?>
                        </td>
                        <td class="center">
                            <?php if (!empty($row->article_id)) : ?>
                                <a href="<?php echo Route::_('index.php?option=com_content&task=article.edit&id=' . (int) $row->article_id); ?>" title="<?php echo Text::_('COM_JEM_EDIT_ASSOCIATED_ARTICLE'); ?>">
                                    <?php echo (int) $row->article_id; ?>
                                </a>
                            <?php else : ?>
                                <?php echo '-'; ?>
                            <?php endif; ?>
                        </td>
                        <td class="center">
                            <?php echo $this->escape($row->access_level); ?>
                        </td>
                        <td>
                            <?php
                            $created = HTMLHelper::_('date', $row->created, Text::_('DATE_FORMAT_LC5'));
                            $overlib = Text::_('COM_JEM_CREATED_AT') . ': ' . $created . '<br>';
                            $overlib .= Text::_('COM_JEM_AUTHOR') . ': ' . $row->author . '<br>';
                            $overlib .= Text::_('COM_JEM_EMAIL') . ': ' . $row->email . '<br>';
                            if ($row->author_ip != '') {
                                $overlib .= Text::_('COM_JEM_WITH_IP') . ': ' . $row->author_ip . '<br>';
                            }
                            if (!empty($row->modified)) {
                                $overlib .= '<br>' . Text::_('COM_JEM_EDITED_AT') . ': ' . HTMLHelper::_('date', $row->modified, Text::_('DATE_FORMAT_LC5')) . '<br>' . Text::_('COM_JEM_GLOBAL_MODIFIEDBY') . ': ' . $row->modified_by;
                            }
                            ?>
                            <span <?php echo JEMOutput::tooltip(Text::_('COM_JEM_EVENTS_STATS'), $overlib, 'editlinktip'); ?>>
                                <a href="<?php echo 'index.php?option=com_users&amp;task=edit&amp;hidemainmenu=1&amp;cid[]=' . (int) $row->created_by; ?>"><?php echo $this->escape($row->author); ?></a>
                            </span>
                        </td>
                        <td class="center"><?php echo (int) $row->hits; ?></td>
                        <td class="center nowrap"><?php echo $row->last_visit ? HTMLHelper::_('date', $row->last_visit, Text::_('DATE_FORMAT_LC5')) : '-'; ?></td>
                        <td class="center"><?php echo $created; ?></td>
                        <td class="center">
                            <?php echo (int) $row->id; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="ms-auto mb-4 me-0">
                <?php echo  (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks(null) : $this->pagination->getListFooter()); ?>
            </div>

            <?php if ($user->authorise('core.edit', 'com_jem')) : ?>
                <template id="joomla-dialog-batch"><?php echo $this->loadTemplate('batch_body'); ?></template>
            <?php endif; ?>
        </div>
    </div>
    <?php //if (isset($this->sidebar)) : ?>
    <?php //endif; ?>

    <div>
        <input type="hidden" name="task" value="" />
        <input type="hidden" name="boxchecked" value="0" />
        <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
        <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
        <input type="hidden" name="filter_venue_id" id="filter_venue_id" value="<?php echo (int) $this->state->get('filter_venue_id'); ?>" />

        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
