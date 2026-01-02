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

<?php if ($this->settings->get('global_show_filter',1) || $this->settings->get('global_display',1)) : ?>
    <div id="jem_filter" class="floattext">
        <?php if ($this->settings->get('global_show_filter',1)) : ?>
            <div class="jem_fleft">
                <?php echo $this->lists['filter'].'&nbsp;'; ?>
                <input type="text" name="filter_search" id="filter_search" class="inputbox form-control" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8');?>" onchange="document.adminForm.submit();" />
                <label for="filter_month"><?php echo Text::_('COM_JEM_SEARCH_MONTH'); ?></label>
                <input type="month" name="filter_month" id="filter_month" pattern="[0-9]{4}-[0-9]{2}" title="<?php echo Text::_('COM_JEM_SEARCH_YYYY-MM_FORMAT'); ?>" class="inputbox form-control" placeholder="<?php echo Text::_('COM_JEM_SEARCH_YYYY-MM'); ?>" size="7" value="<?php echo $this->lists['month'] ?? '';?>">
                <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
                <button class="btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';document.getElementById('filter_month').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
            </div>
        <?php endif; ?>

        <?php if ($this->settings->get('global_display',1)) : ?>
            <div class="jem_fright">
                <label for="limit"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>
                <?php echo $this->pagination->getLimitBox(); ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php $paramShowIconsOrder = $this->params->get('showiconsinorder',1); ?>
<?php $showiconsineventtitle = $this->params->get('showiconsineventtitle',1); ?>
<?php $showiconsineventdata = $this->params->get('showiconsineventdata',1); ?>

