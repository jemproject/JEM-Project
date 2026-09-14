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
use Joomla\CMS\Factory;
?>

<script>
    function tableOrdering(order, dir, view) {
        var form = document.getElementById("adminForm");

        form.filter_order.value = order;
        form.filter_order_Dir.value = dir;
        form.submit(view);
    }
</script>

<?php
$imagewidth = 'inherit';
if ($this->jemsettings->imagewidth != 0) {
    $imagewidth = $this->jemsettings->imagewidth / 2;
    $imagewidth = $imagewidth.'px';
}
$imagewidthstring = 'jem-imagewidth';
if (JemHelper::jemStringContains($this->params->get('pageclass_sfx'), $imagewidthstring)) {
    $pageclass_sfx = $this->params->get('pageclass_sfx');
    $imagewidthpos = strpos($pageclass_sfx, $imagewidthstring);
    $spacepos = strpos($pageclass_sfx, ' ', $imagewidthpos);
    if ($spacepos === false) {
        $spacepos = strlen($pageclass_sfx);
    }
    $startpos = $imagewidthpos + strlen($imagewidthstring);
    $endpos = $spacepos - $startpos;
    $imagewidth = substr($pageclass_sfx, $startpos, $endpos);
}
$imageheight = 'auto';
$imageheigthstring = 'jem-imageheight';
if (JemHelper::jemStringContains($this->params->get('pageclass_sfx'), $imageheigthstring)) {
    $pageclass_sfx = $this->params->get('pageclass_sfx');
    $imageheightpos = strpos($pageclass_sfx, $imageheigthstring);
    $spacepos = strpos($pageclass_sfx, ' ', $imageheightpos);
    if ($spacepos === false) {
        $spacepos = strlen($pageclass_sfx);
    }
    $startpos = $imageheightpos + strlen($imageheigthstring);
    $endpos = $spacepos - $startpos;
    $imageheight = substr($pageclass_sfx, $startpos, $endpos);
}

$document = Factory::getApplication()->getDocument();
$css = '
    #jem .jem-list-img {
        width: ' . $imagewidth . ';
    }

    #jem .jem-list-img img {
        width: ' . $imagewidth . ';
        height: ' . $imageheight . ';
    }

    @media not print {
        @media only all and (max-width: 47.938rem) {
            #jem .jem-list-img {
                width: 100%;
            }

            #jem .jem-list-img img {
                width: ' . $imagewidth . ';
                height: ' . $imageheight . ';
            }
        }
    }';
$document->addStyleDeclaration($css);
$wa = $document->getWebAssetManager();
$wa->registerAndUseScript(
    'com_jem.load-more',
    'media/com_jem/js/load-more.js',
    array('jquery'),
    array('defer' => true)
);
function jem_common_show_filter(&$obj)
{
    if ($obj->settings->get('global_show_filter', 1) && !JemHelper::jemStringContains($obj->params->get('pageclass_sfx'), 'jem-hidefilter')) {
        return true;
    }
    if (JemHelper::jemStringContains($obj->params->get('pageclass_sfx'), 'jem-showfilter')) {
        return true;
    }
    return false;
}

if (jem_common_show_filter($this) && !JemHelper::jemStringContains($this->params->get('pageclass_sfx'), 'jem-filterbelow')): ?>
    <div id="jem_filter" class="floattext jem-form jem-row jem-justify-start jem-events-filter">
        <div class="jem-row jem-justify-start jem-nowrap jem-events-filter-search">
            <?php echo $this->lists['filter']; ?>
            <input type="text" name="filter_search" id="filter_search" class="inputbox form-control" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8');?>" onchange="document.adminForm.submit();" />
        </div>
        <div class="jem-row jem-justify-start jem-nowrap jem-events-filter-month">
            <label for="filter_month"><?php echo Text::_('COM_JEM_SEARCH_MONTH'); ?></label>
            <input type="month" name="filter_month" id="filter_month" pattern="[0-9]{4}-[0-9]{2}" title="<?php echo Text::_('COM_JEM_SEARCH_YYYY-MM_FORMAT'); ?>" class="inputbox form-control" placeholder="<?php echo Text::_('COM_JEM_SEARCH_YYYY-MM'); ?>" size="7" value="<?php echo $this->lists['month'] ?? '';?>">
        </div>
        <div class="jem-row jem-justify-start jem-nowrap jem-events-filter-actions">
            <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            <button class="btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';document.getElementById('filter_month').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
        </div>
        <?php if ($this->settings->get('global_display', 1)) : ?>
            <div class="jem-limit-smallest jem-events-filter-limit">
                <label for="limit"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>
                <?php echo $this->pagination->getLimitBox(); ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php $paramShowIconsOrder = $this->params->get('showiconsinorder',1); ?>
<?php $showiconsineventtitle = $this->params->get('showiconsineventtitle',1); ?>
<?php $showiconsineventdata = $this->params->get('showiconsineventdata',1); ?>
<?php $showAvailabilityText = (bool) $this->params->get('event_show_availability',0); ?>

