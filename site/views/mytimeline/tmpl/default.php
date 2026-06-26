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
use Joomla\CMS\Uri\Uri;
use Joomla\String\StringHelper;

HTMLHelper::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/helpers/html');

$timelineSide = (string) $this->params->get('timeline_side', 'right');
if (!in_array($timelineSide, array('right', 'left', 'alternate'), true)) {
    $timelineSide = 'right';
}

$showAxisDate = (int) $this->params->get('timeline_show_axis_date', 1);
$showRegistered = (int) $this->params->get('mytimeline_include_registered', 0);
$timelinePurposes = $this->params->get('mytimeline_purposes', array('personal_calendar', 'planning'));
if (is_string($timelinePurposes)) {
    $timelinePurposes = array_filter(array_map('trim', explode(',', $timelinePurposes)));
}
if (!is_array($timelinePurposes) || empty($timelinePurposes)) {
    $timelinePurposes = array('personal_calendar', 'planning');
}
$showAllPurposes = in_array('all', $timelinePurposes, true);
$showDiaryNotes = $showAllPurposes || in_array('event_diary', $timelinePurposes, true);
?>

<style>
    .jem-mytimeline-list {
        clear: both;
        display: grid;
        gap: 0;
        margin: 1.5rem 0 1rem;
    }

    .jem-mytimeline-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 2.25rem minmax(0, 1fr);
        min-height: 5.5rem;
        position: relative;
    }

    .jem-mytimeline-row::before {
        background: currentColor;
        bottom: 0;
        content: "";
        left: calc(50% - 1px);
        opacity: .28;
        position: absolute;
        top: 0;
        width: 2px;
    }

    .jem-mytimeline-point {
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

    .jem-mytimeline-time {
        font-weight: 700;
        line-height: 1.25;
        margin: .15rem .85rem 0;
    }

    .jem-mytimeline-time small {
        display: block;
        font-weight: 400;
        opacity: .75;
    }

    .jem-mytimeline-card {
        border: 1px solid currentColor;
        border-radius: 6px;
        margin: 0 .85rem 1.25rem;
        padding: .85rem 1rem;
    }

    .jem-mytimeline-card h3 {
        font-size: 1.1rem;
        line-height: 1.3;
        margin: 0 0 .45rem;
    }

    .jem-mytimeline-meta,
    .jem-mytimeline-badges {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem 1rem;
        margin: .4rem 0 0;
    }

    .jem-mytimeline-badge {
        border: 1px solid currentColor;
        border-radius: 999px;
        display: inline-flex;
        font-size: .85rem;
        line-height: 1.2;
        padding: .18rem .55rem;
    }

    .jem-mytimeline-diary {
        margin: .65rem 0 0;
    }

    .jem-mytimeline-right .jem-mytimeline-time,
    .jem-mytimeline-alternate .jem-mytimeline-row:nth-child(odd) .jem-mytimeline-time {
        grid-column: 1;
        text-align: right;
    }

    .jem-mytimeline-right .jem-mytimeline-card,
    .jem-mytimeline-alternate .jem-mytimeline-row:nth-child(odd) .jem-mytimeline-card {
        grid-column: 3;
    }

    .jem-mytimeline-left .jem-mytimeline-time,
    .jem-mytimeline-alternate .jem-mytimeline-row:nth-child(even) .jem-mytimeline-time {
        grid-column: 3;
    }

    .jem-mytimeline-left .jem-mytimeline-card,
    .jem-mytimeline-alternate .jem-mytimeline-row:nth-child(even) .jem-mytimeline-card {
        grid-column: 1;
        text-align: right;
    }

    .jem-mytimeline-left .jem-mytimeline-card .jem-mytimeline-meta,
    .jem-mytimeline-left .jem-mytimeline-card .jem-mytimeline-badges,
    .jem-mytimeline-alternate .jem-mytimeline-row:nth-child(even) .jem-mytimeline-card .jem-mytimeline-meta,
    .jem-mytimeline-alternate .jem-mytimeline-row:nth-child(even) .jem-mytimeline-card .jem-mytimeline-badges {
        justify-content: flex-end;
    }

    @media (max-width: 720px) {
        .jem-mytimeline-row {
            grid-template-columns: 6.5rem 2rem minmax(0, 1fr);
        }

        .jem-mytimeline-row::before {
            left: 7.5rem;
        }

        .jem-mytimeline-left .jem-mytimeline-time,
        .jem-mytimeline-right .jem-mytimeline-time,
        .jem-mytimeline-alternate .jem-mytimeline-row .jem-mytimeline-time {
            grid-column: 1;
            margin-left: 0;
            text-align: right;
        }

        .jem-mytimeline-left .jem-mytimeline-card,
        .jem-mytimeline-right .jem-mytimeline-card,
        .jem-mytimeline-alternate .jem-mytimeline-row .jem-mytimeline-card {
            grid-column: 3;
            margin-right: 0;
            text-align: left;
        }

        .jem-mytimeline-left .jem-mytimeline-card .jem-mytimeline-meta,
        .jem-mytimeline-left .jem-mytimeline-card .jem-mytimeline-badges,
        .jem-mytimeline-alternate .jem-mytimeline-row:nth-child(even) .jem-mytimeline-card .jem-mytimeline-meta,
        .jem-mytimeline-alternate .jem-mytimeline-row:nth-child(even) .jem-mytimeline-card .jem-mytimeline-badges {
            justify-content: flex-start;
        }
    }
</style>

<div id="jem" class="jem_mytimeline jem-mytimeline-<?php echo $this->escape($timelineSide); ?><?php echo $this->pageclass_sfx; ?>">
    <?php if ($this->params->get('showintrotext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('introtext'); ?>
        </div>
    <?php endif; ?>

    <?php if ($this->needLoginFirst) : ?>
        <?php
        $uri = Uri::getInstance();
        $returnUrl = $uri->toString();
        $urlLogin = Route::_('index.php?option=com_users&view=login&return=' . base64_encode($returnUrl), false);
        ?>
        <div class="alert alert-warning">
            <p><?php echo Text::_('COM_JEM_NEED_LOGGED_IN'); ?></p>
            <a class="btn btn-warning" href="<?php echo $this->escape($urlLogin); ?>">
                <?php echo Text::_('COM_JEM_LOGIN_TO_ACCESS'); ?>
            </a>
        </div>
    <?php else : ?>
        <div class="buttons">
            <?php
            $btn_params = array('task' => $this->task, 'print_link' => $this->print_link, 'pdf_link' => $this->pdf_link, 'archive_link' => $this->archive_link);
            echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
            ?>
        </div>

        <?php if ($this->params->get('show_page_heading', 1)) : ?>
            <h1 class="componentheading">
                <?php echo $this->escape($this->params->get('page_heading')); ?>
            </h1>
        <?php endif; ?>

        <?php if (empty($this->items)) : ?>
            <p class="no_events"><?php echo Text::_('COM_JEM_NO_EVENTS'); ?></p>
        <?php else : ?>
            <div class="jem-mytimeline-list jem-mytimeline-<?php echo $this->escape($timelineSide); ?>">
                <?php foreach ($this->items as $row) : ?>
                    <?php
                    $hasDate  = JemHelper::isValidDate($row->dates);
                    $dateText = $hasDate ? HTMLHelper::_('date', $row->dates, Text::_('DATE_FORMAT_LC3')) : Text::_('COM_JEM_OPEN_DATE');
                    $timeText = '';
                    if ($hasDate && $this->jemsettings->showtime && !empty($row->times)) {
                        $startTime = JemOutput::formattime($row->times);
                        $endTime   = !empty($row->endtimes) ? JemOutput::formattime($row->endtimes) : '';
                        $timeText  = $startTime . ($endTime !== '' ? ' - ' . $endTime : '');
                    }
                    $endDate = !empty($row->enddates) ? $row->enddates : $row->dates;
                    $isPast = !empty($endDate) && strtotime($endDate . ' 23:59:59') < time();
                    $purposeLabels = array();

                    if ($showAllPurposes) {
                        $purposeLabels[] = Text::_('COM_JEM_MY_TIMELINE_PURPOSE_ALL');
                    } else {
                        if (!$isPast && in_array('personal_calendar', $timelinePurposes, true)) {
                            $purposeLabels[] = Text::_('COM_JEM_MY_TIMELINE_PURPOSE_PERSONAL_CALENDAR');
                        }
                        if ($isPast && in_array('activity_history', $timelinePurposes, true)) {
                            $purposeLabels[] = Text::_('COM_JEM_MY_TIMELINE_PURPOSE_ACTIVITY_HISTORY');
                        }
                        if (!$isPast && in_array('planning', $timelinePurposes, true)) {
                            $purposeLabels[] = Text::_('COM_JEM_MY_TIMELINE_PURPOSE_PLANNING');
                        }
                        if ($isPast && in_array('event_diary', $timelinePurposes, true)) {
                            $purposeLabels[] = Text::_('COM_JEM_MY_TIMELINE_PURPOSE_EVENT_DIARY');
                        }
                    }

                    $diaryText = '';
                    if ($showDiaryNotes && !empty($row->introtext)) {
                        $plainIntro = trim(strip_tags((string) $row->introtext));
                        $diaryText = StringHelper::strlen($plainIntro) > 180 ? StringHelper::substr($plainIntro, 0, 178) . '&hellip;' : $plainIntro;
                    }
                    ?>
                    <article class="jem-mytimeline-row<?php echo !empty($row->featured) ? ' featured' : ''; ?> event_id<?php echo $this->escape($row->id); ?>" itemscope="itemscope" itemtype="https://schema.org/Event">
                        <div class="jem-mytimeline-time">
                            <?php if ($showAxisDate) : ?>
                                <span><?php echo $this->escape($dateText); ?></span>
                            <?php endif; ?>
                            <?php if ($timeText !== '') : ?>
                                <small><?php echo $this->escape($timeText); ?></small>
                            <?php endif; ?>
                        </div>
                        <span class="jem-mytimeline-point" aria-hidden="true"></span>
                        <div class="jem-mytimeline-card">
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

                            <div class="jem-mytimeline-badges">
                                <?php if (!empty($row->is_owner)) : ?>
                                    <span class="jem-mytimeline-badge"><?php echo Text::_('COM_JEM_MY_TIMELINE_CREATED_BY_ME'); ?></span>
                                <?php endif; ?>
                                <?php if ($showRegistered && !empty($row->is_registered) && empty($row->is_owner)) : ?>
                                    <span class="jem-mytimeline-badge"><?php echo Text::_('COM_JEM_MY_TIMELINE_REGISTERED'); ?></span>
                                <?php endif; ?>
                                <?php foreach ($purposeLabels as $purposeLabel) : ?>
                                    <span class="jem-mytimeline-badge"><?php echo $this->escape($purposeLabel); ?></span>
                                <?php endforeach; ?>
                            </div>

                            <div class="jem-mytimeline-meta">
                                <?php if ($this->jemsettings->showlocate == 1 && !empty($row->venue)) : ?>
                                    <span>
                                        <?php if (($this->jemsettings->showlinkvenue == 1) && !empty($row->venueslug)) : ?>
                                            <a href="<?php echo Route::_(JemHelperRoute::getVenueRoute($row->venueslug)); ?>"><?php echo $this->escape($row->venue); ?></a>
                                        <?php else : ?>
                                            <?php echo $this->escape($row->venue); ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($this->jemsettings->showcity == 1 && !empty($row->city)) : ?>
                                    <span><?php echo $this->escape($row->city); ?></span>
                                <?php endif; ?>

                                <?php if ($this->jemsettings->showstate == 1 && !empty($row->state)) : ?>
                                    <span><?php echo $this->escape($row->state); ?></span>
                                <?php endif; ?>

                                <?php if ($this->jemsettings->showcat == 1) : ?>
                                    <span><?php echo implode(', ', JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist)); ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($diaryText !== '') : ?>
                                <p class="jem-mytimeline-diary"><?php echo $this->escape($diaryText); ?></p>
                            <?php endif; ?>

                            <?php echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes); ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

            <?php if ($this->params->get('showfootertext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('footertext'); ?>
        </div>
    <?php endif; ?>
    <div class="copyright">
            <?php echo JemOutput::footer(); ?>
        </div>
    <?php endif; ?>
</div>
