<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$row = $displayData['row'];
$params = $displayData['params'];
$jemsettings = $displayData['jemsettings'];
$settings = $displayData['settings'];
$isSafari = (bool) $displayData['isSafari'];
$showIconsInEventTitle = (bool) $displayData['showIconsInEventTitle'];
$showIconsInEventData = (bool) $displayData['showIconsInEventData'];
$showAvailabilityText = (bool) $displayData['showAvailabilityText'];
$structuredData = !empty($displayData['structuredData']);
$imagePathAware = !empty($displayData['imagePathAware']);
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$eventRoute = Route::_(JemHelperRoute::getEventRoute($row->slug));
$eventAccess = empty($row->user_has_access_event)
    ? '<span class="icon-lock jem-lockicon" aria-hidden="true"></span>'
    : '';
$pageClass = trim((string) $params->get('pageclass_sfx', ''));
$classes = !empty($row->featured)
    ? 'jem-event jem-row jem-justify-start jem-featured'
    : 'jem-event jem-row jem-justify-start jem-odd' . ((int) $row->odd + 1);

if ($pageClass !== '') {
    $classes .= ' ' . $pageClass;
}

$classes .= ' event_id' . (int) $row->id;

if (!empty($row->locid)) {
    $classes .= ' venue_id' . (int) $row->locid;
}

$listClickable = (int) $jemsettings->showdetails === 1
    && !$isSafari
    && (!empty($row->featured) || (int) $jemsettings->gddisabled === 0);
$detailsClickable = (int) $jemsettings->showdetails === 1
    && !$isSafari
    && !$listClickable
    && (int) $jemsettings->gddisabled === 1;
