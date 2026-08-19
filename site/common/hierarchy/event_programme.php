<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$event = $this->item;
$showVenueConfiguration = $this->showVenueConfiguration ?? true;
$hierarchy = $this->eventHierarchy ?? array(
    'parent_event_id' => (int) ($event->parent_event_id ?? 0),
    'parent_event_title' => (string) ($event->parent_event_title ?? ''),
    'parent_event_alias' => (string) ($event->parent_event_alias ?? ''),
    'child_events' => (array) ($event->child_events ?? array()),
);

if (!empty($hierarchy['parent_event_id']) && !empty($hierarchy['parent_event_title'])) :
    $parentSlug = (int) $hierarchy['parent_event_id'] . (!empty($hierarchy['parent_event_alias']) ? ':' . $hierarchy['parent_event_alias'] : '');
?>
    <nav class="jem-event-parent" aria-label="<?php echo htmlspecialchars(Text::_('COM_JEM_PARENT_EVENT'), ENT_QUOTES, 'UTF-8'); ?>">
        <strong><?php echo Text::_('COM_JEM_PARENT_EVENT'); ?>:</strong>
        <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($parentSlug)); ?>">
            <?php echo htmlspecialchars($hierarchy['parent_event_title'], ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </nav>
<?php endif; ?>

<?php if (!empty($hierarchy['child_events'])) : ?>
    <section class="jem-event-programme" aria-labelledby="jem-event-programme-title">
        <h2 id="jem-event-programme-title" class="jem"><?php echo Text::_('COM_JEM_EVENT_PROGRAMME'); ?></h2>
        <?php $currentDate = null; ?>
        <?php foreach ($hierarchy['child_events'] as $programmeItem) : ?>
            <?php if ($currentDate !== $programmeItem->dates) : ?>
                <?php $currentDate = $programmeItem->dates; ?>
                <h3 class="jem-programme-day">
                    <?php echo $currentDate ? HTMLHelper::_('date', $currentDate, Text::_('DATE_FORMAT_LC1')) : Text::_('COM_JEM_OPEN_DATE'); ?>
                </h3>
            <?php endif; ?>
            <article class="jem-programme-item jem-programme-item--<?php echo htmlspecialchars($programmeItem->event_status ?: 'scheduled', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="jem-programme-time">
                    <?php echo $programmeItem->times ? substr((string) $programmeItem->times, 0, 5) : ''; ?>
                    <?php if ($programmeItem->endtimes) : ?>&ndash;<?php echo substr((string) $programmeItem->endtimes, 0, 5); ?><?php endif; ?>
                </div>
                <div class="jem-programme-content">
                    <h4>
                        <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($programmeItem->slug)); ?>">
                            <?php echo htmlspecialchars($programmeItem->title, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </h4>
                    <?php if (!empty($programmeItem->venue)) : ?>
                        <div class="jem-programme-venue">
                            <a href="<?php echo Route::_(JemHelperRoute::getVenueRoute($programmeItem->venueslug)); ?>">
                                <?php echo htmlspecialchars($programmeItem->venue, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php $programmeLocation = $showVenueConfiguration ? JemVenueSnapshot::summary($programmeItem) : ''; ?>
                    <?php if ($programmeLocation !== '') : ?>
                        <div class="jem-programme-venue-configuration text-muted">
                            <?php echo htmlspecialchars($programmeLocation, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
