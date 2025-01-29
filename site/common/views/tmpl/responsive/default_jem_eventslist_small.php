<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$uri = Uri::getInstance();
?>

<script>
    function tableOrdering(order, dir, view)
    {
        var form = document.getElementById("adminForm");

        form.filter_order.value 	= order;
        form.filter_order_Dir.value	= dir;
        form.submit(view);
    }
</script>

<style>
    <?php if (!empty($this->jemsettings->tablewidth)) : ?>
    #jem #adminForm {
        width: <?php echo ($this->jemsettings->tablewidth); ?>;
    }
    <?php endif; ?>

    .jem-sort #jem_date,
    #jem .jem-event .jem-event-date {
    <?php if (!empty($this->jemsettings->datewidth)) : ?>
        flex: 1 <?php echo ($this->jemsettings->datewidth); ?>;
    <?php else : ?>
        flex: 1;
    <?php endif; ?>
    }

    .jem-sort #jem_title,
    #jem .jem-event .jem-event-title {
    <?php if (($this->jemsettings->showtitle == 1) && (!empty($this->jemsettings->titlewidth))) : ?>
        flex: 1 <?php echo ($this->jemsettings->titlewidth); ?>;
    <?php else : ?>
        flex: 1;
    <?php endif; ?>
    }

    .jem-sort #jem_location,
    #jem .jem-event .jem-event-venue {
    <?php if (($this->jemsettings->showlocate == 1) && (!empty($this->jemsettings->locationwidth))) : ?>
        flex: 1 <?php echo ($this->jemsettings->locationwidth); ?>;
    <?php else : ?>
        flex: 1;
    <?php endif; ?>
    }

    .jem-sort #jem_city,
    #jem .jem-event .jem-event-city {
    <?php if (($this->jemsettings->showcity == 1) && (!empty($this->jemsettings->citywidth))) : ?>
        flex: 1 <?php echo ($this->jemsettings->citywidth); ?>;
    <?php else : ?>
        flex: 1;
    <?php endif; ?>
    }

    .jem-sort #jem_state,
    #jem .jem-event .jem-event-state {
    <?php if (($this->jemsettings->showstate == 1) && (!empty($this->jemsettings->statewidth))) : ?>
        flex: 1 <?php echo ($this->jemsettings->statewidth); ?>;
    <?php else : ?>
        flex: 1;
    <?php endif; ?>
    }

    .jem-sort #jem_category,
    #jem .jem-event .jem-event-category {
    <?php if (($this->jemsettings->showcat == 1) && (!empty($this->jemsettings->catfrowidth))) : ?>
        flex: 1 <?php echo ($this->jemsettings->catfrowidth); ?>;
    <?php else : ?>
        flex: 1;
    <?php endif; ?>
    }

    .jem-sort #jem_atte,
    #jem .jem-event .jem-event-attendees {
    <?php if (($this->jemsettings->showatte == 1) && (!empty($this->jemsettings->attewidth))) : ?>
        flex: 1 <?php echo ($this->jemsettings->attewidth); ?>;
    <?php else : ?>
        flex: 1;
    <?php endif; ?>
    }
</style>

<?php
function jem_common_show_filter(&$obj) {
    if ($obj->settings->get('global_show_filter',1) && !JemHelper::jemStringContains($obj->params->get('pageclass_sfx'), 'jem-hidefilter')) {
        return true;
    }
    if (JemHelper::jemStringContains($obj->params->get('pageclass_sfx'), 'jem-showfilter')) {
        return true;
    }
    return false;
}
?>
<?php if (jem_common_show_filter($this) && !JemHelper::jemStringContains($this->params->get('pageclass_sfx'), 'jem-filterbelow')): ?>
    <div id="jem_filter" class="floattext jem-form jem-row jem-justify-start">
        <div class="jem-row jem-justify-start jem-nowrap">
            <?php echo $this->lists['filter']; ?>
            <input type="text" name="filter_search" id="filter_search" class="inputbox form-control" value="<?php echo $this->lists['search'];?>" onchange="document.adminForm.submit();" />
        </div>
        <div class="jem-row jem-justify-start jem-nowrap">
            <label for="filter_month"><?php echo Text::_('COM_JEM_SEARCH_MONTH'); ?></label>
            <input type="month" name="filter_month" id="filter_month" pattern="[0-9]{4}-[0-9]{2}" title="<?php echo Text::_('COM_JEM_SEARCH_YYYY-MM_FORMAT'); ?>" required class="inputbox form-control" placeholder="<?php echo Text::_('COM_JEM_SEARCH_YYYY-MM'); ?>" size="7" value="<?php echo $this->lists['month'] ?? '';?>">
        </div>
        <div class="jem-row jem-justify-start jem-nowrap">
            <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            <button class="btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
        </div>
        <?php if ($this->settings->get('global_display',1)) : ?>
            <div class="jem-limit-smallist">
                <?php
                echo '<label for="limit" class="jem-limit-text">'.Text::_('COM_JEM_DISPLAY_NUM').'</label>&nbsp;';
                //echo '<span class="jem-limit-text">'.Text::_('COM_JEM_DISPLAY_NUM').'</span>&nbsp;';
                echo $this->pagination->getLimitBox();
                ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php $paramShowIconsOrder = $this->params->get('showiconsinorder',1); ?>