?>
<li class="<?php echo $escape($classes); ?>"<?php echo $structuredData ? ' itemscope="itemscope" itemtype="https://schema.org/Event"' : ''; ?><?php if ($listClickable) : ?> data-jem-event-url="<?php echo $escape($eventRoute); ?>"<?php endif; ?>>
    <?php if ((int) $jemsettings->showeventimage === 1) : ?>
        <div class="jem-list-img">
            <?php if (!empty($row->datimage)) : ?>
                <?php
                $dimage = $imagePathAware
                    ? JemImage::flyercreator($row->datimage, 'event', $row->image_path ?? '')
                    : JemImage::flyercreator($row->datimage, 'event');
                echo JemOutput::flyer($row, $dimage, 'event');
                ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="jem-event-details"<?php if ($detailsClickable) : ?> data-jem-event-url="<?php echo $escape($eventRoute); ?>"<?php endif; ?>>
        <?php if ((int) $jemsettings->showtitle === 1 && (int) $jemsettings->showdetails === 1) : ?>
            <h3 title="<?php echo $escape(Text::_('COM_JEM_TABLE_TITLE') . ': ' . $row->title); ?>">
                <a href="<?php echo $escape($eventRoute); ?>"<?php echo $structuredData ? ' itemprop="name"' : ''; ?>><?php echo $escape($row->title); ?></a>
                <?php echo $showIconsInEventTitle ? JemOutput::recurrenceicon($row) : ''; ?>
                <?php echo JemOutput::publishstateicon($row); ?>
                <?php if (!empty($row->featured) && $showIconsInEventTitle) : ?>
                    <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
                <?php endif; ?>
                <?php echo $eventAccess; ?>
                <?php echo JemOutput::eventStateBadges($row, $structuredData, $showAvailabilityText); ?>
                <?php echo JemOutput::typeBadge($row); ?>
            </h3>
        <?php elseif ((int) $jemsettings->showtitle === 1) : ?>
            <h4 title="<?php echo $escape(Text::_('COM_JEM_TABLE_TITLE') . ': ' . $row->title); ?>">
                <span<?php echo $structuredData ? ' itemprop="name"' : ''; ?>><?php echo $escape($row->title); ?></span>
                <?php echo $showIconsInEventTitle ? JemOutput::recurrenceicon($row) : ''; ?>
                <?php echo JemOutput::publishstateicon($row); ?>
                <?php if (!empty($row->featured) && $showIconsInEventTitle) : ?>
                    <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
                <?php endif; ?>
                <?php echo $eventAccess; ?>
                <?php echo JemOutput::eventStateBadges($row, $structuredData, $showAvailabilityText); ?>
                <?php echo JemOutput::typeBadge($row); ?>
            </h4>
        <?php elseif ((int) $jemsettings->showdetails === 1) : ?>
            <h4>
                <a href="<?php echo $escape($eventRoute); ?>">
                    <?php echo JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $jemsettings->showtime); ?>
                    <?php if ($structuredData) : ?>
                        <?php echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, true, $row); ?>
                    <?php endif; ?>
                </a>
                <?php echo $showIconsInEventTitle ? JemOutput::recurrenceicon($row) : ''; ?>
                <?php echo JemOutput::publishstateicon($row); ?>
                <?php if (!empty($row->featured) && $showIconsInEventTitle) : ?>
                    <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
                <?php endif; ?>
                <?php echo $eventAccess; ?>
                <?php echo JemOutput::eventStateBadges($row, $structuredData, $showAvailabilityText); ?>
                <?php echo JemOutput::typeBadge($row); ?>
            </h4>
        <?php else : ?>
            <h4>
                <?php echo JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $jemsettings->showtime); ?>
                <?php if ($structuredData) : ?>
                    <?php echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, true, $row); ?>
                <?php endif; ?>
                <?php echo $showIconsInEventTitle ? JemOutput::recurrenceicon($row) : ''; ?>
                <?php echo JemOutput::publishstateicon($row); ?>
                <?php if (!empty($row->featured) && $showIconsInEventTitle) : ?>
                    <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
                <?php endif; ?>
                <?php echo $eventAccess; ?>
                <?php echo JemOutput::eventStateBadges($row, $structuredData, $showAvailabilityText); ?>
                <?php echo JemOutput::typeBadge($row); ?>
            </h4>
        <?php endif; ?>

        <div class="jem-list-row">
            <?php if ((int) $jemsettings->showtitle === 1) : ?>
                <?php $dateText = JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $jemsettings->showtime); ?>
                <div class="jem-event-info" title="<?php echo $escape(Text::_('COM_JEM_TABLE_DATE') . ': ' . strip_tags($dateText)); ?>">
                    <?php echo $showIconsInEventData ? '<i class="far fa-clock" aria-hidden="true"></i>' : ''; ?>
                    <?php echo $dateText; ?>
                    <?php if ($structuredData) : ?>
                        <?php echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, true, $row); ?>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="jem-event-info" title="<?php echo $escape(Text::_('COM_JEM_TABLE_TITLE') . ': ' . $row->title); ?>">
                    <?php echo $showIconsInEventData ? '<i class="fa fa-comment" aria-hidden="true"></i>' : ''; ?>
                    <span<?php echo $structuredData ? ' itemprop="name"' : ''; ?>><?php echo $escape($row->title); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($row->user_has_access_venue)) : ?>
                <?php if ((int) $jemsettings->showlocate === 1 && !empty($row->locid)) : ?>
                    <div class="jem-event-info" title="<?php echo $escape(Text::_('COM_JEM_TABLE_LOCATION') . ': ' . $row->venue); ?>"<?php echo $structuredData ? ' itemprop="location" itemscope itemtype="https://schema.org/Place"' : ''; ?>>
                        <?php echo $showIconsInEventData ? '<i class="fa fa-map-marker" aria-hidden="true"></i>' : ''; ?>
                        <?php if ((int) $jemsettings->showlinkvenue === 1) : ?>
                            <a href="<?php echo $escape(Route::_(JemHelperRoute::getVenueRoute($row->venueslug))); ?>"><span<?php echo $structuredData ? ' itemprop="name"' : ''; ?>><?php echo $escape($row->venue); ?></span></a>
                        <?php else : ?>
                            <span<?php echo $structuredData ? ' itemprop="name"' : ''; ?>><?php echo $escape($row->venue); ?></span>
                        <?php endif; ?>
                        <?php if ($structuredData) : ?>
                            <div itemprop="address" itemscope itemtype="https://schema.org/PostalAddress" hidden>
                                <?php if (!empty($row->street)) : ?><meta itemprop="streetAddress" content="<?php echo $escape($row->street); ?>" /><?php endif; ?>
                                <?php if (!empty($row->postalCode)) : ?><meta itemprop="postalCode" content="<?php echo $escape($row->postalCode); ?>" /><?php endif; ?>
                                <?php if (!empty($row->city)) : ?><meta itemprop="addressLocality" content="<?php echo $escape($row->city); ?>" /><?php endif; ?>
                                <?php if (!empty($row->state)) : ?><meta itemprop="addressRegion" content="<?php echo $escape($row->state); ?>" /><?php endif; ?>
                                <?php if (!empty($row->country)) : ?><meta itemprop="addressCountry" content="<?php echo $escape($row->country); ?>" /><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ((int) $jemsettings->showcity === 1 && !empty($row->city)) : ?>
                    <div class="jem-event-info" title="<?php echo $escape(Text::_('COM_JEM_TABLE_CITY') . ': ' . $row->city); ?>">
                        <?php echo $showIconsInEventData ? '<i class="fa fa-building" aria-hidden="true"></i>' : ''; ?>
                        <?php echo $escape($row->city); ?>
                    </div>
                <?php endif; ?>

                <?php if ((int) $jemsettings->showstate === 1 && !empty($row->state)) : ?>
                    <div class="jem-event-info" title="<?php echo $escape(Text::_('COM_JEM_TABLE_STATE') . ': ' . $row->state); ?>">
                        <?php echo $showIconsInEventData ? '<i class="fa fa-map" aria-hidden="true"></i>' : ''; ?>
                        <?php echo $escape($row->state); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ((int) $jemsettings->showcat === 1) : ?>
                <?php $categoryList = JemOutput::getCategoryList($row->categories, $jemsettings->catlinklist); ?>
                <div class="jem-event-info" title="<?php echo $escape(Text::_('COM_JEM_TABLE_CATEGORY') . ': ' . strip_tags(implode(', ', $categoryList))); ?>">
                    <?php echo $showIconsInEventData ? '<i class="fa fa-tag" aria-hidden="true"></i>' : ''; ?>
                    <?php echo implode(', ', $categoryList); ?>
                </div>
            <?php endif; ?>

            <?php if ((int) $jemsettings->showatte === 1) : ?>
                <?php $registrationCount = (int) ($row->regCount ?? 0); ?>
                <?php $maximumPlaces = (int) ($row->maxplaces ?? 0); ?>
                <?php if ($registrationCount > 0) : ?>
                    <div class="jem-event-info" title="<?php echo $escape(Text::_('COM_JEM_TABLE_ATTENDEES') . ': ' . $registrationCount); ?>">
                        <?php echo $showIconsInEventData ? '<i class="fa fa-user" aria-hidden="true"></i>' : ''; ?>
                        <?php echo $registrationCount . ' / ' . $maximumPlaces; ?>
                    </div>
                <?php elseif ($maximumPlaces === 0) : ?>
                    <div><?php echo $showIconsInEventData ? '<i class="fa fa-user" aria-hidden="true"></i>' : ''; ?> &gt; 0</div>
                <?php else : ?>
                    <div class="jem-event-info-small jem-event-attendees">
                        <?php echo $showIconsInEventData ? '<i class="fa fa-user" aria-hidden="true"></i>' : ''; ?>
                        &lt; <?php echo $maximumPlaces; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ((int) $params->get('show_introtext_events', 0) === 1) : ?>
            <div class="jem-event-intro">
                <?php echo $row->introtext ?? ''; ?>
                <?php if ($settings->get('event_show_readmore') && $row->fulltext !== '' && $row->fulltext !== '<br>') : ?>
                    <a href="<?php echo $escape($eventRoute); ?>"><?php echo Text::_('COM_JEM_EVENT_READ_MORE_TITLE'); ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($structuredData) : ?>
        <?php $absoluteEventRoute = rtrim(Uri::base(), '/') . $eventRoute; ?>
        <meta itemprop="url" content="<?php echo $escape($absoluteEventRoute); ?>" />
        <meta itemprop="identifier" content="<?php echo $escape($absoluteEventRoute); ?>" />
    <?php endif; ?>
</li>