<div class="table-responsive">
    <table class="eventtable table table-striped" style="width:<?php echo !empty($this->jemsettings->tablewidth) ? $this->jemsettings->tablewidth : '100%'; ?>;">
        <colgroup>
            <?php if ($this->jemsettings->showeventimage == 1) : ?>
                <col style="width:<?php echo $this->jemsettings->tableeventimagewidth; ?>" class="jem_col_event_image" />
            <?php endif; ?>
            <col style="width:<?php echo $this->jemsettings->datewidth; ?>" class="jem_col_date" />
            <?php if ($this->jemsettings->showtitle == 1) : ?>
                <col style="width:<?php echo $this->jemsettings->titlewidth; ?>" class="jem_col_title" />
            <?php endif; ?>
            <?php if ($this->jemsettings->showlocate == 1) : ?>
                <col style="width:<?php echo $this->jemsettings->locationwidth; ?>" class="jem_col_venue" />
            <?php endif; ?>
            <?php if ($this->jemsettings->showcity == 1) : ?>
                <col style="width:<?php echo $this->jemsettings->citywidth; ?>" class="jem_col_city" />
            <?php endif; ?>
            <?php if ($this->jemsettings->showstate == 1) : ?>
                <col style="width:<?php echo $this->jemsettings->statewidth; ?>" class="jem_col_state" />
            <?php endif; ?>
            <?php if ($this->jemsettings->showcat == 1) : ?>
                <col style="width:<?php echo $this->jemsettings->catfrowidth; ?>" class="jem_col_category" />
            <?php endif; ?>
            <?php if ($this->jemsettings->showatte == 1) : ?>
                <col style="width:<?php echo $this->jemsettings->attewidth; ?>" class="jem_col_attendees" />
            <?php endif; ?>
        </colgroup>

        <thead>
        <tr>
            <?php if ($this->jemsettings->showeventimage == 1) : ?>
                <th id="jem_eventimage" class="sectiontableheader"><?php echo ($paramShowIconsOrder? '<i class="far fa-image" aria-hidden="true"></i>&nbsp;' : '');?><?php echo Text::_('COM_JEM_TABLE_EVENTIMAGE'); ?></th>
            <?php endif; ?>
            <th id="jem_date" class="sectiontableheader"><?php echo ($paramShowIconsOrder? '<i class="far fa-clock" aria-hidden="true"></i>&nbsp;' : '');?><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_DATE', 'a.dates', $this->lists['order_Dir'], $this->lists['order']); ?></th>
            <?php if ($this->jemsettings->showtitle == 1) : ?>
                <th id="jem_title" class="sectiontableheader"><?php echo ($paramShowIconsOrder? '<i class="fa fa-comment" aria-hidden="true"></i>&nbsp;' : '');?><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_TITLE', 'a.title', $this->lists['order_Dir'], $this->lists['order']); ?></th>
            <?php elseif ($this->params->get('show_introtext_events') == 1): ?>
                <th id="jem_title" class="sectiontableheader"><?php echo ($paramShowIconsOrder? '<i class="fa fa-textt" aria-hidden="true"></i>&nbsp;' : '');?><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_DESCRIPTION', 'a.title', $this->lists['order_Dir'], $this->lists['order']); ?></th>
            <?php endif; ?>
            <?php if ($this->jemsettings->showlocate == 1) : ?>
                <th id="jem_location" class="sectiontableheader"><?php echo ($paramShowIconsOrder? '<i class="fa fa-map-marker" aria-hidden="true"></i>&nbsp;' : '');?><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'l.venue', $this->lists['order_Dir'], $this->lists['order']); ?></th>
            <?php endif; ?>
            <?php if ($this->jemsettings->showcity == 1) : ?>
                <th id="jem_city" class="sectiontableheader"><?php echo ($paramShowIconsOrder? '<i class="fa fa-building" aria-hidden="true"></i>&nbsp;' : '');?><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order']); ?></th>
            <?php endif; ?>
            <?php if ($this->jemsettings->showstate == 1) : ?>
                <th id="jem_state" class="sectiontableheader"><?php echo ($paramShowIconsOrder? '<i class="fa fa-map" aria-hidden="true"></i>&nbsp;' : '');?><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_STATE', 'l.state', $this->lists['order_Dir'], $this->lists['order']); ?></th>
            <?php endif; ?>
            <?php if ($this->jemsettings->showcat == 1) : ?>
                <th id="jem_category" class="sectiontableheader"><?php echo ($paramShowIconsOrder? '<i class="fa fa-tag" aria-hidden="true"></i>&nbsp;' : '');?><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CATEGORY', 'c.catname', $this->lists['order_Dir'], $this->lists['order']); ?></th>
            <?php endif; ?>
            <?php if ($this->jemsettings->showatte == 1) : ?>
                <th id="jem_attendees" class="sectiontableheader"><?php echo ($paramShowIconsOrder? '<i class="fa fa-user" aria-hidden="true"></i>&nbsp;' : '');?><?php echo Text::_('COM_JEM_TABLE_ATTENDEES'); ?></th>
            <?php endif; ?>
        </tr>
        </thead>

        <tbody>
        <?php if (empty($this->rows)) : ?>
            <tr class="no_events"><td colspan="20"><?php echo Text::_('COM_JEM_NO_EVENTS'); ?></td></tr>
        <?php else : ?>
            <?php $odd = 0; ?>
            <?php foreach ($this->rows as $row) : ?>
                <?php $odd = 1 - $odd; ?>
                <?php
                // has user access
                $eventaccess = '';
                if (!$row->user_has_access_event) {
                    // show a closed lock icon
                    $statusicon = JemOutput::publishstateicon($row);
                    $eventaccess = '<span class="icon-lock jem-lockicon" aria-hidden="true"></span>';
                }
                ?>
                <?php if (!empty($row->featured)) : ?>
                    <tr class="featured featured<?php echo $row->id.$this->params->get('pageclass_sfx') . ' event_id' . $this->escape($row->id); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
                <?php else : ?>
                    <tr class="sectiontableentry<?php echo ($odd + 1) . $this->params->get('pageclass_sfx') . ' event_id' . $this->escape($row->id); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
                <?php endif; ?>

                <?php if ($this->jemsettings->showeventimage == 1) : ?>
                    <td headers="jem_eventimage" class="header-td">
                        <?php if (!empty($row->datimage)) : ?>
                            <?php
                            $dimage = JemImage::flyercreator($row->datimage, 'event');
                            echo JemOutput::flyer($row, $dimage, 'event');
                            ?>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>

                <td headers="jem_date" class="header-td">
                    <?php
                    echo ($showiconsineventdata? '<i class="far fa-clock" aria-hidden="true">&nbsp;</i>':'');
                    echo JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $this->jemsettings->showtime);
                    echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes);
                    ?>
                </td>

                <?php if ($this->jemsettings->showtitle == 1 ) : ?>
                    <td headers="jem_title" class="header-td">
                        <?php if ($this->jemsettings->showdetails == 1) : ?>
                            <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>">
                        <?php endif; ?>
                        <span itemprop="name"><?php echo $this->escape($row->title) . ($showiconsineventtitle? JemOutput::recurrenceicon($row) : '');?></span>
                        <?php if ($this->jemsettings->showdetails == 1) : ?>
                            </a>
                        <?php endif; ?>
                        <?php echo JemOutput::publishstateicon($row);
                        echo "<meta itemprop='url' content='" . Route::_(JemHelperRoute::getEventRoute($row->slug)) . "'>"; ?>
                        <?php if (!empty($row->featured)) :
                            echo ($showiconsineventtitle? '<i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>':'');
                        endif;
                        echo $eventaccess;

                        if ($this->params->get('show_introtext_events') == 1) : ?>
                            <div class="jem-event-intro">
                                <?php echo $row->introtext; ?>
                                <?php if ($this->settings->get('event_show_readmore') && $row->fulltext != '' && $row->fulltext != '<br>') : ?>
                                    <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>"><?php echo Text::_('COM_JEM_EVENT_READ_MORE_TITLE'); ?></a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>

                <?php if ($this->jemsettings->showtitle == 0) : ?>
                    <td headers="jem_title" class="header-td" itemprop="name">
                        <?php echo $this->escape($row->title) . ($showiconsineventtitle? JemOutput::recurrenceicon($row) : '') . JemOutput::publishstateicon($row);
                        if (!empty($row->featured)) :
                            echo ($showiconsineventtitle? '<i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>':'');
                        endif;
                        echo $eventaccess;

                        if ($this->params->get('show_introtext_events') == 1) : ?>
                            <div class="jem-event-intro">
                                <?php echo $row->introtext; ?>
                                <?php if ($this->settings->get('event_show_readmore') && $row->fulltext != '' && $row->fulltext != '<br>') : ?>
                                    <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>"><?php echo Text::_('COM_JEM_EVENT_READ_MORE_TITLE'); ?></a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php echo "<meta itemprop='url' content='" . Route::_(JemHelperRoute::getEventRoute($row->slug)) . "'>"; ?>
                    </td>
                <?php endif; ?>

                <?php if ($this->jemsettings->showlocate == 1) : ?>
                    <td headers="jem_location" class="header-td" itemtype="https://schema.org/Place" itemscope itemprop="location">
                        <?php echo ($showiconsineventdata? '<i class="fa fa-map-marker" aria-hidden="true"></i>':''); ?>
                        <?php
                        if (!empty($row->venue)) :
                            if (($this->jemsettings->showlinkvenue == 1) && !empty($row->venueslug)) :
                                echo "<a href='".Route::_(JemHelperRoute::getVenueRoute($row->venueslug))."'><span itemprop='name'>".$this->escape($row->venue)."</span></a>";
                            else :
                                echo "<span itemprop='name'>" . $this->escape($row->venue)."</span>";
                            endif;
                        else :
                            echo "-<meta itemprop='name' content='' />";
                        endif; ?>
                        <div itemprop="address" itemscope itemtype="https://schema.org/PostalAddress" style="display:none;">
                            <meta itemprop="streetAddress" content="<?php echo $this->escape($row->street); ?>" />
                            <meta itemprop="addressLocality" content="<?php echo $this->escape($row->city); ?>" />
                            <meta itemprop="addressRegion" content="<?php echo $this->escape($row->state); ?>" />
                            <meta itemprop="postalCode" content="<?php echo $this->escape($row->postalCode); ?>" />
                        </div>

                    </td>
                <?php endif; ?>

                <?php if ($this->jemsettings->showcity == 1) : ?>
                    <td headers="jem_city" class="header-td">
                        <?php echo ($showiconsineventdata? '<i class="fa fa-building" aria-hidden="true"></i>':''); ?>
                        <?php echo !empty($row->city) ? $this->escape($row->city) : "-"; ?>
                    </td>
                <?php endif; ?>

                <?php if ($this->jemsettings->showstate == 1) : ?>
                    <td headers="jem_state" class="header-td">
                        <?php echo ($showiconsineventdata? '<i class="fa fa-map" aria-hidden="true"></i>':''); ?>
                        <?php echo !empty($row->state) ? $this->escape($row->state) : "-"; ?>
                    </td>
                <?php endif; ?>

                <?php if ($this->jemsettings->showcat == 1) : ?>
                    <td headers="jem_category" class="header-td">
                        <?php echo ($showiconsineventdata? '<i class="fa fa-tag" aria-hidden="true"></i>':''); ?>
                        <?php echo implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist)); ?>
                    </td>
                <?php endif; ?>

                <?php if ($this->jemsettings->showatte == 1) : ?>
                    <td headers="jem_attendees" class="header-td">
                        <?php echo ($showiconsineventdata? '<i class="fa fa-user" aria-hidden="true"></i>':''); ?>
                        <?php
                        if (!empty($row->regCount)) :
                            echo $this->escape($row->regCount), " / ", $this->escape($row->maxplaces);
                        else :
                            echo "- / ", $this->escape ($row->maxplaces);
                        endif;
                        ?>
                    </td>
                <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php echo JemOutput::lightbox(); ?>
