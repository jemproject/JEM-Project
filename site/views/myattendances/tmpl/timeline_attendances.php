<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\String\StringHelper;

HTMLHelper::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/helpers/html');

if (!function_exists('jem_myattendances_country_name')) {
    function jem_myattendances_country_name($country)
    {
        $country = trim((string) $country);

        if ($country === '') {
            return '';
        }

        return JemHelperCountries::getCountryName($country) ?: $country;
    }
}

if (!function_exists('jem_myattendances_country_flag')) {
    function jem_myattendances_country_flag($country, $countryName)
    {
        $flagSrc = JemHelperCountries::getIsoFlag((string) $country);

        if (!$flagSrc) {
            return '';
        }

        $alt = htmlspecialchars((string) $countryName, ENT_QUOTES, 'UTF-8');
        $src = htmlspecialchars($flagSrc, ENT_QUOTES, 'UTF-8');

        return '<img src="' . $src . '" alt="' . $alt . '" title="' . $alt . '" class="venue_country_flag jem-myattendances-country-flag" style="width:20px;height:auto;margin-right:6px;vertical-align:middle;" />';
    }
}

$timelineSide = (string) $this->params->get('timeline_side', 'right');

if (!in_array($timelineSide, array('right', 'left', 'alternate'), true)) {
    $timelineSide = 'right';
}

$showAxisDate = (int) $this->params->get('timeline_show_axis_date', 1);
?>

<style>
    .jem-myattendances-timeline-list {
        clear: both;
        display: grid;
        gap: 0;
        margin: 1.5rem 0 1rem;
    }

    .jem-myattendances-timeline-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 2.25rem minmax(0, 1fr);
        min-height: 5.5rem;
        position: relative;
    }

    .jem-myattendances-timeline-row::before {
        background: currentColor;
        bottom: 0;
        content: "";
        left: calc(50% - 1px);
        opacity: .28;
        position: absolute;
        top: 0;
        width: 2px;
    }

    .jem-myattendances-timeline-point {
        align-self: start;
        background: currentColor;
        border: 3px solid #fff;
        border-radius: 50%;
        box-shadow: 0 0 0 1px currentColor;
        grid-column: 2;
        height: .9rem;
        justify-self: center;
        margin-top: .55rem;
        position: relative;
        width: .9rem;
        z-index: 2;
    }

    .jem-myattendances-timeline-time {
        font-weight: 700;
        line-height: 1.25;
        margin: .15rem .85rem 0;
    }

    .jem-myattendances-timeline-time small {
        display: block;
        font-weight: 400;
        opacity: .75;
    }

    .jem-myattendances-timeline-card {
        border: 1px solid currentColor;
        border-radius: 6px;
        margin: 0 .85rem 1.25rem;
        padding: .85rem 1rem;
    }

    .jem-myattendances-timeline-card h3 {
        font-size: 1.1rem;
        line-height: 1.3;
        margin: 0 0 .45rem;
    }

    .jem-myattendances-timeline-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem 1rem;
        margin: .4rem 0 0;
    }

    .jem-myattendances-timeline-status,
    .jem-myattendances-timeline-comment {
        margin-top: .65rem;
    }

    .jem-myattendances-timeline-right .jem-myattendances-timeline-time,
    .jem-myattendances-timeline-alternate .jem-myattendances-timeline-row:nth-child(odd) .jem-myattendances-timeline-time {
        grid-column: 1;
        text-align: right;
    }

    .jem-myattendances-timeline-right .jem-myattendances-timeline-card,
    .jem-myattendances-timeline-alternate .jem-myattendances-timeline-row:nth-child(odd) .jem-myattendances-timeline-card {
        grid-column: 3;
    }

    .jem-myattendances-timeline-left .jem-myattendances-timeline-time,
    .jem-myattendances-timeline-alternate .jem-myattendances-timeline-row:nth-child(even) .jem-myattendances-timeline-time {
        grid-column: 3;
    }

    .jem-myattendances-timeline-left .jem-myattendances-timeline-card,
    .jem-myattendances-timeline-alternate .jem-myattendances-timeline-row:nth-child(even) .jem-myattendances-timeline-card {
        grid-column: 1;
        text-align: right;
    }

    .jem-myattendances-timeline-left .jem-myattendances-timeline-card .jem-myattendances-timeline-meta,
    .jem-myattendances-timeline-alternate .jem-myattendances-timeline-row:nth-child(even) .jem-myattendances-timeline-card .jem-myattendances-timeline-meta {
        justify-content: flex-end;
    }

    @media (max-width: 720px) {
        .jem-myattendances-timeline-row {
            grid-template-columns: 6.5rem 2rem minmax(0, 1fr);
        }

        .jem-myattendances-timeline-row::before {
            left: 7.5rem;
        }

        .jem-myattendances-timeline-left .jem-myattendances-timeline-time,
        .jem-myattendances-timeline-right .jem-myattendances-timeline-time,
        .jem-myattendances-timeline-alternate .jem-myattendances-timeline-row .jem-myattendances-timeline-time {
            grid-column: 1;
            margin-left: 0;
            text-align: right;
        }

        .jem-myattendances-timeline-left .jem-myattendances-timeline-card,
        .jem-myattendances-timeline-right .jem-myattendances-timeline-card,
        .jem-myattendances-timeline-alternate .jem-myattendances-timeline-row .jem-myattendances-timeline-card {
            grid-column: 3;
            margin-right: 0;
            text-align: left;
        }

        .jem-myattendances-timeline-left .jem-myattendances-timeline-card .jem-myattendances-timeline-meta,
        .jem-myattendances-timeline-alternate .jem-myattendances-timeline-row:nth-child(even) .jem-myattendances-timeline-card .jem-myattendances-timeline-meta {
            justify-content: flex-start;
        }
    }
