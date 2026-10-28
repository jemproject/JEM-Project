<?php
/**
 * @package    JEM
 * @copyright  (C) 2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

require_once JPATH_SITE . '/components/com_jem/classes/dayschedule.class.php';
require_once JPATH_SITE . '/components/com_jem/classes/venueboard.class.php';

$currentDate = new DateTimeImmutable($this->day);
$rows = array();
foreach ($this->rows ?: array() as $row) {
    foreach (JemDaySchedule::project($row, $this->day, JemHelper::getEventTimeZoneName($row)) as $session) {
        $rows[] = $session;
    }
}
$mode = $this->venueBoard['mode'];
$columns = JemVenueBoard::group($rows, $this->venueBoard['locations'], $this->venueBoardAssignments, $mode);
$input = Factory::getApplication()->input;
$extraLink = '';
foreach (array('Itemid', 'catid', 'locid') as $key) {
    $value = $input->getInt($key, 0);
    if ($value > 0) {
        $extraLink .= '&' . $key . '=' . $value;
    }
}
$dayLink = static function ($date) use ($extraLink) {
    return Route::_('index.php?option=com_jem&view=day&layout=venueboard&id=' . $date->format('Ymd') . $extraLink);
};
$todayLink = $dayLink(new DateTimeImmutable(JemHelper::getJoomlaDate()));
$specialDayBadges = JemHelper::renderCalendarSpecialDayBadges($this->day);
?>
<div id="jem" class="jem_day jem_day_venueboard<?php echo $this->pageclass_sfx; ?>">
    <div class="buttons">
        <?php echo JemOutput::createButtonBar($this->getName(), $this->permissions, array('task' => $this->task, 'print_link' => $this->print_link, 'today_link' => $todayLink)); ?>
    </div>

    <?php if ($this->params->get('show_page_heading', 1)) : ?>
        <h1 class="componentheading"><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
    <?php endif; ?>

    <nav class="jem-venueboard-navigation" aria-label="<?php echo Text::_('COM_JEM_TIMETABLE_DAY_NAVIGATION'); ?>">
        <a href="<?php echo $this->escape($dayLink($currentDate->modify('-1 day'))); ?>" rel="prev" aria-label="<?php echo Text::_('COM_JEM_TIMETABLE_PREVIOUS_DAY'); ?>">&larr;</a>
        <strong><?php echo $this->escape($this->daydate); ?></strong>
        <?php echo $specialDayBadges; ?>
        <a href="<?php echo $this->escape($todayLink); ?>"><?php echo Text::_('COM_JEM_TIMETABLE_TODAY'); ?></a>
        <a href="<?php echo $this->escape($dayLink($currentDate->modify('+1 day'))); ?>" rel="next" aria-label="<?php echo Text::_('COM_JEM_TIMETABLE_NEXT_DAY'); ?>">&rarr;</a>
    </nav>

    <?php echo JemHelper::renderCalendarContactFilterContext($this->params); ?>

    <?php if ($this->params->get('showintrotext')) : ?>
        <div class="description no_space floattext"><?php echo $this->params->get('introtext'); ?></div>
    <?php endif; ?>

    <?php if (!$columns) : ?>
        <p class="jem-venueboard-empty"><?php echo Text::_('COM_JEM_NO_EVENTS'); ?></p>
    <?php else : ?>
        <div class="jem-venueboard-scroll" role="region" aria-label="<?php echo Text::_('COM_JEM_DAY_VIEW_VENUEBOARD_TITLE'); ?>" tabindex="0">
            <div class="jem-venueboard-grid" style="--jem-board-columns: <?php echo count($columns); ?>;">
                <?php foreach ($columns as $column) : ?>
                    <?php
                    $location = $column['location'];
                    $name = $location ? (string) ($location->name ?? '') : Text::_($mode === 'spaces' ? 'COM_JEM_VENUEBOARD_OTHER_EVENTS' : 'COM_JEM_TIMETABLE_NO_VENUE');
                    $venueUrl = $mode === 'venues' && $location
                        ? Route::_(JemHelperRoute::getVenueRoute((int) $location->id . (!empty($location->alias) ? ':' . $location->alias : '')))
                        : '';
                    ?>
                    <section class="jem-venueboard-column">
                        <h2 class="jem-venueboard-location">
                            <?php if ($venueUrl) : ?>
                                <a href="<?php echo $this->escape($venueUrl); ?>"><?php echo $this->escape($name); ?></a>
                            <?php else : ?>
                                <?php echo $this->escape($name); ?>
                            <?php endif; ?>
                        </h2>
                        <div class="jem-venueboard-events">
                            <?php if (!$column['events']) : ?>
                                <p class="jem-venueboard-column-empty"><?php echo Text::_('COM_JEM_VENUEBOARD_NO_EVENTS'); ?></p>
                            <?php endif; ?>
                            <?php foreach ($column['events'] as $event) : ?>
                                <?php
                                $eventUrl = Route::_(JemHelperRoute::getEventRoute($event->slug));
                                $time = $event->day_schedule['all_day'] ? Text::_('COM_JEM_TIMETABLE_ALL_DAY') : $event->day_schedule['label'];
                                $intro = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string) ($event->introtext ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
                                if (mb_strlen($intro) > 140) {
                                    $intro = mb_substr($intro, 0, 137) . '…';
                                }
                                ?>
                                <article class="jem-venueboard-event">
                                    <div class="jem-venueboard-time"><?php echo $this->escape($time); ?></div>
                                    <h3><a href="<?php echo $this->escape($eventUrl); ?>"><?php echo $this->escape($event->title); ?></a></h3>
                                    <?php foreach ((array) ($event->categories ?? array()) as $category) : ?>
                                        <span class="jem-venueboard-category"><?php echo $this->escape($category->catname ?? ''); ?></span>
                                    <?php endforeach; ?>
                                    <?php if ($intro !== '') : ?>
                                        <p class="jem-venueboard-intro"><?php echo $this->escape($intro); ?></p>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($this->params->get('showfootertext')) : ?>
        <div class="description no_space floattext"><?php echo $this->params->get('footertext'); ?></div>
    <?php endif; ?>
</div>