<?php $showiconsineventtitle = $this->params->get('showiconsineventtitle',1); ?>
<?php $showiconsineventdata = $this->params->get('showiconsineventdata',1); ?>

<div class="jem-sort jem-sort-small">
    <div class="jem-list-row jem-small-list">
        <div id="jem_date" class="sectiontableheader"><?php echo ($paramShowIconsOrder? '<i class="far fa-clock" aria-hidden="true"></i>&nbsp;' : '');?><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_DATE', 'a.dates', $this->lists['order_Dir'], $this->lists['order']); ?></div>
        <?php if ($this->jemsettings->showtitle == 1) : ?>
            <div id="jem_title" class="sectiontableheader"><?php echo ($paramShowIconsOrder? '<i class="fa fa-comment" aria-hidden="true"></i>&nbsp;' : '');?><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_TITLE', 'a.title', $this->lists['order_Dir'], $this->lists['order']); ?></div>
        <?php endif; ?>
        <?php if ($this->jemsettings->showlocate == 1) : ?>
            <div id="jem_location" class="sectiontableheader"><?php echo ($paramShowIconsOrder? '<i class="fa fa-map-marker" aria-hidden="true"></i>&nbsp;' : '');?><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_LOCATION', 'l.venue', $this->lists['order_Dir'], $this->lists['order']); ?></div>
        <?php endif; ?>
        <?php if ($this->jemsettings->showcity == 1) : ?>
            <div id="jem_city" class="sectiontableheader"><?php echo ($paramShowIconsOrder? '<i class="fa fa-building" aria-hidden="true"></i>&nbsp;' : '');?><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order']); ?></div>
        <?php endif; ?>
        <?php if ($this->jemsettings->showstate == 1) : ?>
            <div id="jem_state" class="sectiontableheader"><?php echo ($paramShowIconsOrder? '<i class="fa fa-map" aria-hidden="true"></i>&nbsp;' : '');?><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_STATE', 'l.state', $this->lists['order_Dir'], $this->lists['order']); ?></div>
        <?php endif; ?>
        <?php if ($this->jemsettings->showcat == 1) : ?>
            <div id="jem_category" class="sectiontableheader"><?php echo ($paramShowIconsOrder? '<i class="fa fa-tag" aria-hidden="true"></i>&nbsp;' : '');?><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TABLE_CATEGORY', 'c.catname', $this->lists['order_Dir'], $this->lists['order']); ?></div>
        <?php endif; ?>
        <?php if ($this->jemsettings->showatte == 1) : ?>
            <div id="jem_atte" class="sectiontableheader"><?php echo ($paramShowIconsOrder? '<i class="fa fa-user" aria-hidden="true"></i>&nbsp;' : '');?><?php echo Text::_('COM_JEM_TABLE_ATTENDEES'); ?></div>
        <?php endif; ?>
    </div>
</div>