</style>

<script>
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

    <?php if ($this->settings->get('global_show_filter', 1) || $this->settings->get('global_display', 1)) : ?>
    <div id="jem_filter" class="floattext">
        <?php if ($this->settings->get('global_show_filter', 1)) : ?>
        <div class="jem_fleft">
            <label for="filter"><?php echo Text::_('COM_JEM_FILTER'); ?></label>
            <?php echo $this->lists['filter'] . '&nbsp;'; ?>
            <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox form-control" onchange="document.adminForm.submit();" />
            <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            <button class="btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
        </div>
        <?php endif; ?>

        <?php if ($this->settings->get('global_display', 1)) : ?>
        <div class="jem_fright">
            <label for="limit"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>
            <?php echo $this->attending_pagination->getLimitBox(); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($this->attending)) : ?>
        <p class="no_events"><?php echo Text::_('COM_JEM_NO_EVENTS'); ?></p>
    <?php else : ?>
        <div class="jem-myattendances-timeline-list jem-myattendances-timeline-<?php echo $this->escape($timelineSide); ?>">
            <?php foreach ($this->attending as $row) : ?>
                <?php
                $dateText = HTMLHelper::_('date', $row->dates, Text::_('DATE_FORMAT_LC3'));
                $timeText = '';

                if ($this->jemsettings->showtime && !empty($row->times)) {
                    $timeText = HTMLHelper::_('date', $row->dates . ' ' . $row->times, Text::_('TIME_FORMAT_LC4'));
                }

                $countryName = jem_myattendances_country_name($row->country ?? '');
                $status = (int) $row->status;

                if ($status === 1 && $row->waiting == 1) {
                    $status = 2;
                }

                $comment = '';
                if (!empty($this->jemsettings->regallowcomments) && !empty($row->comment)) {
                    $len = $this->print ? 256 : 48;
                    $comment = (StringHelper::strlen($row->comment) > $len) ? (StringHelper::substr($row->comment, 0, $len - 2) . '&hellip;') : $row->comment;
                }
                ?>
                <article class="jem-myattendances-timeline-row<?php echo !empty($row->featured) ? ' featured' : ''; ?> event_id<?php echo $this->escape($row->id); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
                    <div class="jem-myattendances-timeline-time">
                        <?php if ($showAxisDate) : ?>
                            <span><?php echo $this->escape($dateText); ?></span>
                        <?php endif; ?>
                        <?php if ($timeText !== '') : ?>
                            <small><?php echo $this->escape($timeText); ?></small>
                        <?php endif; ?>
                    </div>
                    <span class="jem-myattendances-timeline-point" aria-hidden="true"></span>
                    <div class="jem-myattendances-timeline-card">
                        <h3>
                            <?php if ($this->jemsettings->showdetails == 1) : ?>
                                <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" itemprop="url">
                                    <span itemprop="name"><?php echo $this->escape($row->title); ?></span>
                                </a>
                            <?php else : ?>
                                <span itemprop="name"><?php echo $this->escape($row->title); ?></span>
                            <?php endif; ?>
                            <?php echo JemOutput::recurrenceicon($row) . JemOutput::publishstateicon($row); ?>
                        </h3>

                        <div class="jem-myattendances-timeline-meta">
                            <?php if ($this->jemsettings->showlocate == 1) : ?>
                                <span>
                                    <?php if (!empty($row->venue)) : ?>
                                        <?php if (($this->jemsettings->showlinkvenue == 1) && !empty($row->venueslug)) : ?>
                                            <a href="<?php echo Route::_(JemHelperRoute::getVenueRoute($row->venueslug)); ?>"><?php echo $this->escape($row->venue); ?></a>
                                        <?php else : ?>
                                            <?php echo $this->escape($row->venue); ?>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($this->jemsettings->showcity == 1 && !empty($row->city)) : ?>
                                <span><?php echo $this->escape($row->city); ?></span>
                            <?php endif; ?>

                            <?php if ($this->jemsettings->showstate == 1 && !empty($row->state)) : ?>
                                <span><?php echo $this->escape($row->state); ?></span>
                            <?php endif; ?>

                            <?php if ($this->jemsettings->showstate == 1 && $countryName !== '') : ?>
                                <span><?php echo jem_myattendances_country_flag($row->country ?? '', $countryName) . $this->escape($countryName); ?></span>
                            <?php endif; ?>

                            <?php if ($this->jemsettings->showcat == 1) : ?>
                                <span><?php echo implode(', ', JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist)); ?></span>
                            <?php endif; ?>

                            <span><?php echo Text::_('COM_JEM_TABLE_PLACES'); ?>: <?php echo !empty($row->places) ? $this->escape($row->places) : '-'; ?></span>
                        </div>

                        <div class="jem-myattendances-timeline-status">
                            <?php echo jemhtml::toggleAttendanceStatus($row->id, $status, false, $this->print); ?>
                        </div>

                        <?php if ($comment !== '') : ?>
                            <div class="jem-myattendances-timeline-comment">
                                <?php echo $this->print ? $comment : HTMLHelper::_('tooltip', $row->comment, null, null, $comment, null, null); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
    <input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
    <input type="hidden" name="boxchecked" value="0" />
    <input type="hidden" name="task" value="<?php echo $this->task; ?>" />
    <input type="hidden" name="option" value="com_jem" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<div class="pagination">
    <?php echo $this->attending_pagination->getPagesLinks(); ?>
</div>