<div class="jem-misc jem-row">
    <div class="jem-sort jem-row jem-justify-start jem-nowrap">
        <?php echo ($paramShowIconsOrder? '<i class="fa fa-sort fa-lg jem-sort-icon" aria-hidden="true"></i>' : '');?>
        <div class="jem-row jem-justify-start jem-sort-parts">
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
</div>

<ul class="eventlist">
    <?php if ($this->noevents == 1) : ?>
        <li class="jem-event"><?php echo Text::_('COM_JEM_NO_EVENTS'); ?></li>
    <?php else : ?>
        <?php
        // Safari has problems with the "onclick" element in the <li>. It covers the links to location and category etc.
        // This detects the browser and just writes the onclick attribute if the broswer is not Safari.
        $userAgent = Factory::getApplication()->input->server->getString('HTTP_USER_AGENT', '');
        $isSafari  = (strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false);
        ?>
        <?php
        $this->rows = $this->getRows();
        $showMonthRow = false;
        $previousYearMonth = '';
        $paramShowMonthRow = $this->params->get('showmonthrow', '');
        ?>

        <?php foreach ($this->rows as $row) : ?>
            <?php
            if (empty($row->user_has_access_category)) {
                continue;
            }

            if ($paramShowMonthRow && $row->dates) {
                $year = date('Y', strtotime($row->dates));
                $month = date('F', strtotime($row->dates));
                $yearMonth = Text::_('COM_JEM_' . strtoupper($month)) . ' ' . $year;

                if ($previousYearMonth === '' || $previousYearMonth !== $yearMonth) {
                    $showMonthRow = $yearMonth;
                }

                if ($showMonthRow) {
                    ?>
                    <li class="jem-event jem-row jem-justify-center bg-body-secondary" itemscope="itemscope"><span class="row-month"><?php echo $this->escape($showMonthRow); ?></span></li>
                    <?php
                }
            }

            $displayData = array(
                'row' => $row,
                'params' => $this->params,
                'jemsettings' => $this->jemsettings,
                'settings' => $this->settings,
                'isSafari' => $isSafari,
                'showIconsInEventTitle' => $showiconsineventtitle,
                'showIconsInEventData' => $showiconsineventdata,
                'showAvailabilityText' => $showAvailabilityText,
                'structuredData' => true,
                'imagePathAware' => false,
            );
            require __DIR__ . '/default_jem_eventslist_item.php';

            if ($paramShowMonthRow) {
                $previousYearMonth = $yearMonth ?? '';
                $showMonthRow = false;
            }
            ?>
        <?php endforeach; ?>
    <?php endif; ?>
</ul>
<?php if (jem_common_show_filter($this) && JemHelper::jemStringContains($this->params->get('pageclass_sfx'), 'jem-filterbelow')) : ?>
    <div id="jem_filter" class="floattext jem-form jem-row jem-justify-start jem-events-filter">
        <div class="jem-row jem-justify-start jem-nowrap jem-events-filter-search">
            <?php echo $this->lists['filter']; ?>
            <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8');?>" class="inputbox" onchange="document.adminForm.submit();" />
        </div>
        <div class="jem-row jem-justify-start jem-nowrap jem-events-filter-actions">
            <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            <button class="btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';document.getElementById('filter_month').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
        </div>
    </div>
<?php endif;

// Add Load More Button
if (!$this->noevents && (int) $this->params->get('show_more_button', 0) === 1
    && (int) $this->pagination->limit > 0 && count($this->rows) >= $this->pagination->limit) {
    $currentOffset = max(0, (int) $this->pagination->limitstart);
    $pageLimit = max(1, (int) $this->pagination->limit);
    $requestLimit = min(JemLoadMoreRequestPolicy::MAX_LIMIT, $pageLimit);
    $nextOffset = $currentOffset + $pageLimit;
    $totalItems = max(0, (int) $this->pagination->total);
    $hasMore = $nextOffset < $totalItems && $nextOffset <= JemLoadMoreRequestPolicy::MAX_OFFSET;
    $itemId = Factory::getApplication()->input->getInt('Itemid', 0);
    $endpoint = 'index.php?option=com_jem&view=eventslist&task=loadmore&format=json'
        . ($itemId > 0 ? '&Itemid=' . $itemId : '');
    $endpoint = Route::_($endpoint, false);
    $loadMoreContext = $this->task === 'archive' ? 'archive' : '';
    
    if ($hasMore) :
?>
<div class="jem-load-more-container text-center mt-3">
    <button 
        id="jem-load-more-btn" 
        class="btn btn-primary"
        data-next-offset="<?php echo $nextOffset; ?>"
        data-limit="<?php echo $requestLimit; ?>"
        data-endpoint="<?php echo htmlspecialchars($endpoint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
        data-context="<?php echo htmlspecialchars($loadMoreContext, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
        data-text-loading="<?php echo Text::_('COM_JEM_LOADING'); ?>"
        data-text-loadmore="<?php echo Text::_('COM_JEM_LOAD_MORE'); ?>"
        type="button"
    >
        <?php echo Text::_('COM_JEM_LOAD_MORE'); ?>
    </button>
</div>
<?php 
    endif;
}
?>