<ul class="eventlist">
    <?php if ($this->noevents == 1) : ?>
        <li class="jem-event"><?php echo Text::_('COM_JEM_NO_EVENTS'); ?></li>
    <?php else : ?>
        <?php
        // Safari has problems with the "onclick" element in the <li>. It covers the links to location and category etc.
        // This detects the browser and just writes the onclick attribute if the broswer is not Safari.
        $isSafari = false;
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') && !strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome')) {
            $isSafari = true;
        }
        ?>
        <?php
        $this->rows = $this->getRows();
        $showMonthRow = false;
        $previousYearMonth = '';
        $paramShowMonthRow = $this->params->get('showmonthrow', '');
        ?>

        <?php foreach ($this->rows as $row) : ?>
            <?php
            if ($paramShowMonthRow && $row->dates) {
                //get event date
                $year = date('Y', strtotime($row->dates));
                $month = date('F', strtotime($row->dates));
                $YearMonth = Text::_('COM_JEM_'.strtoupper ($month)) . ' ' . $year;

                if (!$previousYearMonth || $previousYearMonth != $YearMonth) {
                    $showMonthRow = $YearMonth;
                }

                //Publish month row
                if ($showMonthRow) { ?>
                    <li class="jem-event jem-row jem-justify-center bg-body-secondary" itemscope="itemscope"><span class="row-month"><?php echo $showMonthRow;?></span></li>
                <?php }
            } ?>
            <?php if (!empty($row->featured)) :   ?>
                <li class="jem-event jem-list-row jem-small-list jem-featured <?php echo $this->params->get('pageclass_sfx') . ' event_id' . $this->escape($row->id); if (!empty($row->locid)) {  echo ' venue_id' . $this->escape($row->locid); } ?>" itemscope="itemscope" itemtype="https://schema.org/Event" <?php if ($this->jemsettings->showdetails == 1 && (!$isSafari)) : echo 'onclick="location.href=\''.Route::_(JemHelperRoute::getEventRoute($row->slug)).'\'"';; endif; ?> >				<?php else : ?>
                <li class="jem-event jem-list-row jem-small-list jem-odd<?php echo ($row->odd +1) . $this->params->get('pageclass_sfx') . ' event_id' . $this->escape($row->id); if (!empty($row->locid)) {  echo ' venue_id' . $this->escape($row->locid); } ?>" itemscope="itemscope" itemtype="https://schema.org/Event" <?php if ($this->jemsettings->showdetails == 1 && (!$isSafari)) : echo 'onclick="location.href=\''.Route::_(JemHelperRoute::getEventRoute($row->slug)).'\'"';; endif; ?> >				<?php endif; ?>
            <div class="jem-event-info-small jem-event-date" title="<?php echo Text::_('COM_JEM_TABLE_DATE').': '.strip_tags(JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $this->jemsettings->showtime)); ?>">
                <?php echo ($showiconsineventdata? '<i class="far fa-clock" aria-hidden="true"></i>':''); ?>
                <?php
                echo JemOutput::formatShortDateTime($row->dates, $row->times,
                    $row->enddates, $row->endtimes, $this->jemsettings->showtime);
                echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times,
                    $row->enddates, $row->endtimes);
                ?>
                <?php if ($this->jemsettings->showtitle == 0) : ?>
                    <?php echo JemOutput::recurrenceicon($row); ?>
                    <?php echo JemOutput::publishstateicon($row); ?>
                    <?php if (!empty($row->featured)) :?>
                        <?php echo ($showiconsineventtitle? '<i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>':''); ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($this->jemsettings->showtitle == 1) : ?>
                <div class="jem-event-info-small jem-event-title">
                    <h4	title="<?php echo Text::_('COM_JEM_TABLE_TITLE').': '.$this->escape($row->title); ?>">


                        <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>"><?php echo $this->escape($row->title); ?></a>
                        <?php echo ($showiconsineventtitle? JemOutput::recurrenceicon($row) : '') . JemOutput::publishstateicon($row); ?>
                        <?php if (!empty($row->featured)) :?>
                            <?php echo ($showiconsineventtitle? '<i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>':''); ?>
                        <?php endif; ?>
                    </h4>
                </div>
            <?php else : ?>
            <?php endif; ?>

            <?php if ($this->jemsettings->showlocate == 1) : ?>
                <?php if (!empty($row->locid)) : ?>
                    <div class="jem-event-info-small jem-event-venue" title="<?php echo Text::_('COM_JEM_TABLE_LOCATION').': '.$this->escape($row->venue); ?>">
                        <?php echo ($showiconsineventdata? '<i class="fa fa-map-marker" aria-hidden="true"></i>':''); ?>
                        <?php if ($this->jemsettings->showlinkvenue == 1) : ?>
                            <?php echo "<a href='".Route::_(JemHelperRoute::getVenueRoute($row->venueslug))."'>".$this->escape($row->venue)."</a>"; ?>
                        <?php else : ?>
                            <?php echo $this->escape($row->venue); ?>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <div class="jem-event-info-small jem-event-venue">
                        <?php echo ($showiconsineventdata? '<i class="fa fa-map-marker" aria-hidden="true"></i>':''); ?> -
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($this->jemsettings->showcity == 1) : ?>
                <?php if (!empty($row->city)) : ?>
                    <div class="jem-event-info-small jem-event-city" title="<?php echo Text::_('COM_JEM_TABLE_CITY').': '.$this->escape($row->city); ?>">
                        <?php echo ($showiconsineventdata? '<i class="fa fa-building" aria-hidden="true"></i>':''); ?>
                        <?php echo $this->escape($row->city); ?>
                    </div>
                <?php else : ?>
                    <div class="jem-event-info-small jem-event-city">
                        <?php echo ($showiconsineventdata? '<i class="fa fa-building" aria-hidden="true"></i>':''); ?> -
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($this->jemsettings->showstate == 1) : ?>
                <?php if (!empty($row->state)) : ?>
                    <div class="jem-event-info-small jem-event-state" title="<?php echo Text::_('COM_JEM_TABLE_STATE').': '.$this->escape($row->state); ?>">
                        <?php echo ($showiconsineventdata? '<i class="fa fa-map" aria-hidden="true"></i>':''); ?>
                        <?php echo $this->escape($row->state); ?>
                    </div>
                <?php else : ?>
                    <div class="jem-event-info-small jem-event-state">
                        <?php echo ($showiconsineventdata? '<i class="fa fa-map" aria-hidden="true"></i>':''); ?> -
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($this->jemsettings->showcat == 1) : ?>
                <div class="jem-event-info-small jem-event-category" title="<?php echo strip_tags(Text::_('COM_JEM_TABLE_CATEGORY').': '.implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist))); ?>">
                    <?php echo ($showiconsineventdata? '<i class="fa fa-tag" aria-hidden="true"></i>':''); ?>
                    <?php echo implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist)); ?>
                </div>
            <?php endif; ?>

            <?php if ($this->jemsettings->showatte == 1) : ?>
                <?php if (!empty($row->regCount)) : ?>
                    <div class="jem-event-info-small jem-event-attendees" title="<?php echo Text::_('COM_JEM_TABLE_ATTENDEES').': '.$this->escape($row->regCount); ?>">
                        <?php echo ($showiconsineventdata? '<i class="fa fa-user" aria-hidden="true"></i>':''); ?>
                        <?php echo $this->escape($row->regCount), " / ", $this->escape($row->maxplaces); ?>
                    </div>
                <?php else : ?>
                    <div class="jem-event-info-small jem-event-attendees">
                        <?php echo ($showiconsineventdata? '<i class="fa fa-user" aria-hidden="true"></i>':''); ?>
                        <?php echo " - / ", $this->escape ($row->maxplaces); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <?php
            if ($paramShowMonthRow) {
                $previousYearMonth = $YearMonth ?? '';
                $showMonthRow = false;
            }
            ?>

            <meta itemprop="name" content="<?php echo $this->escape($row->title); ?>" />
            <meta itemprop="url" content="<?php echo rtrim($uri->base(), '/').Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" />
            <meta itemprop="identifier" content="<?php echo rtrim($uri->base(), '/').Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" />
            <div itemtype="https://schema.org/Place" itemscope itemprop="location" style="display: none;" >
                <?php if (!empty($row->locid)) : ?>
                    <meta itemprop="name" content="<?php echo $this->escape($row->venue); ?>" />
                <?php else : ?>
                    <meta itemprop="name" content="None" />
                <?php endif; ?>
                <?php
                $microadress = '';
                if (!empty($row->city)) {
                    $microadress .= $this->escape($row->city);
                }
                if (!empty($microadress)) {
                    $microadress .= ', ';
                }
                if (!empty($row->state)) {
                    $microadress .= $this->escape($row->state);
                }
                if (empty($microadress)) {
                    $microadress .= '-';
                }
                ?>
                <meta itemprop="address" content="<?php echo $microadress; ?>" />
            </div>
            </li>
        <?php endforeach; ?>
    <?php endif; ?>
</ul>

<div class="jem-row valign-baseline">
    <div style="margin:0; padding: 0;">
        <?php if (jem_common_show_filter($this) && JemHelper::jemStringContains($this->params->get('pageclass_sfx'), 'jem-filterbelow')): ?>
            <div id="jem_filter" class="floattext jem-form jem-row jem-justify-start">
                <div class="jem-row jem-justify-start jem-nowrap">
                    <?php echo $this->lists['filter']; ?>
                    <input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search'];?>" class="inputbox" onchange="document.adminForm.submit();" />
                </div>
                <div class="jem-row jem-justify-start jem-nowrap">
                    <button class="buttonfilter btn" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
                    <button class="buttonfilter btn" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
                </div>
            </div>
        <?php endif; ?>
    </div>


</div>
